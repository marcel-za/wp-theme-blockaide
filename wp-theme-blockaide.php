<?php
/**
 * Plugin Name: WP Theme Blockaide
 * Description: Locks the Full Site Editor on non-local environments, enabling FSE as a local-only build tool.
 * Version:     1.0.0
 * Requires PHP: 8.0
 */

define( 'WP_THEME_BLOCKAIDE_VERSION', '1.0.0' );

require_once __DIR__ . '/lock-fse-non-local.php';
