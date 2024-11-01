<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/admin
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 * @author     Robin Day <rday@tradetracker.com>
 */
class Tradetracker_Connect_Admin
{
	/**
	 * The ID of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string $tradetracker_connect The ID of this plugin.
	 */
	private $tradetracker_connect;

	/**
	 * The version of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * The class for the product feed generation and handling.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      Tradetracker_Connect_Feed $feed Class for the product feed generation.
	 */
	private $feed;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $tradetracker_connect The name of this plugin.
	 * @param string $version The version of this plugin.
	 * @since    2.0.0
	 */
	public function __construct(string $tradetracker_connect, string $version)
	{

		$this->tradetracker_connect = $tradetracker_connect;
		$this->version = $version;
		$this->feed = new Tradetracker_Connect_Feed();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function enqueue_styles() : void
	{
		wp_enqueue_style($this->tradetracker_connect, TRADETRACKER_CONNECT_URL . 'admin/css/tradetracker-connect-admin.css', [], $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function enqueue_scripts() : void
	{
		wp_enqueue_script($this->tradetracker_connect, TRADETRACKER_CONNECT_URL . 'admin/js/tradetracker-connect-admin.js', ['jquery', 'lodash'], $this->version, false);
	}

	/**
	 * Register sidebar menu entries for the admin area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function add_menu_items() : void
	{
		global $submenu;
		$parent_slug = 'tradetracker-configuration';
		add_menu_page(
			__('TradeTracker', 'tradetracker-connect'),
			__('TradeTracker', 'tradetracker-connect'),
			'manage_options',
			$parent_slug,
			[$this, 'display_admin_setup'],
			TRADETRACKER_CONNECT_URL . 'assets/logo.svg',//" style="padding:0;height:97%;width:auto',
			100
		);
		// If the WooCommerce plugin is active.
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			add_submenu_page(
				$parent_slug,
				__('Product Feed', 'tradetracker-connect'),
				__('Product Feed', 'tradetracker-connect'),
				'manage_options',
				'tradetracker-product-feed',
				[$this, 'display_admin_feed'],
				10
			);
			// Modify first entry and the first index of the entry (title)
			$submenu[$parent_slug][0][0] = __('Merchant Setup', 'tradetracker-connect');
        }
	}


	/**
	 * Add the settings page to the plugin links on the plugin page.
	 *
	 * @param $links
	 * @return array|string[]
	 * @since    2.0.0
	 */
	public function plugin_action_links($links) : array
	{
		return array_merge([
			sprintf('<a href="%1$s">%2$s</a>', admin_url('admin.php?page=tradetracker-configuration'), __('Settings')),
		], $links);
	}

	/**
	 * Display the admin setup partial view.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function display_admin_setup() : void
	{
		include(TRADETRACKER_CONNECT_PATH . 'admin/partials/tradetracker-connect-admin-setup.php');
	}

	/**
	 * Display the admin product feed partial view.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function display_admin_feed() : void
	{
		if (isset($_GET['action']) && $_GET['action'] === 'generate-now') {
			$this->feed->generate();
			wp_redirect(admin_url('admin.php?page=tradetracker-product-feed'));
		}

		include(TRADETRACKER_CONNECT_PATH . 'admin/partials/tradetracker-connect-admin-feed.php');
	}

	/**
	 * CRON handler that will process the generation of the product feed.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function cron_feed_handler() : void
	{
		$this->feed->generate();
	}

	/**
	 * Filter that will bypass the authentication check for the API.
	 * Should only bypass when executed by cron.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function bypass_authentication() : void
	{
		if (wp_doing_cron()) {
			$user = new WP_User(1);
			wp_set_current_user($user->ID);
			add_filter('woocommerce_api_check_authentication', function ($original) use ($user) {
				return $user;
			}, 100);
		}
	}

	/**
	 * Register the settings for the admin area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_register_settings() : bool
	{
		if (!current_user_can('manage_options')) {
			return false;
		}

		if (array_key_exists('action', $_POST) && sanitize_text_field($_POST['action']) === 'update') {
			if (empty($_POST[TRADETRACKER_CONNECT_NONCE])) {
				return false;
			}
			if (
				array_key_exists('option_page', $_POST)
				&& sanitize_text_field($_POST['option_page']) === 'tradetracker-connect-setup'
				&& !(check_admin_referer('update_setup', TRADETRACKER_CONNECT_NONCE) && wp_verify_nonce(sanitize_text_field($_POST[TRADETRACKER_CONNECT_NONCE]), 'update_setup'))
			) {
				return false;
			}
			if (array_key_exists('option_page', $_POST)
				&& sanitize_text_field($_POST['option_page']) === 'tradetracker-connect-feed'
				&& !(check_admin_referer('update_feed', TRADETRACKER_CONNECT_NONCE) && wp_verify_nonce(sanitize_text_field($_POST[TRADETRACKER_CONNECT_NONCE]), 'update_feed'))
			) {
				return false;
			}
		}

		$setup_page = sprintf('%s-setup', $this->tradetracker_connect);
		// Campaign settings
		register_setting($setup_page, 'tradetracker_connect_campaign_options', [$this, 'tradetracker_connect_campaign_options_validate']);
		add_settings_section('campaign_settings', '', [$this, 'tradetracker_connect_campaign_text'], $setup_page);
		add_settings_field('tradetracker_connect_campaign_cid', __('Campaign ID', 'tradetracker-connect'), [$this, 'tradetracker_connect_campaign_cid'], $setup_page, 'campaign_settings');
		add_settings_field('tradetracker_connect_campaign_pid', __('Product Group ID', 'tradetracker-connect'), [$this, 'tradetracker_connect_campaign_pid'], $setup_page, 'campaign_settings');
		add_settings_field('tradetracker_connect_campaign_tgi', __('Tracking group ID (optional)', 'tradetracker-connect'), [$this, 'tradetracker_connect_campaign_tgi'], $setup_page, 'campaign_settings');
		add_settings_field('tradetracker_connect_campaign_tid', __('Add line item numeric suffix to transaction ID (optional)', 'tradetracker-connect'), [$this, 'tradetracker_connect_campaign_tid'], $setup_page, 'campaign_settings');
		add_settings_field('tradetracker_connect_campaign_unique_tid', __('Conversion transaction IDs unique (optional)', 'tradetracker-connect'), [$this, 'tradetracker_connect_campaign_unique_tid'], $setup_page, 'campaign_settings');

		// WebService settings
		register_setting($setup_page, 'tradetracker_connect_webservice_options', [$this, 'tradetracker_connect_webservice_options_validate']);
		add_settings_section('webservice_settings', '', [$this, 'tradetracker_connect_webservice_text'], $setup_page);
		if (extension_loaded('soap') === true) {
			add_settings_field('tradetracker_connect_webservice_customer_id', __('Customer ID', 'tradetracker-connect'), [$this, 'tradetracker_connect_webservice_customer_id'], $setup_page, 'webservice_settings');
			add_settings_field('tradetracker_connect_webservice_passphrase', __('Passphrase', 'tradetracker-connect'), [$this, 'tradetracker_connect_webservice_passphrase'], $setup_page, 'webservice_settings');
		}

		// DirectLinking settings
		register_setting($setup_page, 'tradetracker_connect_directlinking_options', [$this, 'tradetracker_connect_directlinking_options_validate']);
		add_settings_section('directlinking_settings', '', [$this, 'tradetracker_connect_directlinking_text'], $setup_page);
		add_settings_field('tradetracker_connect_directlinking_page', __('Local Redirect folder', 'tradetracker-connect'), [$this, 'tradetracker_connect_directlinking_page'], $setup_page, 'directlinking_settings');

		$feed_page = sprintf('%s-feed', $this->tradetracker_connect);
		// Product feed settings
		register_setting($feed_page, 'tradetracker_connect_feed_options', [$this, 'tradetracker_connect_feed_options_validate']);
		add_settings_section('feed_settings', '', [$this, 'tradetracker_connect_feed_text'], $feed_page);

		return true;
	}

	/**
	 * Validate the settings for the admin webservices area.
	 *
	 * @param ?array $input
	 * @return array
	 * @since    2.0.0
	 */
	public function tradetracker_connect_webservice_options_validate(?array $input) : array
	{
		$new_input = [];

		if (isset($input['customer_id'])) {
			$new_input['customer_id'] = intval($input['customer_id']);
		}

		if (isset($input['passphrase'])) {
			$new_input['passphrase'] = sanitize_text_field($input['passphrase']);
		}

		return $new_input;
	}

	/**
	 * Validate the settings for the admin product feed area.
	 *
	 * @param array|null $input
	 * @return array
	 * @since    2.0.0
	 */
	public function tradetracker_connect_feed_options_validate(?array $input) : array
	{
		$new_input = [];

		$options = $input ?? [];
		$options = array_map('sanitize_text_field', $options);

		if (!empty($options)) {
			$columns = $this->_getColumns();
			$products = $this->feed->get_raw_products(1);
			if (!isset($products[0]['id'])) {
				return [];
			}
			$product_keys = array_keys($products[0]);
			// Loop through each of the incoming options
			foreach ($options as $tt_key => $mapped_value) {
				$tt_key = sanitize_text_field($tt_key);
				// Check if the key is in $columns, if not then continue
				if (!in_array($tt_key, $columns, true)) {
					continue;
				}
				// First we check if $mappedValue contains a dot followed by star
				if (strpos($mapped_value, '*') !== false) {
					// If it does, we want to see if any of the array keys of $productKeys matches
					// $mappedValue using regex, swapping out the star for decimal
					$regex_key = str_replace('*', '\\d+', $mapped_value);
					$regex_key = str_replace('.', '\\.', $regex_key);
					$matches = preg_grep('/^' . $regex_key . '$/', $product_keys);
					if ($matches) {
						$new_input[$tt_key] = $mapped_value;
					}
				} elseif (in_array($mapped_value, $product_keys, true)) {
					// If it doesn't contain a star then $new_input can accept the key value as is
					$new_input[$tt_key] = $mapped_value;
				}
			}
		}

		// Reschedule the cron hook
		if (wp_next_scheduled('tradetracker_connect_feed_cron_hook')) {
			wp_unschedule_event(wp_next_scheduled('tradetracker_connect_feed_cron_hook'), 'tradetracker_connect_feed_cron_hook');
		}
		wp_schedule_event(time(), 'hourly', 'tradetracker_connect_feed_cron_hook');

		// Notify the feed generator that the feed has changed and can generate again
		$generator_options = get_option('tradetracker_connect_feed_generator', []);
		$generator_options['__generated'] = false;
		$generator_options['__generating'] = true;
		update_option('tradetracker_connect_feed_generator', $generator_options);

		return $new_input;
	}

	/**
	 * Validate the settings for the admin direct linking area.
	 *
	 * @param array $input
	 * @return array
	 * @since    2.0.0
	 */
	public function tradetracker_connect_directlinking_options_validate(array $input) : array
	{
        $new_input = [];
		if (isset($input['page']) && strlen($input['page'])) {
			if (!preg_match('/[^\/%]+/', $input['page'])) {
				$wp_permalink_article = '<a href="https://wordpress.org/support/article/using-permalinks/#choosing-your-permalink-structure" target="_blank">' . esc_html__('Learn more', 'tradetracker-connect') . '</a>';
				$error = sprintf(
					esc_html__('A structure tag is required when using custom permalinks, %s.', 'tradetracker-connect'),
					wp_kses($wp_permalink_article, array('a' => array('href' => array(), 'target' => array()))),
				);
			}
			if (!empty($error)) {
				if (function_exists('add_settings_error')) {
					add_settings_error('tradetracker_connect_directlinking_options', "invalid_page", $error);
				}
			} else {
				$new_input['page'] = sanitize_title($input['page']);
			}
		}

		return $new_input;
	}

	/**
	 * Validate the settings for the admin campaign area.
	 *
	 * @param array $input
	 * @return array
	 * @since    2.0.0
	 */
	public function tradetracker_connect_campaign_options_validate(array $input) : array
	{
		return array_map('intval', $input);
	}

	/**
	 * Render the text for the admin webservices area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_webservice_text() : void
	{
		echo '<hr/>';

		echo '<h2 class="wp-heading-inline">';
		esc_html_e('WebServices Integration', 'tradetracker-connect');
		echo '</h2>';

		$merchant_webservice_link = '<a href="https://merchant.tradetracker.com/webService" target="_blank">' . esc_html__('Merchant Web Services dashboard', 'tradetracker-connect') . '</a>';
		echo '<p>';
		echo sprintf(
			esc_html__('Configure your Merchant WebService integration by providing the fields below with values from your merchant dashboard. You can view these credentials at the %s.', 'tradetracker-connect'),
			wp_kses($merchant_webservice_link, array('a' => array('href' => array(), 'target' => array()))),
		);
		echo '</p>';

		if (extension_loaded('soap') === true) {
			$client = new Tradetracker_Connect_Soap();

			echo '<p>';
			esc_html_e('Connection Status', 'tradetracker-connect');
			echo ': ';

			if ($client->authenticate()) {
				echo '<span style="font-weight:bold;color:#00a32a;"><span class="dashicons dashicons-yes"></span>';
				esc_html_e('Connected', 'tradetracker-connect');
			} else {
				echo '<span style="font-weight:bold;color:#d63638;"><span class="dashicons dashicons-no"></span>';
				esc_html_e('Invalid credentials', 'tradetracker-connect');
			}
			echo '</span>';
		} else {
			echo '<em>';
			esc_html_e('WebServices Integration could not be enabled because the SOAP extension is not loaded. Please contact your hosting provider to enable the SOAP extension in your PHP configuration.', 'tradetracker-connect');
			echo '</em>';
		}
	}

	/**
	 * Render the text for the direct linking area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_directlinking_text() : void
	{
		$site = get_home_url(null);
		echo '<hr/>';

		echo '<h2 class="wp-heading-inline">';
		esc_html_e('Direct Linking Integration', 'tradetracker-connect');
		echo '</h2>';

		echo '<p>';
		$overview_link = sprintf('<a href="https://sc.tradetracker.net/implementation/overview?f[id]=1" target="_blank">%s</a>', __('here', 'tradetracker-connect'));
		printf(esc_html__('Enable the use of Direct Linking. Learn more about this function %s.', 'tradetracker-connect'), esc_url($overview_link));
		echo '</p>';

		esc_html_e('The local redirect folder name should be a phrase that best represents your shop or topic of your site.', 'tradetracker-connect');
		echo ' ';
		echo esc_html(
			sprintf(
				__('If you have a website that sells fashion items or clothing, the name of the redirect directory should represent this. For instance in this case it might be %1$s/fashion/ or %1$s/clothing/', 'tradetracker-connect'),
				esc_url($site)
			)
		);
		echo '<br>';
		echo esc_html(
			sprintf(
				__('Please refrain from using a name that resembles any connection or relation to TradeTracker like %1$s/tt/, %1$s/tracking/, %1$s/tradetracker/ or %1$s/redirect/ as this can cause issues with ad blockers. Also take care to not use an existing URL on your site', 'tradetracker-connect'),
				esc_url($site)
			)
		);
	}

	/**
	 * Render the text for the campaign area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_campaign_text() : void
	{
		echo '<hr/>';
		echo '<h2 class="wp-heading-inline">';
		esc_html_e('Campaign Integration', 'tradetracker-connect');
		echo '</h2>';
		echo '<p>';
		esc_html_e('Enter the IDs as received by TradeTracker.', 'tradetracker-connect');
		echo '</p>';
	}

	/**
	 * Render the text and markup for the admin product feed area.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_feed_text() : void
	{
		echo '<hr/>';
		echo '<h2 class="wp-heading-inline">';
		esc_html_e('Product Feed Mapper', 'tradetracker-connect');
		echo '</h2>';
		echo '<p>';
		esc_html_e('A product feed is a list of products that are available for sale on your website. TradeTracker will download this product feed regularly, so that the information is always up-to-date.', 'tradetracker-connect');
		echo '</p>';
		$generator_options = get_option('tradetracker_connect_feed_generator', []);
		$generating = (isset($generator_options['__generating']) && $generator_options['__generating'] === true);
		$generated = (isset($generator_options['__generated']) && $generator_options['__generated'] === true);

		if ($generated === true) {
			$this->createFeedDownloadLink();
		}

		if ($generated === true && $generating === false) {
			$status = sprintf('<span style="font-weight:bold;color:#00a32a;"><span class="dashicons dashicons-yes"></span>%s</span>', __('Ready', 'tradetracker-connect'));
		} elseif ($generated === true && $generating === true) {
			$status = sprintf('<span style="font-weight:bold;color:#d6a336;"><span class="dashicons dashicons-update"></span>%s</span>', __('Awaiting next update.. This could take a while.', 'tradetracker-connect'));
		} elseif ($generated === false && $generating === true) {
			$status = sprintf('<span style="font-weight:bold;color:#d6a336;"><span class="dashicons dashicons-download"></span>%s</span>', __('Preparing.. This could take a while.', 'tradetracker-connect'));
		} else {
			$status = sprintf('<span style="font-weight:bold;color:#d63638;"><span class="dashicons dashicons-no"></span>%s</span>', __('Not Ready', 'tradetracker-connect'));
		}

		echo '<p>';
		esc_html_e('Feed Status', 'tradetracker-connect');
		echo ': ';
		echo wp_kses($status, array(
			'span' => array(
				'style' => array(),
				'class' => array(),
			),
		));
		echo '</p>';

		echo '<p>';
		esc_html_e('Feed last generated', 'tradetracker-connect');
		echo ': <code>';
		if (!empty($generator_options['__generated_at'])) {
			echo wp_date(wc_date_format() . ' ' . wc_time_format(), $generator_options['__generated_at']);
		} else {
			esc_html_e('Never', 'tradetracker-connect');
		}
		echo '</code></p>';
		echo sprintf('<a href="%1$s" class="generateFeedNow">%2$s</a>', admin_url('admin.php?page=tradetracker-product-feed&action=generate-now'), esc_html__('Generate now', 'tradetracker-connect'));
	}

	/**
	 * Render the input field for the customer ID.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_webservice_customer_id() : void
	{
		$options = get_option('tradetracker_connect_webservice_options');
		printf('<input id="tradetracker_connect_webservice_customer_id" name="tradetracker_connect_webservice_options[customer_id]" type="text" value="%1$s">',
			isset($options['customer_id']) ? esc_attr((int)$options['customer_id']) : 0
		);
	}

	/**
	 * Render the input field for the passphrase.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_webservice_passphrase() : void
	{
		$options = get_option('tradetracker_connect_webservice_options');
		printf('<input id="tradetracker_connect_webservice_passphrase" name="tradetracker_connect_webservice_options[passphrase]" type="text" value="%1$s">',
			esc_attr($options['passphrase'] ?? '')
		);
	}

	/**
	 * Render the input field for the direct linking URL.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_directlinking_page() : void
	{
		$options = get_option('tradetracker_connect_directlinking_options');

		$campaign_options = get_option('tradetracker_connect_campaign_options');
		$cid = $campaign_options['cid'] ?? '';
		$page = $options['page'] ?? false;

		printf('<div>
				<code>%1$s/</code>
				<input name="tradetracker_connect_directlinking_options[page]" id="tradetracker_connect_directlinking_options[page]" type="text" value="%2$s" class="regular-text code">',
			esc_url(get_home_url(null)),
			esc_attr($page)
		);

		if ($page) {
			$test_url = get_home_url(null, $options['page']) . '/?tt=' . $cid . '_0_1_&r=';
			printf('<a href="%s" class="page-title-action test-button" target="_blank">%s</a>', esc_url($test_url), esc_html__('Test', 'tradetracker-connect'));
		}

		echo '</div>';

		echo '<div><p>';
		esc_html_e('After saving this field a button will show up to help you to validate if the link is correct by opening new tab which should redirect you to your homepage', 'tradetracker-connect');
		echo '</p></div>';
	}



	/**
	 * Render the input field for the campaign ID.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_campaign_cid() : void
	{
		$options = get_option('tradetracker_connect_campaign_options');
		$campaign_listing_page = '<a href="https://merchant.tradetracker.com/customerSite/list" target="_blank">' . esc_html__('campaign listing page', 'tradetracker-connect') . '</a>';

		printf('
			<div>
				<div>
					<input id="tradetracker_connect_campaign_cid[cid]" name="tradetracker_connect_campaign_options[cid]" type="text" value="%1$s">
				</div>
				<div><p>
					' . sprintf(
							esc_html__('The campaign ID can be found at the %s page in the ID column (located next to the campaign name) starting with an # character.', 'tradetracker-connect'),
							wp_kses($campaign_listing_page, array('a' => array('href' => array(), 'target' => array()))),
						) . '
				</p></div>
			</div>',
			esc_attr(isset($options['cid']) ? (int)$options['cid'] : 0)
		);
	}

	/**
	 * Render the input field for the campaign product group ID.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_campaign_pid() : void
	{
		$options = get_option('tradetracker_connect_campaign_options');
		printf('<input id="tradetracker_connect_campaign_pid[pid]" name="tradetracker_connect_campaign_options[pid]" type="text" value="%1$s">',
			esc_attr(isset($options['pid']) ? (int)$options['pid'] : 0)
		);
	}

	/**
	 * Render the input field for the tracking group ID.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_campaign_tgi() : void
	{
		$options = get_option('tradetracker_connect_campaign_options');
		printf('<input id="tradetracker_connect_campaign_tgi[tgi]" name="tradetracker_connect_campaign_options[tgi]" type="text" value="%1$s">',
			esc_attr(empty($options['tgi']) ? '' : (int)$options['tgi'])
		);
	}

	/**
	 * Render the checkbox for transaction grouping.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function tradetracker_connect_campaign_tid() : void
	{
		$options = get_option('tradetracker_connect_campaign_options');
		printf('<div>
			<input id="tradetracker_connect_campaign_tid[tid]" name="tradetracker_connect_campaign_options[tid]" type="checkbox" value="%1$s" onchange="this.value = this.checked ? 1 : 0;" %2$s>
		</div>
		<p>
			<em>%3$s</em>
		</p>',
			esc_attr(empty($options['tid']) ? '0' : '1'),
			esc_attr(empty($options['tid']) ? '' : 'checked'),
			wp_kses_post(esc_html__('If enabled, the transaction characteristic will be appended with the numeric index of the line item.
			E.g. if the order ID is 1234 and its associative basket contains 3 items, the order will end up as 1234_01, 1234_02 and 1234_03 within your TradeTracker dashboard.
			If disabled (default), you will simply see 3 basket items with the transaction identifier 1234. This setting will be ignored when "Conversion transaction IDs unique" 
			is enabled.', 'tradetracker-connect'))
		);
	}

	/**
	 * Render the checkbox for unique transaction ID setting
	 *
	 * @return void
	 * @since    2.2.1
	 */
	public function tradetracker_connect_campaign_unique_tid() : void
	{
		$options = get_option('tradetracker_connect_campaign_options');
		printf('<div>
			<input id="tradetracker_connect_campaign_unique_tid[unique_tid]" name="tradetracker_connect_campaign_options[unique_tid]" type="checkbox" value="%1$s" onchange="this.value = this.checked ? 1 : 0;" %2$s>
		</div>
		<p>
			<em>%3$s</em>
		</p>',
			esc_attr(empty($options['unique_tid']) ? '0' : '1'),
			esc_attr(empty($options['unique_tid']) ? '' : 'checked'),
			wp_kses_post(esc_html__('If enabled, the full order will be sent to TradeTracker using one unique 
			transaction ID. The basket items, will be sent along in the description field to Merchant and Affiliate 
			as well as the product field. Turn this on when your campaign setting: "Conversion transaction IDs unique" 
			is set to true. When in doubt, ask your Account Manager.', 'tradetracker-connect'))
		);
	}

	/**
	 * Get the columns for the column mapper.
	 *
	 * @return array
	 * @since    2.0.0
	 */
	protected function _getColumns() : array
	{
		$columns = [];

		try {
			$xml_feed = new SimpleXMLElement(require('partials/tradetracker_columns.xml.php'));
			$columns = (array)$xml_feed->columnName;
			sort($columns);

			// This allows us to define the order of the columns in the XML file and have the column mapper
			// automatically sort them by position. That way we can specify which columns are required and
			// which ones we want to suggest to the user
			$config = $this->_getMapperConfig();
			$positions = array_map(function ($col) use ($config) {
				return $config[$col] ?? INF;
			}, $columns);
			array_multisort($positions, $columns);
		} catch (Exception $e) {
		}

		return $columns;
	}

	/**
	 * Get the required and suggested columns for the column mapper.
	 *
	 * @return array
	 * @since    2.0.0
	 */
	protected function _getMapperConfig() : array
	{
		return [
			'name' => 'required',
			'productURL' => 'required',
			'price' => 'required',
			'EAN' => 'suggested',
			'UPC' => 'suggested',
			'description' => 'suggested',
			'imageURL' => 'suggested',
		];
	}

	public function createFeedDownloadLink(): void
	{
		echo sprintf('<p>%s: <a target="_blank" href="%s">%s</a></p>',
			esc_html__('Feed link', 'tradetracker-connect'),
			esc_url($this->feed->get_file_path(true)),
			esc_html($this->feed->get_file_path(true))
		);
	}
}
