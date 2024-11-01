<?php

require_once(ABSPATH . 'wp-admin/includes/file.php');
/**
 * Class for the product feed generation.
 *
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/includes
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 */
class Tradetracker_Connect_Feed
{
	/**
	 * Generate the product feed.
	 *
	 * @param bool $recursive Whether this method has been called recursively
	 * @return   void
	 * @since    2.0.0
	 */
	public function generate(bool $recursive = false): void
	{
		// If woocommerce is not active, then we don't need to do anything.
		if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			return;
		}

		$generator_options = get_option('tradetracker_connect_feed_generator', []);
		if (!$generator_options) {
			// No options set, return.
			return;
		}

		if (!$recursive && function_exists('set_time_limit')) {
			set_time_limit(3600);
		}

		if (!$recursive && function_exists('ini_set')) {
			ini_set('memory_limit', '512M');
		}

		$feed_file = $this->get_temp_file_path();
		$page = $generator_options['__batch_page'] ?? 1;

		$products = $this->get_raw_products(TRADETRACKER_PRODUCTS_PER_BATCH, $page);
		wc_get_logger()->debug('TTC: Feed: Receiving page #' . $page . ', Product count ' . count($products));
		try {
			[$xml, $products_xml] = $this->_get_xml($feed_file);
		} catch (Exception $exception) {
			// Could not parse XML, maybe some glitch, remove it and try again next time.
			wc_get_logger()->error('TTC: Feed generation issue: error: ' . $exception->getMessage());
			unlink($feed_file);
			$generator_options['__batch_page'] = 0;
			update_option('tradetracker_connect_feed_generator', $generator_options);
			return;
		}

		// If we're out of products on this page we end it here.
		if (!isset($products[0], $products[0]['id'])) {
			// Remove non-existing products from the feed.
			$this->_remove_feed_products($xml);
			$xml->saveXML($feed_file);
			if (!copy($this->get_temp_file_path(), $this->get_file_path())) {
				wc_get_logger()->debug('TTC: Feed: Error copying temp file');
			}

			// Update the feed generator options.
			unset($generator_options['__batch_page']);
			$generator_options['__generating'] = false;
			$generator_options['__generated'] = true;
			$generator_options['__generated_at'] = time();
			update_option('tradetracker_connect_feed_generator', $generator_options);
			wc_get_logger()->debug('TTC: Feed: XML generation completed');

			return;
		}

		foreach ($products as $product) {
			// If we don't have an ID we can't work with it.
			if (!isset($product['id'])) {
				continue;
			}

			// First we search for the product node by ID with xpath in the feed
			$searching_products = $products_xml->xpath(sprintf('//product[@id="%s"]', $product['id']));
			$formatted_product = $this->_get_formatted_product($product);
			$product_hash = sha1(json_encode($formatted_product));
			if (!empty($searching_products)) {
				// Remove multiple nodes with the same ID since we only want one.
				foreach ($searching_products as $searching_product) {
					// If the product node is already in the feed we check if the hash is the same.
					if ((string)$searching_product['hash'] == $product_hash) {
						// If it is we skip the $product.
						continue 2;
					} else {
						// If the hash is not the same we remove the node, so we can add a new one later.
						$removing_node = dom_import_simplexml($searching_product);
						$removing_node->parentNode->removeChild($removing_node);
					}
				}
			}

			// Let the frontend know we're generating the feed.
			$generator_options['__generating'] = true;

			$product_xml = $products_xml->addChild('product');
			$product_xml->addAttribute('id', $product['id']);
			$product_xml->addAttribute('hash', $product_hash);

			foreach ($formatted_product as $column => $product_field) {
				if (is_array($product_field)) {
					foreach ($product_field as $sub_field) {
						$this->_add_column($product_xml, $column, $sub_field);
					}
				} else {
					$this->_add_column($product_xml, $column, $product_field);
				}
			}
		}

		$generator_options['__batch_page'] = $page + 1;
		update_option('tradetracker_connect_feed_generator', $generator_options);
		wc_get_logger()->debug('TTC: Feed generation: Add count: ' . $products_xml->products->count());
		$xml->saveXML($feed_file);

		wc_get_logger()->debug('TTC: Feed: Page written: #' . $page);

