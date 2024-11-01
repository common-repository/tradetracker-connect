<?php

/**
 * Class for easy communication with the SOAP client.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/includes
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 */
class Tradetracker_Connect_Soap
{
	/**
	 * The SOAP client that is used for communication with the Tradetracker WSDL.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      SoapClient $client SOAP client wrapper.
	 */
	private $client = null;

	/**
	 * Attempt to authenticate with the saved options to
	 * the merchant web services.
	 *
	 * @return bool
	 */
	public function authenticate() : bool
	{
		try {
			$web_service_options = get_option('tradetracker_connect_webservice_options');
			$this->client = new SoapClient(TRADETRACKER_CONNECT_MERCHANT_WSDL, ['compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP]);
			$this->client->authenticate($web_service_options['customer_id'] ?? null, $web_service_options['passphrase'] ?? null, TRADETRACKER_CONNECT_SANDBOX, 'en_GB', TRADETRACKER_CONNECT_DEMO);

			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Get the conversion transactions from the merchant web services.
	 * Along with the filters for the storefront order ID's we can
	 * search for TradeTracker transactions.
	 *
	 * @param array $filters
	 * @return array
	 */
	public function getConversionTransactions(array $filters = []) : array
	{
		$campaign_options = get_option('tradetracker_connect_campaign_options');
		if ($this->authenticate() === false || !intval($campaign_options['cid'] ?? '0')) {
			return [];
		}

		$campaign_id = (TRADETRACKER_CONNECT_DEMO === true || !$campaign_options['cid']) ? $this->client->getCampaigns()[0]->ID : $campaign_options['cid'];

		return $this->client->getConversionTransactions($campaign_id, $filters);
	}

	/**
	 * Hooking in to the "woocommerce_update_order" action. We add
	 * a WP cronjob with a delay of 24 hours per-order to assess the
	 * transactions in case of modifications to the order statuses.
	 *
	 * @param int $order_id
	 */
	public function tradetracker_connect_on_update_order(int $order_id) : void
	{
		/**
		 * To delete cronjobs we need the timestamp + parameter, meaning we can save the following struct:
		 * [$orderId => $timestamp, $orderId => $timestamp, ...]
		 */

		$order = wc_get_order($order_id);
		$scheduled_events = get_option('tradetracker_connect_scheduled_events', []);

		// If an event has been scheduled for this $orderId
		if (isset($scheduled_events[$order_id])) {
			wp_unschedule_event($scheduled_events[$order_id], 'tradetracker_connect_order_cron_hook', [$order_id]);
			unset($scheduled_events[$order_id]);
		}

		$timestamp = time() + TRADETRACKER_CONNECT_ORDER_CRON_DELAY_SECONDS;
		wp_schedule_single_event($timestamp, 'tradetracker_connect_order_cron_hook', [$order_id]);
		$scheduled_events[$order_id] = $timestamp;
		update_option('tradetracker_connect_scheduled_events', $scheduled_events);
	}

	/**
	 * CRON functionality to be called 24 hours after an order has been modified.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function cron_update_order(int $order_id) : void
	{
		$order = new WC_Order($order_id);

		// Array of WC order statuses we consider rejected
		$rejecting_statuses = [
			'failed' => 'payment_not_received',
			'cancelled' => 'order_canceled',
			'refunded' => 'returned_goods',
		];

		if ($this->authenticate() === false ) {
			wc_get_logger()->error( 'TTC: cron_update_order, unable to connect to WebService, please check credentials and service status.');
			return;
		}
		if (in_array($order->get_status(), array_keys($rejecting_statuses)) === false) {
			// turn this comment off when debugging. Not active by default since it will pollute the system.
			// wc_get_logger()->notice( 'TTC: cron_update_order, there is no mapping for order status: ' . $order->get_status());
			$this->unregisterCronEvent($order_id);
			return;
		}

		$campaign_options = get_option('tradetracker_connect_campaign_options');
		$filters = [
			'transactionType' => 'sale',
			'transactionStatus' => 'pending',
			'registrationDateFrom' => $order->get_date_created(),
		];
		$line_items = $order->get_items();

		$transactions = $this->getConversionTransactions(array_merge($filters, ['characteristic' => $order_id]));
		if (!$transactions) {
			// If we can't find any transactions for this order ID we try to suffix the
			// numeric index of the line item.
			$suffixed_transactions = [];
			for ($i = 0; $i < sizeof($order->get_items()); $i++) {
				$characteristic = sprintf('%1d_%02d', $order_id, ($i + 1));
				$transaction = $this->getConversionTransactions(array_merge($filters, ['characteristic' => $characteristic]));
				$suffixed_transactions[] = $transaction[0];
			}
			$transactions = $suffixed_transactions;
		}

		if ($transactions) {
			// First we try to search for the order ID itself without suffixes
			foreach ($transactions as $transaction) {
				$rejection_reason = $rejecting_statuses[$order->get_status()];

				// Validate that the order was created by wooCommerce in the first place.
				if ( ! str_contains(strtolower($transaction->description), 'line item')) {
					wc_get_logger()->warning( 'TTC: cron_update_order > transaction descriptions does not contain line item. Description: ' . $transaction->description);
					continue;
				}

				try {
					wc_get_logger()->notice( 'TTC: cron_update_order > rejecting order with tid: ' . $transaction->ID);
					$this->client->assessConversionTransaction($transaction->ID, 'rejected', $rejection_reason);
					$updated_transaction = $this->client->getConversionTransactions($campaign_options['cid'], [
						'ID' => $transaction->ID,
					]);
					$this->unregisterCronEvent($order_id);
					if ($updated_transaction) {
						$order->add_order_note(
							sprintf(
								__('Assessed transaction ID# %s in TradeTracker.', 'tradetracker-connect'),
								$transaction->ID
							),
							false,
							false
						);
					} else {
						wc_get_logger()->notice( 'TTC: cron_update_order, no updated_transaction was returned');
					}
				} catch (Exception $exception){
					wc_get_logger()->error( 'TTC: Unable to assess Conversion Transaction via webservice, error message: ' . $exception->getMessage());
				}
			}
		} else {
			wc_get_logger()->error( 'TTC: Unable to find transactions for : ' . $order_id);
		}
	}

	/**
	 * @param int $order_id
	 * @return void
	 */
	public function unregisterCronEvent(int $order_id): void
	{
		$scheduled_events = get_option('tradetracker_connect_scheduled_events', []);
		wp_unschedule_event($scheduled_events[$order_id], 'tradetracker_connect_order_cron_hook', [$order_id]);
		unset($scheduled_events[$order_id]);
		update_option('tradetracker_connect_scheduled_events', $scheduled_events);
	}
}