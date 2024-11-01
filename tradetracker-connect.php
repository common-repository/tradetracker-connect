<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @since             2.0.0
 * @package           Tradetracker_Connect
 *
 * @wordpress-plugin
 * Plugin Name:       TradeTracker Connect
 * Description:       Seamless WordPress integration for TradeTracker's Merchants.
 * Version:           2.2.10
 * Author:            TradeTracker.com
 * Author URI:        https://tradetracker.com
 * License:           GPLv3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       tradetracker-connect
 * Domain Path:       /languages
 * Requires at least: 5.5
 * Tested up to:      6.6
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Current plugin version.
 * Start at version 2.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('TRADETRACKER_CONNECT_VERSION', '2.2.10');
define('TRADETRACKER_CONNECT_URL', plugin_dir_url(__FILE__));
define('TRADETRACKER_CONNECT_PATH', plugin_dir_path(__FILE__));
define('TRADETRACKER_CONNECT_BASENAME', plugin_basename(__FILE__));
define('TRADETRACKER_CONNECT_DOMAIN', 'tradetracker-connect');
define('TRADETRACKER_CONNECT_MERCHANT_WSDL', 'https://ws.tradetracker.com/soap/merchant?wsdl');
define('TRADETRACKER_CONNECT_SANDBOX', false);
define('TRADETRACKER_CONNECT_DEMO', false);
define('TRADETRACKER_CONNECT_ORDER_CRON_DELAY_SECONDS', (60 * 60 * 6));
define('TRADETRACKER_PRODUCTS_PER_BATCH', 10);
define('TRADETRACKER_PRODUCTS_BATCH_DELAY_INTERVAL', 10000);
define('TRADETRACKER_CONNECT_NONCE', 'tradetracker_connect_nonce');
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-tradetracker-connect-activator.php
 */
function tradetracker_connect_activate() : void
{
	require_once TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect-activator.php';
	Tradetracker_Connect_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-tradetracker-connect-deactivator.php
 */
function tradetracker_connect_deactivate() : void
{
	require_once TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect-deactivator.php';
	Tradetracker_Connect_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'tradetracker_connect_activate');
register_deactivation_hook(__FILE__, 'tradetracker_connect_deactivate');

if(in_array('tradetracker-woocommerce/tradetracker-tracking.php', apply_filters('active_plugins', get_option('active_plugins')))){
	// Old plugin is installed meaning we can't enable our plugin, we need to disable it and show a notice to the user
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	deactivate_plugins(TRADETRACKER_CONNECT_BASENAME);
	add_action('admin_notices', function() {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
			<?php
				printf(
					esc_html__('%s plugin has been disabled because you have the older version enabled as well. Please disable the older version and try again.', 'tradetracker-connect'),
					'<strong><em>' . esc_html__('TradeTracker Connect', 'tradetracker-connect') . '</em></strong>'
				);
			?></p>
		</div>
		<?php
	});
	return;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    2.0.0
 */
function tradetracker_connect_run() : void
{
	$plugin = new Tradetracker_Connect();
	$plugin->run();
}

tradetracker_connect_run();
