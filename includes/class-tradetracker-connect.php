<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/includes
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 */

class Tradetracker_Connect
{
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      Tradetracker_Connect_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string $tradetracker_connect The string used to uniquely identify this plugin.
	 */
	protected $tradetracker_connect;

	/**
	 * The current version of the plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    2.0.0
	 */
	public function __construct()
	{
		$this->version = TRADETRACKER_CONNECT_VERSION;
		$this->tradetracker_connect = TRADETRACKER_CONNECT_DOMAIN;

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Tradetracker_Connect_Loader. Orchestrates the hooks of the plugin.
	 * - Tradetracker_Connect_i18n. Defines internationalization functionality.
	 * - Tradetracker_Connect_Admin. Defines all hooks for the admin area.
	 * - Tradetracker_Connect_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function load_dependencies() : void
	{
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect-loader.php';

		/**
		 * The class responsible for communicating with the Merchant WebService using
		 * the SOAP client.
		 */
		require_once TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect-soap.php';

		/**
		 * The class responsible for product feed generation.
		 */
		require_once TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect-feed.php';

		/**
		 * The class responsible for extended functionality for SimpleXML.
		 */
		require_once TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect-xml.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once TRADETRACKER_CONNECT_PATH . 'includes/class-tradetracker-connect-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once TRADETRACKER_CONNECT_PATH . 'admin/class-tradetracker-connect-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once TRADETRACKER_CONNECT_PATH . 'public/class-tradetracker-connect-public.php';

		$this->loader = new Tradetracker_Connect_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Tradetracker_Connect_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function set_locale() : void
	{
		$plugin_i18n = new Tradetracker_Connect_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Register all hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_admin_hooks() : void
	{
		$plugin_admin = new Tradetracker_Connect_Admin($this->get_tradetracker_connect(), $this->get_version());
		$plugin_soap = new Tradetracker_Connect_Soap();

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		$this->loader->add_filter('plugin_action_links_' . TRADETRACKER_CONNECT_BASENAME, $plugin_admin, 'plugin_action_links');
		$this->loader->add_filter('admin_menu', $plugin_admin, 'add_menu_items');

		$this->loader->add_action('init', $plugin_admin, 'bypass_authentication', 100);
		$this->loader->add_action('admin_init', $plugin_admin, 'tradetracker_connect_register_settings');
		$this->loader->add_action('tradetracker_connect_feed_cron_hook', $plugin_admin, 'cron_feed_handler');

		if (extension_loaded('soap') === true) {
			$this->loader->add_action('tradetracker_connect_order_cron_hook', $plugin_soap, 'cron_update_order');
			$this->loader->add_action('woocommerce_pre_payment_complete', $plugin_soap, 'tradetracker_connect_on_update_order');
			$this->loader->add_action('woocommerce_update_order', $plugin_soap, 'tradetracker_connect_on_update_order');
			$this->loader->add_action('woocommerce_new_order', $plugin_soap, 'tradetracker_connect_on_update_order');
			$this->loader->add_action('woocommerce_order_status_changed', $plugin_soap, 'tradetracker_connect_on_update_order');
		}
	}

	/**
	 * Register all hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_public_hooks() : void
	{
		$plugin_public = new Tradetracker_Connect_Public($this->get_tradetracker_connect(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
		$this->loader->add_action('template_redirect', $plugin_public, 'directlinking_handler');
		$this->loader->add_action('woocommerce_thankyou', $plugin_public, 'woocommerce_tracking_hook');
	}

	/**
	 * Run the loader to execute all hooks with WordPress.
	 *
	 * @since    2.0.0
	 */
	public function run() : void
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return    string    The name of the plugin.
	 * @since     2.0.0
	 */
	public function get_tradetracker_connect() : string
	{
		return $this->tradetracker_connect;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return    Tradetracker_Connect_Loader    Orchestrates the hooks of the plugin.
	 * @since     2.0.0
	 */
	public function get_loader() : Tradetracker_Connect_Loader
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return    string    The version number of the plugin.
	 * @since     2.0.0
	 */
	public function get_version() : string
	{
		return $this->version;
	}
}
