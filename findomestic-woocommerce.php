<?php
/**
 *
 * @link              https://alessandrodecristofaro.it
 * @since             1.0.3
 * @package           Findomestic_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Findomestic for WooCommerce
 * Plugin URI:        https://alessandrodecristofaro.it
 * Description:       Vendi prodotti a rate sul tuo shop usando la finanziaria Findomestic.
 * Version:           1.0.3
 * Author:            Alessandro De Cristofaro
 * Author URI:        https://alessandrodecristofaro.it
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       findomestic-woocommerce
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;


add_action( 'plugins_loaded', 'woocommerce_findomestic_init', 0 );

function woocommerce_findomestic_init() {
	if ( ! class_exists( 'WC_Payment_Gateway') ){
        add_action('admin_notices', function() {
            $message = __('Per utilizzare "Findomestic for WooCommerce" è necessario <a href="' . admin_url(  'plugin-install.php?s=woocommerce&tab=search&type=term' ) . '">"WooCommerce"</a>', 'findomestic-woocommerce');
            echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
        });
		return;
	}
	define( 'WC_GATEWAY_FINDOMESTIC_VERSION', '1.0.3');
	define( 'WC_FINDOMESTIC_URL', plugin_dir_url( __FILE__ ));
	require_once( plugin_basename( 'include/class-wc-gateway-findomestic.php' ) );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_findomestic_add_gateway' );
	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_findomestic_plugin_links' );
	add_action('rest_api_init',	'findomestic_register_routes' );
}


function woocommerce_findomestic_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Findomestic_Gateway';
	return $methods;
}

function woocommerce_findomestic_plugin_links( $links ) {
	$settings_url = add_query_arg(
		[
			'page' => 'wc-settings',
			'tab' => 'checkout',
			'section' => 'findomestic',
		],
		admin_url( 'admin.php' )
	);

	$plugin_links = [
		'<a href="' . esc_url( $settings_url ) . '">' . __( 'Impostazioni', 'findomestic-woocommerce' ) . '</a>',
		'<a href="' . esc_url( 'https://alessandrodecristofaro.it/docs-findomestic-gateway?ref=' . admin_url() ) . '">' . __( 'Documentazione', 'findomestic-woocommerce' ) . '</a>'
	];

	return array_merge( $plugin_links, $links );
}

function findomestic_register_routes(){
	register_rest_route('findomestic', '/payment', [
			'methods' => 'GET',
			'callback' => 'check_esito'
		]
	);
}

function check_esito($request) {
	$parameters = $request->get_query_params();
	$esito = $parameters['esito'];
	$cartId = $parameters['cartId'];
	$numAuth = $parameters['numAuth'];
	$order = new WC_Order($cartId);
	if ($esito == 'AUTHORIZED' && $order )
	{
		$order->add_order_note(__('Preaccettazione approvata da Findomestic, la pratica proseguirà sul portale. Codice autorizzativo: ' . $numAuth, 'findomestic-woocommerce'));
		$order->update_status('processing');
		wp_redirect($order->get_view_order_url());
		exit;
	}else{
		echo 'La tua Pratica è stata Rifiutata dal Sistema Telematico Findomestic';
		$order->update_status('delete');
		$order->add_order_note(__('Pratica Rifiutata dalla Preaccettazione automatica di Findomestic.', 'findomestic-woocommerce'));
		wp_redirect($order->get_view_order_url());
		exit;
	}
}