		//Recursively call next page. Delay interval should be at least 10x time the product batch completes without it.
		usleep(TRADETRACKER_PRODUCTS_BATCH_DELAY_INTERVAL);
		$this->generate(true);
	}

	/**
	 * Get the file path for the feed.
	 *
	 * @param bool $url Whether to return the URL or the file path.
	 * @return string
	 * @since    2.0.0
	 */
	public function get_file_path(bool $url = false) : string
	{
		if ($url) {
			return content_url(sprintf('uploads/%s/tradetracker-connect-product-feed.xml', TRADETRACKER_CONNECT_DOMAIN));
		}

		$upload_dir = sprintf('%s/%s', wp_upload_dir()['basedir'], TRADETRACKER_CONNECT_DOMAIN);
		if (!file_exists($upload_dir)) {
			wp_mkdir_p($upload_dir);
		}

		return sprintf('%s/tradetracker-connect-product-feed.xml', $upload_dir);
	}

	/**
	 * Get the temporary file path for the feed.
	 *
	 * @return string
	 */
	public function get_temp_file_path() : string
	{
		$upload_dir = sprintf('%s/%s', wp_upload_dir()['basedir'], TRADETRACKER_CONNECT_DOMAIN);
		if (!file_exists($upload_dir)) {
			wp_mkdir_p($upload_dir);
		}

		return sprintf('%s/tradetracker-connect-product-feed-tmp.xml', $upload_dir);
	}

	/**
	 * Get the products from the internal WooCommerce API endpoint. If our dataset contains arrays,
	 * we need to flatten them and make sure they can be accessed using dot notation because we
	 * will display them in the column mapper using select fields.
	 *
	 * @param int $per_page
	 * @param int $page
	 * @return array
	 * @since    2.0.0
	 */
	public function get_raw_products(int $per_page = 4, int $page = 1) : array
	{
		$request = new \WP_REST_Request('GET', '/wc/v3/products');
		$request->set_param('order', 'desc');
		$request->set_param('per_page', $per_page);
		$request->set_param('page', $page);
		$request->set_param('status', 'publish');
		$response = rest_do_request($request);
		$api_products = $response->get_data();
		$products = [];
		if (!empty($api_products)) {
			foreach ($api_products as $api_product) {
				if (!is_array($api_product) && !is_object($api_product)) {
					if (defined('WP_DEBUG') && WP_DEBUG) {
						error_log('Invalid product type: ' . gettype($api_product). ': ' . (string) $api_product);
					}
					continue;
				}
				$product = [];
				foreach ($api_product as $field => $value) {
					if (is_array($value)) {
						// Recursively flatten the array and key with dot notation
						// https://stackoverflow.com/a/10424516
						$ritit = new RecursiveIteratorIterator(new RecursiveArrayIterator($value));
						foreach ($ritit as $leaf_value) {
							$keys = [$field];
							foreach (range(0, $ritit->getDepth()) as $depth) {
								$keys[] = $ritit->getSubIterator($depth)->key();
							}
							$product[join('.', $keys)] = $leaf_value;
						}
					} else {
						$product[$field] = $value;
					}
				}
				$products[] = $product;
			}
		}

		return $products;
	}

	/**
	 * Get the products for the feed, excluding the keys that are not mapped to the feed.
	 *
	 * @param array $product
	 * @return array
	 * @since    2.0.0
	 */
	protected function _get_formatted_product(array $product) : array
	{
		$feed_options = get_option('tradetracker_connect_feed_options', []);
		if (!$feed_options) {
			return [];
		}
		$formatted_product = [];
		foreach ($feed_options as $column_name => $mapped_value) {
			// if $columnName starts with '__' it is a meta key, so we continue
			if (strpos($column_name, '__') === 0) {
				continue;
			}

			if (strpos($mapped_value, '*') !== false) {
				// If it does, we want to see if any of the array keys of $productKeys matches
				// $mappedValue using regex, swapping out the star for decimal
				$regex_key = str_replace('*', '\\d+', $mapped_value);
				$regex_key = str_replace('.', '\\.', $regex_key);
				$matches = preg_grep('/^' . $regex_key . '$/', array_keys($product));
				if ($matches) {
					$formatted_product[$column_name] = [];
					foreach ($matches as $match) {
						$formatted_product[$column_name][] = $product[$match];
					}
				}
			} else {
				if (in_array($mapped_value, array_keys($product), true)) {
					// If it doesn't contain a star then $formattedProduct can accept the key value as is
					$formatted_product[$column_name] = [$product[$mapped_value]];
				}
			}
		}

		return $formatted_product;
	}

	/**
	 * Function that removes html tags, control characters and multiple whitespaces from a string.
	 *
	 * @param string $string
	 * @return string
	 * @since    2.0.0
	 */
	protected function _sanitize_text(string $string) : string
	{
		$string = preg_replace('/<[^>]*>/', ' ', $string);
		$string = str_replace("\r", '', $string);    // --- replace with empty space
		$string = str_replace("\n", ' ', $string);   // --- replace with space
		$string = str_replace("\t", ' ', $string);   // --- replace with space

		return trim(preg_replace('/ {2,}/', ' ', $string));
	}

	/**
	 * Remove products that should not be in the feed if a feed file exists.
	 *
	 * @param Tradetracker_Connect_XML $xml
	 * @return void
	 * @since 2.0.0
	 */
	protected function _remove_feed_products(Tradetracker_Connect_XML &$xml): void
	{
		// As an extra measure of integrity we remove the products
		// that should not be in the feed. We gather all product ids, convert them to an xpath query
		// and remove the products that should not be in the feed.
		$product_ids_query = new WP_Query( array(
			'post_type' => 'product',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'fields' => 'ids',
		) );
		// Get all product ids
		$all_product_ids = array_map(function($id) {
			return (int) $id;
		}, $product_ids_query->posts);
		// xml xpath for every product node that has an id that doesn't exist in the $allProductIds array
		$xpath_query = sprintf('//product[not(@id="%s")]', implode('") and not(@id="', $all_product_ids));
		// Remove the nodes that are not in the feed
		$deleting_products = $xml->xpath($xpath_query);

		wc_get_logger()->debug('TTC: Feed: Remove products query ' . $xpath_query.', products to delete from feed: ' . count($deleting_products));
		foreach ($deleting_products as $deleting_product) {
			$removing_node = dom_import_simplexml($deleting_product);
			$removing_node->parentNode->removeChild($removing_node);
        }
	}

	/**
	 * Initialize or load the xml feed.
	 *
	 * @param string $feed_file
	 * @return array
	 * @since 2.0.0
	 * @throws Exception
	 */
	protected function _get_xml(string $feed_file) : array
	{
		// Initialize the XML feed by either creating a new one or loading an existing one.
		if (file_exists($feed_file)) {
			$xml = simplexml_load_string(file_get_contents($feed_file), Tradetracker_Connect_XML::class);
			if ($xml === false) {
				wc_get_logger()->error('TTC: feed generation: feed file' . $feed_file . ' could not be parsed, known errors below:');
				foreach(libxml_get_errors() as $error) {
					wc_get_logger()->error('TTC: parsing error: ' . $error->message);
				}
				throw new Exception($feed_file . ' could not be parsed');
			}
			$xml->attributes()->timestamp = time();
			$xml->attributes()->version = TRADETRACKER_CONNECT_VERSION;
			$products_xml = $xml->products;
		} else {
			$xml = new Tradetracker_Connect_XML('<productFeed></productFeed>');
			$xml->addAttribute('version', TRADETRACKER_CONNECT_VERSION);
			$xml->addAttribute('timestamp', time());
			$products_xml = $xml->addChild('products');
		}
		return [$xml, $products_xml];
	}

	/**
	 * Whether the given value contains html we add a column with or without CDATA.
	 *
	 * @param Tradetracker_Connect_XML $node
	 * @param string $column
	 * @param $value
	 */
	protected function _add_column(Tradetracker_Connect_XML &$node, string $column, $value): void
	{
		if(!$value) {
			$node->addChild($column, false);
			return;
		}
		if ($this->_sanitize_text($value) !== $value) {
			$node->addChildWithCDATA($column, trim($value));
		} else {
			$node->addChild($column, trim($value));
		}
	}
}