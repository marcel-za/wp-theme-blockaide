<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Name: Lock FSE on Non-Local Environments
 * Description: Prevents access to the Full Site Editor on staging and production, keeping FSE as a local-only build tool.
 * Version:     1.0.0
 */

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	return;
}

add_action( 'wp_loaded', 'wptba_lock_fse_on_non_local' );

function wptba_lock_fse_on_non_local() {
	if ( 'local' === wp_get_environment_type() ) {
		return;
	}

	if ( is_admin() ) {
		add_action( 'admin_menu',            'wptba_remove_fse_menu',          999 );
		add_action( 'admin_bar_menu',        'wptba_remove_fse_adminbar',      999 );
		add_action( 'admin_init',            'wptba_block_fse_direct_access'        );
		add_action( 'admin_enqueue_scripts', 'wptba_deregister_fse_commands'        );
		add_filter( 'user_has_cap',          'wptba_remove_customize_cap',  10, 3   );
	}

	add_filter( 'block_editor_settings_all', 'wptba_disable_template_mode'        );
	add_filter( 'theme_file_path',           'wptba_hide_fse_template_paths', 10, 2 );
}

function wptba_remove_fse_menu() {
	remove_submenu_page( 'themes.php', 'site-editor.php' );
}

/**
 * @param WP_Admin_Bar $wp_admin_bar
 */
function wptba_remove_fse_adminbar( $wp_admin_bar ) {
	$wp_admin_bar->remove_node( 'site-editor' );
}

/**
 * Redirects direct URL access to site-editor.php back to wp-admin.
 *
 * Uses $_SERVER['SCRIPT_NAME'] as the primary signal (more reliable than
 * $pagenow on some nginx configurations) with $pagenow as a fallback.
 */
function wptba_block_fse_direct_access() {
	global $pagenow;

	$script_name = isset( $_SERVER['SCRIPT_NAME'] )
		? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) )
		: '';

	if (
		str_ends_with( $script_name, '/site-editor.php' ) ||
		( isset( $pagenow ) && 'site-editor.php' === $pagenow )
	) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}

/**
 * Deregisters FSE-related commands from the WP 7.0+ Command Palette.
 *
 * The Command Palette (cmd+k) registers client-side commands via the
 * @wordpress/commands JS package. These bypass all PHP lockout hooks, so
 * we remove them via an inline script attached to the wp-commands handle.
 */
function wptba_deregister_fse_commands() {
	if ( ! wp_script_is( 'wp-commands', 'registered' ) ) {
		return;
	}

	$script = "wp.domReady( function() {
		if ( wp.data && wp.data.dispatch( 'core/commands' ) ) {
			wp.data.dispatch( 'core/commands' ).unregisterCommand( 'core/edit-site' );
		}
	} );";

	wp_add_inline_script( 'wp-commands', $script );
}

/**
 * Removes the customize capability on non-local environments.
 *
 * Hides the Customize button on wp-admin/themes.php, which is non-functional
 * for FSE/block themes but otherwise still renders.
 *
 * @param  array $allcaps All capabilities for the current user.
 * @param  array $caps    Required capabilities being checked.
 * @return array
 */
function wptba_remove_customize_cap( $allcaps, $caps ) {
	if ( in_array( 'customize', $caps, true ) ) {
		$allcaps['customize'] = false;
	}
	return $allcaps;
}

/**
 * @param  array $settings Block editor settings array.
 * @return array
 */
function wptba_disable_template_mode( $settings ) {
	$settings['supportsTemplateMode'] = false;
	return $settings;
}

/**
 * Hides FSE HTML templates in the admin by corrupting their resolved paths.
 *
 * WordPress uses the existence of .html files in a theme to determine whether
 * it is a block theme (see wp_is_block_theme()). By appending '.disable-fse'
 * to every .html path in the admin, the files appear not to exist on disk,
 * preventing FSE template resolution. Applied in the admin only so that
 * frontend rendering via the theme's .html templates is never affected.
 *
 * Approach sourced from the Cleaner class in janw-me/disable-full-site-editing.
 *
 * @param  string $path The resolved absolute path to the theme file.
 * @param  string $file The relative file path being requested.
 * @return string
 */
function wptba_hide_fse_template_paths( $path, $file ) {
	if ( ! is_admin() ) {
		return $path;
	}

	if ( str_ends_with( $path, '.html' ) ) {
		return $path . '.disable-fse';
	}

	return $path;
}
