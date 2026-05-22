# wp-theme-blockaide

**Theme Blockaide** is a lightweight developer utility designed to bridge the gap between WordPress Full Site Editing (FSE) and traditional file-based Git deployment workflows. By automatically detecting your environment, it acts as a digital assistant that freezes the Site Editor UI on staging and production — preventing accidental database template overrides and keeping your source-controlled theme files as the single source of truth.

---

## How it works

WordPress FSE writes templates and template parts to the database when saved via the Site Editor. DB-stored records silently take precedence over theme files, which breaks file-based deployment pipelines (rsync, GitHub Actions, etc.). Theme Blockaide solves this by removing all Site Editor entry points on any environment other than `local`, leaving FSE fully functional for development while locking it down everywhere else.

On non-local environments, seven lockout vectors are applied:

- **Admin menu** — removes Appearance > Editor
- **Admin toolbar** — removes the Edit Site node on the frontend toolbar
- **Direct URL access** — redirects `/wp-admin/site-editor.php` to `/wp-admin/`
- **Block editor template mode** — suppresses the Template tab in the post/page editor sidebar
- **Template path resolution** — intercepts `.html` FSE template lookup so DB-stored templates cannot be served even if the above UI entry points are somehow bypassed
- **Command Palette** — deregisters the `core/edit-site` command from the WP 7.0+ Command Palette (cmd+k / ctrl+k), which bypasses PHP hooks entirely
- **Customize button** — removes the non-functional Customize button from the Themes admin screen

On `local`, the plugin does nothing. FSE works normally.

---

## Requirements

- WordPress 5.9 or later (FSE / block theme support)
- PHP 8.0 or later (`str_ends_with()` is used internally)
- `WP_ENVIRONMENT_TYPE` defined in `wp-config.php` on each target environment

---

## Installation

Must-use plugins are not managed via the WordPress Plugins screen and cannot be installed through it. They must be placed on the server manually or via a deployment pipeline.

### Important: the subdirectory loader

WordPress only auto-loads PHP files placed **directly** in `mu-plugins/` — it does not recurse into subdirectories. Because this plugin lives in a subdirectory (`mu-plugins/wp-theme-blockaide/`), a small loader file must also be present one level up in `mu-plugins/` to require it.

Create `wp-content/mu-plugins/load-theme-blockaide.php` with the following contents:

```php
<?php
/**
* Plugin Name: Theme Blockaide Loader
* Description: Safely loads Theme Blockaide plugin's environment-locking utility to protect file-based workflows.
*/
// Load Theme Blockaide from its clean subfolder
$_blockaide_file = WPMU_PLUGIN_DIR . '/wp-theme-blockaide/wp-theme-blockaide.php';
if ( file_exists( $_blockaide_file ) ) {
	require_once $_blockaide_file;
}
unset( $_blockaide_file );
```

This file is intentionally kept outside the versioned repo — it is a one-time server setup step, not something that gets deployed with each release. Without it, the plugin will be present on disk but will never load.

### Option A: Manual (copy via SSH or FTP)

1. Copy the `wp-theme-blockaide` folder into `wp-content/mu-plugins/`:
   ```
   wp-content/
   └── mu-plugins/
       ├── load-theme-blockaide.php   ← create this manually (see above)
       └── wp-theme-blockaide/
           └── wp-theme-blockaide.php
   ```
2. Create `load-theme-blockaide.php` as shown above if it does not already exist.
3. No activation step required — WordPress loads must-use plugins automatically on the next request.

### Option B: WP-CLI

If you have WP-CLI access and the repo checked out locally:

```bash
cp -r wp-theme-blockaide /path/to/wordpress/wp-content/mu-plugins/
```

Then create the loader file if it does not exist:

```bash
cat > /path/to/wordpress/wp-content/mu-plugins/load-theme-blockaide.php << 'EOF'
<?php
/**
* Plugin Name: Theme Blockaide Loader
* Description: Safely loads Theme Blockaide plugin's environment-locking utility to protect file-based workflows.
*/
// Load Theme Blockaide from its clean subfolder
$_blockaide_file = WPMU_PLUGIN_DIR . '/wp-theme-blockaide/wp-theme-blockaide.php';
if ( file_exists( $_blockaide_file ) ) {
	require_once $_blockaide_file;
}
unset( $_blockaide_file );
EOF
```

---

## Configuration

No settings page. Behaviour is controlled entirely by `WP_ENVIRONMENT_TYPE` in `wp-config.php`.

```php
// Local — FSE fully available, plugin does nothing
define( 'WP_ENVIRONMENT_TYPE', 'local' );

// Staging — all lockout vectors active
define( 'WP_ENVIRONMENT_TYPE', 'staging' );

// Production — all lockout vectors active
define( 'WP_ENVIRONMENT_TYPE', 'production' );
```

If `WP_ENVIRONMENT_TYPE` is not defined, `wp_get_environment_type()` returns `'production'` by default, so the lockout will be active.

---

## Verifying the lockout

After deploying to a non-local environment, confirm the following:

1. **Admin menu** — Appearance > Editor does not appear in the sidebar
2. **Direct URL** — navigating to `/wp-admin/site-editor.php` redirects to `/wp-admin/`
3. **Admin toolbar** — Edit Site is absent from the frontend toolbar when logged in as admin
4. **Block editor** — no Template tab in the Document panel when editing a post or page
5. **Command Palette** — opening cmd+k / ctrl+k in the block editor shows no Site Editor or Edit Site commands
6. **Themes page** — no Customize button appears for the active theme on `/wp-admin/themes.php`
7. **Frontend rendering** — the theme still loads and renders correctly (templates are served from theme files as expected)

---

## Changelog

= 1.0.0 =
* Initial release
