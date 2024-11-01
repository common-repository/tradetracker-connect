<?php

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/public
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 */
class Tradetracker_Connect_Public
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
	 * Initialize the class and set its properties.
	 *
	 * @param string $tradetracker_connect The name of the plugin.
	 * @param string $version The version of this plugin.
	 * @since    2.0.0
	 */
	public function __construct(string $tradetracker_connect, string $version)
	{
		$this->tradetracker_connect = $tradetracker_connect;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function enqueue_styles() : void
	{
		wp_enqueue_style($this->tradetracker_connect, TRADETRACKER_CONNECT_URL . 'public/css/tradetracker-connect-public.css', [], $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function enqueue_scripts() : void
	{
		wp_enqueue_script($this->tradetracker_connect, TRADETRACKER_CONNECT_URL . 'public/js/tradetracker-connect-public.js', ['jquery'], $this->version, false);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @return void
	 * @since    2.0.0
	 */
	public function directlinking_handler() : void
	{
		$directlinking = get_option('tradetracker_connect_directlinking_options');
		$directlinking_parameter_detected = $this->detectConfiguredDirectLinkingUrl($directlinking['page'] ?? '', $this->get_current_url(), get_home_url());
		// Should only redirect when the directLinking parameter is found.
		if ($directlinking_parameter_detected === false) {
			return;
		}

		// Set domain name on which the redirect-page runs, WITHOUT "www.".
		$domain_name = str_replace(['www.', 'http://', 'https://'], '', get_home_url());
		// Set tracking group ID if provided by TradeTracker.

		$campaign_options = get_option('tradetracker_connect_campaign_options');
		$tracking_group_id = $campaign_options['tgi'] ?? '';
		// Set the P3P compact policy.
		header('P3P: CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
		$tt_param = sanitize_text_field($_GET['tt']);
		if (empty($tt_param)) {
			wc_get_logger()->error('TTC: Missing tt parameter for: ' . $this->get_current_url());
			return;
		}
		$redirect_url = isset($_GET['r']) ? sanitize_url($_GET['r']) : '';
		//wc_get_logger()->debug( 'TTC: tt_param: ' . $tt_param . ' and redirect param: ' . $redirect_url);

		// Set parameters.
		$tracking_param = explode('_', $tt_param);
		$campaign_id = $tracking_param[0] ?? '';
		$material_id = $tracking_param[1] ?? '';
		$affiliate_id = $tracking_param[2] ?? '';
		$reference = $tracking_param[3] ?? '';

		// Calculate MD5 checksum.
		$check_sum = md5('CHK_' . $campaign_id . '::' . $material_id . '::' . $affiliate_id . '::' . $reference);
		// Set tracking data.
		$tracking_data = $material_id . '::' . $affiliate_id . '::' . $reference . '::' . $check_sum . '::' . time();
		// Set regular tracking cookie.
		setcookie('TT2_' . $campaign_id, $tracking_data, time() + 31536000, '/', empty($domain_name) ? null : '.' . $domain_name);

		// Set session tracking cookie.
		setcookie('TTS_' . $campaign_id, $tracking_data, 0, '/', empty($domain_name) ? null : '.' . $domain_name);
		// Set tracking group cookie.
		if (!empty($tracking_group_id)) {
			setcookie('__tgdat' . $tracking_group_id, $tracking_data . '_' . $campaign_id, time() + 31536000, '/', empty($domain_name) ? null : '.' . $domain_name);
		}
		// Set track-back URL.
		$trackback_url = 'https://tc.tradetracker.net/?c=' . $campaign_id . '&m=' . $material_id . '&a=' . $affiliate_id . '&r=' . urlencode($reference) . '&u=' . urlencode($redirect_url);
		wc_get_logger()->debug( 'TTC: trackback url: ' . $trackback_url);
		// Redirect to TradeTracker.
		header('Location: ' . $trackback_url, true, 301);
		exit;
	}

	/**
	 * Inject tracking script on the order completed page.
	 *
	 * @param $order_id
	 * @return void
	 * @since    1.0.0
	 */
	public function woocommerce_tracking_hook($order_id) : void
	{
		$options = get_option('tradetracker_connect_campaign_options');
		if (empty($options)) {
			return;
		}

		// *****************
		$order = new WC_Order($order_id);

		$currency_code = htmlentities($order->get_currency(), ENT_QUOTES);
		$coupons_used = $order->get_coupon_codes();
		$coupon_used = htmlentities($coupons_used[0] ?? '', ENT_QUOTES); // We only need the first one, if applicable.

		// Option to add line item numeric suffix to transaction ID (optional) === off
		if (isset($options['unique_tid']) && $options['unique_tid'] === 1) {

			$line_item_index = 1;
			$merchantDescription = '';
			$affiliateDescription = '';
			$productInformation = [];
			foreach ($order->get_items() as $order_item) {
				// Text used in merchant description must contain the text "line item" (anycase) for text validation in \Tradetracker_Connect_Soap::cron_update_order
				$merchantDescription .= htmlentities('Line item #' . $line_item_index . ': ' . $order_item->get_quantity() . ' x ' . $order_item->get_name() . '. ', ENT_QUOTES);
				$affiliateDescription .= htmlentities($order_item->get_name() . ' ('.$order_item->get_quantity().'x)|', ENT_QUOTES);
				$productInformation[] = $this->createProductObjectJson($order_item);
				$line_item_index++;
			}

			// WooCommerce allows for modification of the original order identification code it shows to end users.
			// It does however not send that new code to hook woocommerce_thankyou which causes Woo to send the original order_id
			// to this hook. To avoid confusion, we send the modified code also as part of the merchantDescription when it is different from the order_id.
			if($order->get_order_number() != $order_id){
				$merchantDescription = $order->get_order_number() . ' ' . $merchantDescription;
			}

			echo $this->createConversionOptionsScript($options, (int)$order_id, (float)$order->get_subtotal(), 1, $merchantDescription, $affiliateDescription, $currency_code, $coupon_used, $productInformation);

		// Option to add line item numeric suffix to transaction ID (optional) === on
		} else {
			$line_item_index = 1;
			foreach ($order->get_items() as $order_item) {
				$transaction_id = (int)$order_id;
				if (isset($options['tid']) && $options['tid'] === 1) {
					$transaction_id .= sprintf('_%02d', $line_item_index);
				}

				$line_total = (float)$order_item->get_total();
				$line_subtotal = (float)$order_item->get_subtotal();
				$line_discount = $line_subtotal - $line_total;
				$line_end_total = htmlentities($line_subtotal - $line_discount, ENT_QUOTES);
				$quantity = $order_item->get_quantity();

				$product_name = $order_item->get_name();
				// Text used in merchant description must contain the text "line item" (anycase) for text validation in \Tradetracker_Connect_Soap::cron_update_order
				$merchantDescription = htmlentities($quantity . ' x ' . $product_name . ' (Line item #' . $line_item_index . ')', ENT_QUOTES);
				$affiliateDescription = htmlentities($product_name . '(x' . $quantity . ')', ENT_QUOTES);

				// WooCommerce allows for modification of the original order identification code it shows to end users.
				// It does however not send that new code to hook woocommerce_thankyou which causes Woo to send the original order_id
				// to this hook. To avoid confusion, we send the modified code also as part of the merchantDescription when it is different from the order_id.
				if ($order->get_order_number() != $order_id) {
					$merchantDescription = $order->get_order_number() . ' ' . $merchantDescription;
				}
				$productInformation = $this->createProductObjectJson($order_item);
				echo $this->createConversionOptionsScript(
					$options, $transaction_id, $line_end_total, $quantity, $merchantDescription, $affiliateDescription, $currency_code, $coupon_used, [$productInformation]
				);

				$line_item_index++;
			}
		}

		echo "
		<script type=\"text/javascript\">
			(function(ttConversionOptions) {
				var campaignID = 'campaignID' in ttConversionOptions ? ttConversionOptions.campaignID : ('length' in ttConversionOptions && ttConversionOptions.length ? ttConversionOptions[0].campaignID : null);
				var tt = document.createElement('script'); tt.type = 'text/javascript'; tt.async = true; tt.src = 'https://tm.tradetracker.net/conversion?s=' + encodeURIComponent(campaignID) + '&t=m';
				var s = document.getElementsByTagName('script'); s = s[s.length - 1]; s.parentNode.insertBefore(tt, s);
			})(ttConversionOptions);
		</script>";
	}

	/**
	 * echo the conversion scripts directly.
	 * should ideally be implemented with plain templates in a future iteration
	 */
	private function createConversionOptionsScript(
		array  $options, string $transaction_id, float $transaction_amount, int $quantity,
		string $merchantDescription, string $affiliateDescription, string $currency_code, string $coupon_used,
		array $productInformation
	): string
	{
		$campaign_id = empty($options['cid']) && $options['cid'] <= 0 ? '' : (int)$options['cid'];
		$tracking_group_id = empty($options['tgi']) && $options['tgi'] <= 0 ? '' : (int)$options['tgi'];
		$product_group_id = intval($options['pid'] ?? '');

		$returnString = '<script type="text/javascript">
					var ttConversionOptions = ttConversionOptions || [];
					ttConversionOptions.push({
					type: \'sales\',
					campaignID: \'' . esc_js($campaign_id) . '\',
					trackingGroupID: \'' . esc_js($tracking_group_id) . '\',
					productID: \'' . esc_js($product_group_id) . '\',
					transactionID: \'' . esc_js($transaction_id) . '\',
					transactionAmount: \'' . esc_js($transaction_amount) . '\',
					quantity: \'' . esc_js($quantity) . '\',
					descrMerchant: \'' . esc_js($merchantDescription) . '\',
					descrAffiliate: \'' . esc_js($affiliateDescription) . '\',
					currency: \'' . esc_js($currency_code) . '\',
					vc: \'' . esc_js($coupon_used) . '\',
					product: \'' . esc_js(json_encode($productInformation)) . '\',
				});
				</script>';

		$returnString .= '<noscript><img src="https://ts.tradetracker.net/?cid=' . esc_attr($campaign_id) .
			'&amp;tgi=' . esc_attr($tracking_group_id) .
			'&amp;pid=' . esc_attr($product_group_id) .
			'&amp;tid=' . esc_attr($transaction_id) .
			'&amp;tam=' . esc_attr($transaction_amount) .
			'&amp;qty=' . esc_attr($quantity) .
			'&amp;descrMerchant=' . esc_attr(rawurlencode($merchantDescription)) .
			'&amp;descrAffiliate=' . esc_attr(rawurlencode($affiliateDescription)) .
			'&amp;currency=' . esc_attr($currency_code) .
			'&amp;vc=' . esc_attr(rawurlencode($coupon_used)) .
			'&amp;product=' . esc_attr(rawurlencode(json_encode($productInformation))) .
			'&amp;data=&amp;descrAffiliate=&amp;event=sales" alt="" /></noscript>';

		return $returnString;
	}

	private function createProductObjectJson($order_item): ?stdClass {
		if( ! $order_item instanceof WC_Order_Item_Product) {
			return null;
		}
		$product_item = new stdClass();
		$product = $order_item->get_product();

		$product_item->name = $order_item->get_name();
		$product_item->ean = $product->get_attribute('ean');
		$product_item->sku = $product->get_sku();
		$product_item->id = $order_item->get_product_id();
		$product_item->currency = htmlentities($order_item->get_order()->get_currency(), ENT_QUOTES);
		$product_item->quantity = $order_item->get_quantity();
		$product_item->product_price_cents = $product->get_price() * 100;
		$product_item->product_price_ex_tax_cents = $order_item->get_subtotal() > 0 ? ($order_item->get_subtotal() * 100) / $product_item->quantity : 0;
		$product_item->product_tax_cents = $order_item->get_subtotal_tax() > 0 ? ($order_item->get_subtotal_tax() * 100) / $product_item->quantity : 0;

		$brand_terms = get_the_terms($order_item->get_product_id(), 'pa_brand');

		// If taxonomy can be fetched and there is only one brand name assigned, set the brand.
		if (!$brand_terms instanceof WP_Error && isset($brand_terms[0]) && count($brand_terms) === 1 && !empty($brand_terms[0]->name)) {
			$product_item->brand = $brand_terms[0]->name;
		}

		$cat_terms = get_the_terms($order_item->get_product_id(), 'product_cat');

		if (!$cat_terms instanceof WP_Error && !empty($cat_terms)) {
			//Sort the terms so that subcategories will be at the top
			$cat_terms = wp_list_sort($cat_terms, ['parent' => 'DESC', 'term_id' => 'ASC',]);
			$cat_path = get_term_parents_list($cat_terms[0]->term_id, $cat_terms[0]->taxonomy, [
				'separator' => ' > ',
				'link' => false,
				'format' => 'name',
			]);
			if (is_string($cat_path) && !empty($cat_path)) {
				$cat_path = preg_replace('/ \> *$/', '', $cat_path);
				$product_item->category = $cat_path;
			}
		}

		return $product_item;
	}

	private function detectConfiguredDirectLinkingUrl(string $directlinking_path, string $full_url, string $base_url): bool
	{
		//wc_get_logger()->debug( 'TTC: checking directlinking url with path: ' . $directlinking_path .', full url: ' . $full_url . ', and base url: ' . $base_url);
		if (empty(trim($directlinking_path))){
			wc_get_logger()->debug( 'TTC: empty directLinking path, DirectLinking disabled.');
			return false;
		}
		if (empty(trim($full_url)) || empty(trim($base_url)) || $full_url === $base_url){
			if ($full_url !== $base_url){
				wc_get_logger()->error('TTC: empty fullUrl or baseUrl provided.');
			}
			return false;
		}
		if (stripos($full_url, $base_url) === false){
			wc_get_logger()->error( 'TTC: baseUrl: "' . $base_url . '" should at be found in the fullUrl variable: "' . $full_url . '"');
			return false;
		}

		$query_string = parse_url($full_url, PHP_URL_QUERY);
		if (empty($query_string) || stripos($query_string, 'tt=') === false){
			return false;
		}

		$path_with_query = str_ireplace($base_url, '', $full_url);
		if (strpos($path_with_query, $directlinking_path) === false){
			wc_get_logger()->debug( 'TTC: no directLinkingPath provided for url: "' . $full_url . '", skipping Directlinking.');
			return false;
		}

		return true;
	}

	private function get_current_url() {
		$protocol = is_ssl() ? 'https://' : 'http://';
		$http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_url($_SERVER['HTTP_HOST'], ['http', 'https']) : '';
		$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_url($_SERVER['REQUEST_URI'], ['http', 'https']) : '';
		$current_url = $http_host . $request_uri;

		// HTTP_HOST can in some conditions contain the protocol.
		if(substr($current_url, 0, 4) !== 'http') {
			$current_url = $protocol . $current_url;
		}
		// It can also happen that WP baseurl protocol is wrongly configured, fix it.
		if(!str_contains($current_url, $protocol)){
			$current_url = str_ireplace(['https://', 'http://'], $protocol, $current_url);
		}

		return $current_url;
	}

	private function logMessage(string $log) {
		if (WP_DEBUG === true) {
			if (is_array($log) || is_object($log)) {
				error_log(print_r($log, true));
			} else {
				error_log($log);
			}
		}
	}
}
