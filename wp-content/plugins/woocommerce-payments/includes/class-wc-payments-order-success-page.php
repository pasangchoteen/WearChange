<?php
/**
 * Class WC_Payments_Order_Success_Page
 *
 * @package WooCommerce\Payments
 */

use WCPay\Duplicate_Payment_Prevention_Service;

/**
 * Class handling order success page.
 */
class WC_Payments_Order_Success_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_thankyou', [ $this, 'register_payment_method_override' ] );
		add_action( 'woocommerce_order_details_before_order_table', [ $this, 'unregister_payment_method_override' ] );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'add_notice_previous_paid_order' ], 11 );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'add_notice_previous_successful_intent' ], 11 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Register the hook to override the payment method name on the order received page.
	 */
	public function register_payment_method_override() {
		// Override the payment method title on the order received page.
		add_filter( 'woocommerce_order_get_payment_method_title', [ $this, 'show_woopay_payment_method_name' ], 10, 2 );
	}

	/**
	 * Remove the hook to override the payment method name on the order received page before the order summary.
	 */
	public function unregister_payment_method_override() {
		remove_filter( 'woocommerce_order_get_payment_method_title', [ $this, 'show_woopay_payment_method_name' ], 10 );
	}

	/**
	 * Add the WooPay logo and the last 4 digits of the card used to the payment method name
	 * on the order received page.
	 *
	 * @param string            $payment_method_title the default payment method title.
	 * @param WC_Abstract_Order $abstract_order the order being shown.
	 */
	public function show_woopay_payment_method_name( $payment_method_title, $abstract_order ) {

		// Only change the payment method title on the order received page.
		if ( ! is_order_received_page() ) {
			return $payment_method_title;
		}

		$order_id = $abstract_order->get_id();
		$order    = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( 'is_woopay' ) ) {
			return $payment_method_title;
		}

		ob_start();
		?>
		<div class="wc-payment-gateway-method-name-woopay-wrapper">
			<img alt="WooPay" src="<?php echo esc_url_raw( plugins_url( 'assets/images/woopay.svg', WCPAY_PLUGIN_FILE ) ); ?>">
			<?php
			if ( $order->get_meta( 'last4' ) ) {
				echo esc_html_e( 'Card ending in', 'woocommerce-payments' ) . ' ';
				echo esc_html( $order->get_meta( 'last4' ) );
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add the notice to the thank you page in case a recent order with the same content has already paid.
	 *
	 * @param string $text  the default thank you text.
	 *
	 * @return string
	 */
	public function add_notice_previous_paid_order( $text ) {
		if ( isset( $_GET[ Duplicate_Payment_Prevention_Service::FLAG_PREVIOUS_ORDER_PAID ] ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			$text .= $this->format_addtional_thankyou_order_received_text(
				__( 'We detected and prevented an attempt to pay for a duplicate order. If this was a mistake and you wish to try again, please create a new order.', 'woocommerce-payments' )
			);
		}

		return $text;
	}

	/**
	 * Add the notice to the thank you page in case an existing intention was successful for the order.
	 *
	 * @param string $text  the default thank you text.
	 *
	 * @return string
	 */
	public function add_notice_previous_successful_intent( $text ) {
		if ( isset( $_GET[ Duplicate_Payment_Prevention_Service::FLAG_PREVIOUS_SUCCESSFUL_INTENT ] ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			$text .= $this->format_addtional_thankyou_order_received_text(
				__( 'We prevented multiple payments for the same order. If this was a mistake and you wish to try again, please create a new order.', 'woocommerce-payments' )
			);
		}

		return $text;
	}


	/**
	 * Formats the additional text to be displayed on the thank you page, with the side effect
	 * as a workaround for an issue in Woo core 8.1.x and 8.2.x.
	 *
	 * @param string $additional_text
	 *
	 * @return string Formatted text.
	 */
	private function format_addtional_thankyou_order_received_text( string $additional_text ): string {
		/**
		 * This condition is a workaround for Woo core 8.1.x and 8.2.x as it formatted the filtered text,
		 * while it should format the original text only.
		 *
		 * It's safe to remove this conditional when WooPayments requires Woo core 8.3.x or higher.
		 *
		 * @see https://github.com/woocommerce/woocommerce/pull/39758 Introduce the issue since 8.1.0.
		 * @see https://github.com/woocommerce/woocommerce/pull/40353 Fix the issue since 8.3.0.
		 */
		if( version_compare( WC_VERSION, '8.0', '>' )
			&& version_compare( WC_VERSION, '8.3', '<' )
		) {
			echo "
				<script type='text/javascript'>
					document.querySelector('.woocommerce-thankyou-order-received')?.classList?.add('woocommerce-info');
				</script>
			";

			return ' ' . $additional_text;
		}

		return sprintf( '<div class="woocommerce-info">%s</div>', $additional_text );
	}

	/**
	 * Enqueue style to the order success page
	 */
	public function enqueue_scripts() {
		if ( ! is_order_received_page() ) {
			return;
		}

		WC_Payments_Utils::enqueue_style(
			'wcpay-success-css',
			plugins_url( 'assets/css/success.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'assets/css/success.css' ),
			'all',
		);
	}
}
