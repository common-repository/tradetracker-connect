<?php

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/includes
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 */
class Tradetracker_Connect_Deactivator
{
	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    2.0.0
	 */
	public static function deactivate()
	{
		wp_unschedule_event(wp_next_scheduled('tradetracker_connect_order_cron_hook'), 'tradetracker_connect_order_cron_hook');
		wp_unschedule_event(wp_next_scheduled('tradetracker_connect_feed_cron_hook'), 'tradetracker_connect_feed_cron_hook');
	}
}
