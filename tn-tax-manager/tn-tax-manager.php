<?php
/**
 * Plugin Name: TN Tax Manager
 * Plugin URI: https://github.com/cchatterton/tn-tax-manager/releases/latest
 * Description: Lightweight front-end taxonomy management with AI suggestions and guided taxonomy cleanup.
 * Version: 1.0.32
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/cchatterton/tn-tax-manager
 * Author: Techn
 * Author URI: https://techn.com.au/
 * Text Domain: tn-tax-manager
 */

if (!defined('ABSPATH')) exit;

define('TN801_TTM_PLUGIN_FILE', __FILE__);
define('TN801_TTM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('TN801_TTM_VERSION', '1.0.32');

define('TN801_TTM_MODE_OPTION', 'tn801_ttm_display_mode');
define('TN801_TTM_TAXONOMY_OPTION', 'tn801_ttm_taxonomy');

define('TN801_TTM_DEFAULT_MODE', 'compact');
define('TN801_TTM_DEFAULT_TAXONOMY', 'category');

$dir = plugin_dir_path(__FILE__);

$functions = array(
	'install.php',
	'helpers.php',
	'frontend.php',
	'openai.php',
	'actions.php',
	'admin.php',
	'updater.php',
);

foreach ($functions as $function) {
	require($dir . 'functions/' . $function);
}

register_activation_hook(__FILE__, 'tn801_ttm_install');
