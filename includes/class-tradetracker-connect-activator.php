<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/includes
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 */
class Tradetracker_Connect_Activator
{
	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    2.0.0
	 */
	public static function activate()
	{
		$errorMessage = null;
		if (version_compare(phpversion(), '8.0', '<')) {
			// PHP version isn't high enough
			$errorMessage = ' plugin could not be activated because it requires PHP version 8.0 or greater. Please upgrade your PHP version.';
		}
		if (version_compare(get_bloginfo('version'), '5.4', '<')) {
			// WP version isn't high enough
			$errorMessage = ' plugin could not be activated because it requires WordPress version 5.4 or greater. Please upgrade your installation of WordPress.';
		}

		if (isset($_GET['activate'])) {
			unset($_GET['activate']);
		}
	}
}
