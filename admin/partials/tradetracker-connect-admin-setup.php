<?php
/**
 * Provide an admin area view for the plugin
 *
 * This file is used to mark-up the admin-facing aspects of the plugin.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 *
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/admin/partials
 */

	if (!defined('ABSPATH')) {
		exit;
	}
?>
<div class="wrap" id="tradetracker-connect-setup-page">
	<form action="options.php" method="POST">
		<div>
			<a class="logo-img" href="https://tradetracker.com/" target="_blank"> <img src="<?php echo esc_url(TRADETRACKER_CONNECT_URL . 'assets/ttlogo.svg'); ?>" alt="TradeTracker Logo"> </a>
			<h1 class="wp-heading-inline">
				<?php esc_html_e('TradeTracker Connect', 'tradetracker-connect'); ?>
				|
				<?php esc_html_e('Merchant setup', 'tradetracker-connect'); ?>
			</h1>
			<?php settings_errors(); ?>
		</div>
		<?php
		settings_fields($this->tradetracker_connect . '-setup');
		do_settings_sections($this->tradetracker_connect . '-setup');

		$button_html = get_submit_button();

		echo wp_kses($button_html, array(
			'p' => array(
				'class' => array(),
			),
			'input' => array(
				'id' => array(),
				'class' => array(),
				'type' => array(),
				'name' => array(),
				'value' => array(),
			),
		));

		wp_nonce_field('update_setup', TRADETRACKER_CONNECT_NONCE);
		?>
	</form>
</div>
