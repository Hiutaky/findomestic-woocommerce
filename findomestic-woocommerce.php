<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://alessandrodecristofaro.it
 * @since             1.0.2
 * @package           Findomestic_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Findomestic for WooCommerce
 * Plugin URI:        https://alessandrodecristofaro.it
 * Description:       Integrazione WooCommerce Con Sistema Findomestic Per Richiedere Finanziamenti Telematici
 * Version:           1.0.2
 * Author:            Alessandro De Cristofaro
 * Author URI:        https://alessandrodecristofaro.it
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       findomestic-woocommerce
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

function woocommerce_findomestic_init() {
	if ( ! class_exists( 'WC_Payment_Gateway') ){
		return;
	}

	define( 'WC_GATEWAY_FINDOMESTIC_VERSION', '1.0.2');

	require_once( plugin_basename( 'include/class-wc-gateway-findomestic.php' ) );
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_findomestic_add_gateway' );
}

add_action( 'plugins_loaded', 'woocommerce_findomestic_init', 0 );


function woocommerce_findomestic_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Findomestic_Gateway';
	return $methods;
}

function woocommerce_findomestic( $links ) {
	$settings_url = add_query_arg(
		array(
			'page' => 'wc-settings',
			'tab' => 'checkout',
			'section' => 'findomestic',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-gateway-findomestic' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_link_' . plugin_basename( __FILE__ ), 'woocommerce_findomestic_plugin_links');

add_action('rest_api_init',
		'findo_register_routes'
);

function findo_register_routes()
{
		register_rest_route('findomestic', '/payment', array(
				'methods' => 'GET',
				'callback' => 'check_esito'
		));
}

function check_esito($request)
{
		$parameters = $request->get_query_params();
		$esito = $parameters['esito'];
		$cartId = $parameters['cartId'];
		$numAuth = $parameters['numAuth'];
		$order = new WC_Order($cartId);
		if ($esito == 'AUTHORIZED' && $order)
		{
				$order->add_order_note(__('PreAccettazione Findomestic Riuscita > Auth: ' . $numAuth, 'findomestic'));
				$order->update_status('processing');
				wp_redirect($order->get_view_order_url());
				exit;
		}
		else
		{
				echo 'La tua Pratica è stata Rifiutata dal Sistema Telematico Findomestic';
				$order->update_status('delete');
				$order->add_order_note(__('Pratica Rifiutata dal Sistema Telematico di Findomestic', 'findomestic'));
				wp_redirect($order->get_view_order_url());
				exit;
		}
		//var_dump($request);

}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_plugin_page_settings_link' );

function add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'admin.php?page=wc-settings&tab=checkout&section=findomestic' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}
