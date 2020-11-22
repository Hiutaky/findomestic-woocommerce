<?php
class WC_Gateway_Findomestic_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id = 'findomestic';
        $this->icon = 'https://docfunnel.app/wp-content/uploads/2020/03/findomestic-banca-logo-A-scelta.png';
        $this->has_fields = false;
        $this->method_title = _x('Findomestic', 'Pagamento Rateale', $this->id);
        $this->method_description = __('Ti permette di ricevere pagamenti rateali tramite il Gateway Findomestic', $this->id);
        $this->callbackUrl = get_home_url() . '/wp-json/findomestic/payment';
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->codice_fiscale = $this->get_option('codice_fiscale');

        if (!$this->is_plugin_active('woocommerce/woocommerce.php') || ('findomestic-woocommerce.php' === basename(__FILE__)))
        {
            return;
        }
		

        // Actions.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options'
        ));

        add_action('woocommerce_thankyou_' . $this->id, array(
            $this,
            'thankyou_page'
        ));


        //Rimuove Findomestic se Totale Carrello < PRF_Value ( importo minimo finanziabile )
        add_filter('woocommerce_available_payment_gateways', [
          $this,
          'remove_findomestic_on_amount'
        ]);

        if($this->codice_fiscale == 'yes'){
          add_action('woocommerce_before_order_notes', array(
              $this,
              'codice_fiscale_checkout_field'
          ));
          add_action('woocommerce_checkout_create_order', array(
              $this,
              'custom_checkout_field_update_order_meta'
          ));
          add_action('woocommerce_checkout_process', array(
              $this,
              'codice_fiscale_checkout_field_process'
          ));
        }

        // Customer Emails.
        //
        add_action('woocommerce_email_before_order_table', array(
            $this,
            'email_instructions'
        ) , 10, 3);

    }

    public function remove_findomestic_on_amount($available_gateways){

      if( !is_admin() ){
        $order_total = WC()->cart->total;
        if($order_total < $this->get_option('prf_value')){
          foreach( $available_gateways as $gateways_id => $gateways ){
            if( $gateways_id == 'findomestic') {
                unset($available_gateways[$gateways_id]);
            }
        }
      }
    }

      return $available_gateways;
    }

    public function is_plugin_active($plugin)
    {
        return (function_exists('is_plugin_active') ? is_plugin_active($plugin) : (in_array($plugin, apply_filters('active_plugins', ( array )get_option('active_plugins', array()))) || (is_multisite() && array_key_exists($plugin, ( array )get_site_option('active_sitewide_plugins', array())))));
    }

    public function codice_fiscale_checkout_field_process()
    {
        if (isset($_POST['payment_method']))
        {
            if ($_POST['payment_method'] == $this->id)
            {
                if (!$_POST['codice_fiscale']) wc_add_notice(__('Inserisci il Codice Fiscale per procedere al pagamento rateale.') , 'error');

            }
        }
    }

    /**
     * Add the field to the checkout
     */

    public function custom_checkout_field_update_order_meta($order)
    {
        if (isset($_POST['codice_fiscale']))
        {
            // Save custom checkout field value
            $order->update_meta_data('_codice_fiscale', esc_attr($_POST['codice_fiscale']));

            // Save the custom checkout field value as user meta data
            if ($order->get_customer_id())
                update_user_meta($order->get_customer_id() , 'codice_fiscale', esc_attr($_POST['codice_fiscale']));
        }
    }

    public function codice_fiscale_checkout_field($checkout)
    {

        echo '<div id="custom-codice-fiscale"><h2>' . __('Codice Fiscale') . '</h2>';

        woocommerce_form_field('codice_fiscale', array(
            'type' => 'text',
            'class' => array(
                'form-row-wide'
            ) ,
            'label' => __('Codice Fiscale') ,
            'placeholder' => __('Inserisci il tuo Codice Fiscale') ,
            'required' => true,
        ) , $checkout->get_value('codice_fiscale'));

        echo '</div>';

    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Attiva/Disattiva', $this->id) ,
                'type' => 'checkbox',
                'label' => __('Attiva Findomestic', $this->id) ,
                'default' => 'yes'
            ) ,
            'title' => array(
                'title' => __('Titolo', $this->id) ,
                'type' => 'text',
                'description' => __('Il testo che vedono gli utenti nel carrello.', $this->id) ,
                'default' => __('Findomestic Rateale', $this->id) ,
                'desc_tip' => true,
            ) ,
            'description' => array(
                'title' => __('Messaggio per gli Utenti', $this->id) ,
                'type' => 'textarea',
                'default' => 'Effettua comodamente i tuoi Acquisti utilizzando il Pagamento Rateale di Findomestic.'
            ) ,
            'tvei' => array(
                'title' => __('Codice Venditore TVEI', $this->id) ,
                'type' => 'text',
                'description' => __('Il codice Venditore fornito da Findomestic.', $this->id) ,
                'desc_tip' => true,
            ) ,
            'prf_value' => array(
                'title' => __('Valore primo PRF', $this->id) ,
                'type' => 'text',
                'description' => __('Inserisci il valore minimo (in Euro) del primo PRF (es. 200)', $this->id) ,
                'desc_tip' => true,
            ) ,
            'prf' => array(
                'title' => __('Codice PRF', $this->id) ,
                'type' => 'text',
                'description' => __('Il codice PRF fornito da Findomestic.', $this->id) ,
                'desc_tip' => true,
            ) ,
            'prf_2_status' => array(
              'title' => __('Secondo PRF', $this->id),
              'type' => 'checkbox',
              'description' => __('Attiva solo se hai a disposizione un secondo PRF', $this->id),
              'desc_tip' => true,
            ),
            'prf_value_2' => array(
                'title' => __('Valore Secondo PRF', $this->id) ,
                'type' => 'text',
                'description' => __('Inserisci il valore minimo (in Euro) del secondo PRF (es. 500)', $this->id) ,
                'desc_tip' => true,
                'custom_attributes' => $this->get_option('prf_2_status') == 'no' ? array('readonly' => 'readonly') : null,
            ) ,
            'prf_2' => array(
                'title' => __('Codice PRF 2', $this->id) ,
                'type' => 'text',
                'description' => __('Il codice PRF 2 fornito da Findomestic.', $this->id) ,
                'desc_tip' => true,
                'custom_attributes' => $this->get_option('prf_2_status') == 'no' ? array('readonly' => 'readonly') : null,
            ) ,
            'url-cliente' => array(
                'title' => __('Identificativo Cliente', $this->id) ,
                'type' => 'text',
                'description' => __('L\'Identificativo Cliente fornito da Findomestic ( es. docfunnel ).', $this->id) ,
                'desc_tip' => true,
            ) ,
            'instructions' => array(
                'title' => __('Istruzioni', $this->id) ,
                'type' => 'textarea',
                'description' => __('Le Istruzioni da Aggiungere nella Pagina di Conferma e nelle Email.', $this->id) ,
                'default' => 'Abbiamo Ricevuto il Tuo Ordine, ma la richiesta di Pagamento è in attesa. </ br> Completa la Pratica di <b>richiesta di Finanziamento di Findomestic</b> per concludere l\'\ordine con successo',
                'desc_tip' => true,
            ) ,
            'codice_fiscale' => array(
                'title' => __('Codice Fiscale', $this->id) ,
                'type' => 'checkbox',
                'description' => __('Crea un Campo Obbligatorio: Codice Fiscale al momento del Checkout.', $this->id) ,
                'desc_tip' => true,
            ) ,
            'bottone' => array(
                'title' => __('Testo Bottone', $this->id) ,
                'type' => 'text',
                'description' => __('Testo per Avvio della Procedura di Richiesta Finanziamento', $this->id) ,
                'default' => 'Avvia Pratica Findomestic',
                'desc_tip' => true,
            )
        );
    }

    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        if ($order->get_total() > 0)
        {
            // Mark as on-hold (we're awaiting the cheque).
            $order->update_status('on-hold', _x('In Sospeso', $this->id));
            $order->add_order_note('Il Cliente ha Completato l\'Ordine ma non la Richiesta di Finanziamento e Pre-Accettazione.');

        }
        else
        {
            $order->payment_complete();
        }

        // Remove cart.
        WC()
            ->cart
            ->empty_cart();

        // Return thankyou redirect.
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order) ,
        );
    }

    public function thankyou_page($order_id)
    {
        if ($this->instructions)
        {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
        echo wp_kses_post(wpautop(wptexturize($this->findomestic_details($order_id))));
    }

    public function findomestic_details($order_id)
    {

        $order = new WC_Order($order_id);
        //$prf = $this->get_option('prf');
        $tvei = $this->get_option('tvei');
        $url_cli = $this->get_option('url-cliente');
        $total = $order->get_total();
        $prf1_value = $this->get_option('prf_value_1');
        $prf2_value = $this->get_option('prf_value_2');

        if($this->get_option('prf_2_status') != 'no'){
          if(isset($prf1_value) && isset($prf2_value)){
            if ($total < $prf2_value && $prf2_value != 0 && $prf2_value > $prf1_value)
            {
                $prf = $this->get_option('prf');
            }
            else
            {
                $prf = $this->get_option('prf_2');
            }
          }else{
            $prf = $this->get_option('prf');
          }
        }else{
          $prf = $this->get_option('prf');
        }

?>
			<form action="https://secure.findomestic.it/clienti/pmcrs/<?php echo $url_cli ?>/mcommerce/pages/simulatore.html" method="post" target="_blank">
			<input name="tvei" value="<?php echo $tvei ?>" />
			<input name="prf" value="<?php echo $prf ?>" />
			<input name="cartId" value="<?php echo $order_id ?>" />
			<input name="importo" value="<?php echo str_replace('.', '', $total) ?>" />
			<input name="nomeCliente" value="<?php echo $order->get_billing_first_name() ?>" />
			<input name="cognomeCliente" value="<?php echo $order->get_billing_last_name() ?>" />
			<input name="codiceFiscaleCliente" value="<?php echo $order->get_meta('_codice_fiscale') ?>" />
			<input name="emailCliente" value="<?php echo $order->get_billing_email() ?>" />
			<input name="labelRedirect" value="Torna" />
			<input name="urlRedirect" value="<?php echo $order->get_view_order_url() ?>" />
			<input name="callBackUrl" value="<?php echo $this->callbackUrl ?>" />
			<input type="submit" id="submit" value="<?php echo $this->get_option('bottone') ?>" />
			</form>
			<style>
			form > input {
				 display: none;
			}

			#submit {
				display: flex !important;
				width: 100%;
				text-align: center;
				color: #0D5C63 !important;
				border: 5px solid !important;
				font-weight: 700!important;
        background: #fff;
			}
			</style>
			<?php
    }

    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method())
        {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . '</br><a href="' . $order->get_view_order_url() . '">Clicca qui per Completare la Richiesta di Finanziamento</a>' . PHP_EOL);
        }
    }

}
