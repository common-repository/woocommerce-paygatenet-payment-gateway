<?php
/*
Plugin Name: WooCommerce PayGate.net Payment Gateway
Plugin URI: http://intelligent.it.asia
Description: Extends WooCommerce with the Korean PayGate.net payment gateway.
Version: 1.3
Author: intelligent-it.asia
Author URI: http://intelligent.it.asia
 
Copyright: 2013 Intelligent IT.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
 


// check that woocommerce is an active plugin before initializing paygate payment gateway
if ( in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins' )  ) ) 
{
	add_action('plugins_loaded', 'woocommerce_gateway_paygate_init', 0);	
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_paygate_gateway' );
    
    // localization
    load_plugin_textdomain( 'PayGateKorea', false, plugin_basename( dirname(__FILE__) ) . '/languages' );  
	//add_filter('post_type_link', 'qtrans_convertURL'); 
}


function woocommerce_gateway_paygate_init() {
 
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
 
	class WC_Gateway_PayGate extends WC_Payment_Gateway {
		public function __construct(){
			global $woocommerce;
			$this -> id = 'paygate';
			$this -> method_title = 'PayGate';
			$this -> has_fields = false;
			$this -> icon = apply_filters( 'woocommerce_paygate_checkout_icon',plugins_url().'/paygate-gateway/images/PayGate.png');
			$this -> init_form_fields();
			$this -> init_settings();

			//$this->  enabled = $this -> settings['enabled'];
			$this -> title = $this -> settings['title'];
			$this -> description = $this -> settings['description'].'<img alt="Credit Cards" src="'.plugins_url().'/paygate-gateway/images/cc.png"></img>';

			//$this -> notification_email = $this -> settings['notification_email'];
			$this -> css_style = $this -> settings['css_style'];
			$this -> pay_method_allowed = $this -> settings['pay_method_allowed'];

			$this -> merchant_id = $this -> settings['merchant_id'];
			$this -> merchant_salt = $this -> settings['merchant_salt'];
			//$this -> bank_code = $this -> settings['bank_code'];
			//$this -> merchant_bank_account = $this -> settings['merchant_bank_account'];
			$this -> expiry_time = $this -> settings['expiry_time'];
			
			$this -> merchant_mode = $this -> settings['merchant_mode'];
			//$this -> member_api = $this -> settings['member_api'];
			$this -> redirect_page_id = $this -> settings['redirect_page_id'];//usually order receipt page

			//$this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_PayGatenet', home_url( '/' ) ) );

			$this->s2s_verification=($this -> settings['merchant_id']==$this -> settings['merchant_mode']?'yes':'no');
			//$this->callback_url   = $this -> settings['callback_url'];
			//$this -> liveurl = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_PayGate', home_url( '/' ) ) );

			$this -> nonce = wp_create_nonce( $hash = hash('sha512', home_url().$this->merchant_id) );// secret
			
			$this -> msg['message'] = "";
			$this -> msg['class'] = "";

			//check
			$this->debug = $this->get_option( 'debug' );
			$this->spm = (count($this->pay_method_allowed)==1)?'yes':'no';//more than one paymethod?
			// Logs
			if ( 'yes' == $this->debug )
				//$this->log = $woocommerce->logger();deprecated since 2.1
				$this->log = $this->log = new WC_Logger();
				
			//add_action('init', array(&$this, 'check_paygate_response'));
			//add_action('admin_init', array($this, 'admin_init'));

			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				 } else {
					add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
				}
			add_action('woocommerce_receipt_paygate', array(&$this, 'receipt_page'));//paygate pay page

			// Payment listener/API hook
			// listen to paygate  see http://docs.woothemes.com/document/payment-gateway-api/
			add_action( 'woocommerce_api_wc_gateway_paygate', array( $this, 'callback' ) );//can be from shop or PayGate

			// Order update
			add_action('woocommerce_update_order', array( $this,'paygate_order_update') );

			//register javascript
			add_action('wp_enqueue_scripts', array( &$this, 'load_javascript' ));  

			//check if PayGate may be enabled

			if ( !$this->is_valid_for_use() ) $this->enabled = false;
			//if ( count($this -> settings['pay_method_allowed']) == 0 ) $this->enabled = false;

			//add info to the Email
			add_action( 'woocommerce_email_after_order_table', 'add_info_to_email', 15, 2 );			

		}//end of __construct()

		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		function is_valid_for_use() {
			if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_pagate_supported_currencies', array( 'USD', 'KRW'  ) ) ) ) return false;
			return true;
		}

		/**
		 * Add info to the Email
		 **/
		function add_info_to_email( $order, $is_admin_email ) {
		  if ( $is_admin_email ) {//admin email only
			echo '<p><strong>Payment Method:</strong> ' . $order->payment_method_title . '</p>';
		  }
			echo '<p><strong>Payment Method:</strong> ' . $order->payment_method_title . '</p>';
		  if ($_POST['paymethod']=="4") {//VIRTUAL BANK TRANSFER
			echo '<p><strong>VIRTUAL BANK TRANSFER:</strong> Dear customer, please transfer the order total in the near future.' . $this -> merchant_bank_account . ' </p>';
		  }
		}	

		/**
		 * register and enque javascript
		 **/		
		//if (function_exists('load_javascript')) {  
		 function load_javascript() {  
			global $woocommerce;
			if ( is_page('checkout') ) {
				wp_register_script( 'checkout_handler', plugins_url().'/paygate-gateway/js/paygate_checkout.js','woocommerce.min.js', '1.0', true);
				wp_register_script( 'OpenPayApi', 'https://api.paygate.net/ajax/common/OpenPayAPI.js');
				wp_enqueue_script('checkout_handler');
				wp_enqueue_script('OpenPayApi');

				//pass variables to javascript
				$go=get_option('woocommerce_paygate_settings');
				//$smp=$smp['pay_method_allowed'];//find own Merchant ID, for using it in the selectbox
				$smp = ((count($go['pay_method_allowed'])==1)?'yes':'no');
				$data = array(	//'site_url' => __(site_url()),
								'order_id' => $_GET['order'],
								'ajaxurl' => admin_url('admin-ajax.php'),
								'plugin_url' => $woocommerce->plugin_url(),
								'single_pay_method' => $smp,
								'check' => $this->nonce,
								's2s_verification' => ($this -> settings['merchant_id']==$this -> settings['merchant_mode']?true:false)
							);
								//'single_pay_method' => ($WC_PayGatenet->spm)

				wp_localize_script('checkout_handler', 'php_data', $data);

	
			}  
		}  
		
		/**
		 * log the transaction
		 **/
		function log_transaction($ordertotal){
			$oname='paygate.net_'.get_woocommerce_currency().'_transactions';
			
			$log=explode('.',get_option($oname));
			
			update_option( $oname, ($log[0]+1).'.'.($log[1]+$ordertotal) );		
			/*call home depending on conditions tbd*/			
		}
		/**
		 * read the transaction log
		 **/
		function read_transaction_log(){
			$oname='paygate.net_%_transactions';
			$wc_currencies=get_woocommerce_currencies();

			$html='<table class="transaction_log" summary="Transaction Log">
					<thead>
					<tr>
					<th scope="col">'.__('Transactions','PayGateKorea').'</th>
					<th scope="col">'.__('Currency','PayGateKorea').'</th>
					<th scope="col">'.__('Total','PayGateKorea').'</th>
					</tr>
					</thead>
					<tbody>
					';
			foreach($wc_currencies as $key => $value)
						{
							$oname='paygate.net_'.$key.'_transactions';
							if (get_option($oname)){
							$log=explode('.',get_option($oname));
							$html.='<tr><td>' .$log[0].'</td><td>'.$key.'</td><td>' .number_format($log[1]).'</td></tr>';
							};
						}
			$html.='</tbody>
					</table>';
			return $html;
		}
	
		/**
		 * order update after success (work in progress)
		 **/
		//add_action('wp_ajax_pgo_update', 'pgo_update_callback');
		//add_action('wp_ajax_nopriv_pgo_update', 'pgo_update_callback');

		function paygate_order_update() {
			global $woocommerce;
			//$order = new WC_Order( $_REQUEST['order'] );
			$order = new WC_Order( $this->decodeit($_REQUEST['goodoption5'],'order'));
			$this->log->add( 'paygate', 'Paygate - function paygate_order_update() called for Order:'.$this->decodeit($_REQUEST['goodoption5'],'order'));

			//do something different for VIRTUAL BANK TRANSFER
			//distinguish between 1st and 2nd callback

			//if ( $_REQUEST['paymethod']==='3007' and $_REQUEST['payresultcode']==='Success' ) {//VIRTUAL BANK TRANSFER delayed callback
			if ( $_REQUEST['paymethod']==='3007' and (!isset($_REQUEST['payresultcode']))) {//VIRTUAL BANK TRANSFER order (1st callback, awaiting payment)
				/*Put the order status to "Pending" after the customer successfully goes through "Virtual Banking Transfer" and add an order note about the payment status..
				Have the listener receiving your notification(including the shop's order ID and some verification with merchant salt etc.) any time after the order was placed . 
				This will then change the order status to "Processing" and add some additional notes to the order about the payment details.
				See WooCommerce order status: http://docs.woothemes.com/document/managing-orders/
				*/
				$order -> add_order_note(__('PayGate - VIRTUAL BANK TRANSFER: Waiting for payment<br/>Unique Id from PayGate:', 'PayGateKorea').' '.$_REQUEST['tid']);
				$order -> update_status('pending');
			}
			else{//standard order processing
				//exeption
				if ( $_REQUEST['paymethod']!='3007') {// no shop message for VIRTUAL BANK TRANSFER order (2nd callback, payment notification)
					$woocommerce->add_message(__('PayGate - Transaction completed. Thank you for shopping with us.', 'PayGateKorea'));
					$woocommerce -> cart -> empty_cart(); //empty the cart after the order is placed
				}
				//do this for all completed orders
				$this->log_transaction($_REQUEST['unitprice']);//write the transaction to db
				$order -> add_order_note(__('Transaction Success - Transaction ID ', 'PayGateKorea').$_REQUEST['tid'].' - Order:'.$this->decodeit($_REQUEST['goodoption5'],'order'));
				$order -> payment_complete();
				//$woocommerce -> cart -> empty_cart();
			}
			//die();
		}
		
		/**
		 *  If Single Paymethod selected returns 'true'
		 **/
		public function single_pay_method(){
			//$allowed_pay_methods = (array) $WC_PayGatenet->pay_method_allowed;
			return (count((array) $this->pay_method_allowed)==1);
		}

		function init_form_fields(){
			//set payment methods into it's own arry for later reuse for select options
			
			switch (get_woocommerce_currency()) { //paymethod selection dependant on shop currency
				case "KRW":
					$this -> payment_methods=array(
						'card'=> __('CARD AUTO DETECTION', 'PayGateKorea' ),
						'100'=> __('CREDIT CARD BASIC KRW', 'PayGateKorea' ),
						//'101'=> __('CREDIT CARD BASIC AUTH', 'PayGateKorea' ),
						//'102'=> __('CREDIT CARD ISP (IE only)', 'PayGateKorea' ),
						//'103'=> __('CREDIT CARD 3D SECURE', 'PayGateKorea' ),
						//'104'=> __('CREDIT CARD BASIC USD', 'PayGateKorea' ),
						//'105'=> __('CHINA PRC DEBIT CARD', 'PayGateKorea' ),
						//'106'=> __('CHINA ALIPAY', 'PayGateKorea' ),
						'9'=> __('CREDIT CARD basic demo', 'PayGateKorea' ),
						'4'=> __('REALTIME BANK TRANSFER', 'PayGateKorea' ),//https://km.paygate.net/display/CS/Realtime+Bank+Transfer
						'7'=> __('BANK TRANSFER NOTICE', 'PayGateKorea' ),
						'801'=> __('MOBILE PHONE', 'PayGateKorea' ),
						//'802'=> __('PHONE BILL', 'PayGateKorea' ),	
						//'803'=> __('KT ARS', 'PayGateKorea' ),
						//'999'=> __('ESCROW', 'PayGateKorea' ),
						//'transinfo'=> __('retrieve transaction info', 'PayGateKorea' ),
						//'rnameauth'=> __('Person authentication', 'PayGateKorea' ),
						//'cardreceipt'=> __('View/Print Card receipt', 'PayGateKorea' ),
					);
					break;
				case "USD":
					$this -> payment_methods=array(
						'104'=> __('CREDIT CARD BASIC USD', 'PayGateKorea' ),
					);
					break;
				default:
					// Paygate does not support shop currency

			}

			/* Bank Codes */
			$this -> bank_code=array(				
				//'54'=>__('HSBC','PayGateKorea'),
				'04'=>__('국민','PayGateKorea'),
				//'88'=>__('신한','PayGateKorea'),
				'20'=>__('우리','PayGateKorea'),
				'11'=>__('농협(중앙)','PayGateKorea'),
				//'12'=>__('지역농협','PayGateKorea'),
				//'23'=>__('SC제일','PayGateKorea'),
				//'05'=>__('외환','PayGateKorea'),
				'81'=>__('하나','PayGateKorea'),
				'26'=>__('신한은행','PayGateKorea'),
				//'27'=>__('씨티','PayGateKorea'),
				'03'=>__('기업','PayGateKorea'),
				//'07'=>__('수협','PayGateKorea'),
				'71'=>__('우체국','PayGateKorea'),
				//'02'=>__('산업','PayGateKorea'),
				//'32'=>__('부산','PayGateKorea'),
				//'37'=>__('전북','PayGateKorea'),
				//'39'=>__('경남','PayGateKorea'),
				//'34'=>__('광주','PayGateKorea'),
				//'31'=>__('대구','PayGateKorea'),
				//'35'=>__('제주','PayGateKorea'),
				//'45'=>__('새마을금고','PayGateKorea'),
				//'48'=>__('신용협동조합','PayGateKorea'),
				//'50'=>__('상호저축은행','PayGateKorea'),
				//'55'=>__('도이치','PayGateKorea'),
				//'60'=>__('Bank of America','PayGateKorea'),
				//'58'=>__('Mizuho Bank','PayGateKorea'),
				//'59'=>__('Tokyo Mitsubishi Bank','PayGateKorea'),
				//'57'=>__('UFJ Bank','PayGateKorea'),
			);
				
			
				$m=get_option('woocommerce_paygate_settings');
				$m=$m['merchant_id'];//find own Merchant ID, for using it in the selectbox

				$this -> form_fields = array(
					'enabled' => array(
						'title' => __('Enable/Disable', 'PayGateKorea'),
						'type' => 'checkbox',
						'label' => __('Enable PayGate Payment Module.', 'PayGateKorea'),
						'default' => 'no'),
						
					'title' => array(
						'title' => __('Title:', 'PayGateKorea'),
						'type'=> 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'PayGateKorea'),
						'desc_tip'      => true,
						'default' => __('PayGate.net', 'PayGateKorea')),
						
					'description' => array(
						'title' => __('Description:', 'PayGateKorea'),
						'type' => 'textarea',
						'description' => __('This controls the description which the user sees during checkout.', 'PayGateKorea'),
						'desc_tip'      => true,
						'css'           => 'width: 450px; height: 50px;',					
						'default' => __('Pay securely by international Credit card or Korean Debit card or Korean internet banking through PayGate Secure Servers.', 'PayGateKorea')),

					'merchant_id' => array(
						'title' => __('Merchant ID:', 'PayGateKorea'),
						'type'=> 'text',
						'description' => __('Please register your Merchant ID. (mandatory setting)', 'PayGateKorea'),
						'desc_tip'      => true,
						'default' => __('Merchant ID', 'PayGateKorea')),
				
					'merchant_salt' => array(
						'title' => __('Merchant Hash Salt:', 'PayGateKorea'),
						'type'=> 'text',
						'description' => __('This is the salt used to create the Hash key. For example: "test1" is default for merchant ID "paygatekr". (mandatory setting)', 'PayGateKorea'),
						'desc_tip'      => true,
						'default' => __('your_salt_here', 'PayGateKorea')),						
						
					'merchant_mode' => array(
						'title' => __('Merchant ID in use:', 'PayGateKorea'),
						'type' => 'select',
						'description' => __('Use "paygatekr" or "paygateus" for testing purposes. Select your own ID for production.', 'PayGateKorea'),
						'desc_tip'      => true,
						'label' => __('Use "paygatekr" or "paygateus" for testing purposes. Select your own ID for production.', 'PayGateKorea'),
						'class'  => 'chosen_select',
						'options'=> array(
							'paygatekr' => 'paygatekr',
							'paygateus' => 'paygateus',
							$m => $m,
						)
					),
					
					'css_style' => array(
						'title' => __('CSS Style:', 'PayGateKorea'),
						'type' => 'select',
						'description' => __('CSS Style. For custom style edit custom-form.css in plugin directory', 'PayGateKorea'),
						'desc_tip'      => true,
						'class'         => 'chosen_select',
						'options'=> array(
							'0'=> 0,
							'1'=> 1,
							'2'=> 2,
							'3'=> 3,
							'4'=> 4,
							'5'=> 5,
							'no'=>__( 'custom style', 'PayGateKorea' ),
						)
					),

					'pay_method_allowed' => array(
						'title' => __('Payment Methods:', 'PayGateKorea'),
						'type' => 'multiselect',
						'description' => __('Allowed Payment Methods, multiple select option, <a href="https://km.paygate.net/pages/viewpage.action?pageId=721063">read Customer documentation</a> (mandatory setting)', 'PayGateKorea'),
						'desc_tip'      => false,
						'class'         => 'chosen_select',
						'css'           => 'width: 450px;',
						'options'=> $this -> payment_methods,
					),

					'expiry_time' => array(
						'title' => __('Expiry time in days:', 'PayGateKorea'),
						'type'=> 'text',
						'description' => __('Define the bank transfer expiry time in days. (mandatory setting when REALTIME BANK TRANSFER method is selected)', 'PayGateKorea'),
						'desc_tip'      => true,
						'default' => __('14')),
					
					'redirect_page_id' => array(
						'title' => __('Return Page', 'PayGateKorea'),
						'type' => 'select',
						'options' => $this -> get_pages('Select Page'),
						'description' => "URL of success page"
					),
					'debug' => array(
						'title' => __( 'Debug Log', 'PayGateKorea' ),
						'type' => 'checkbox',
						'label' => __( 'Enable logging', 'PayGateKorea' ),
						'default' => 'yes',
						'description' => sprintf( __( 'Log PayGate events, such as PayGate requests, inside <code>../plugins/woocommerce/logs/paygate-%s.txt</code><br/> and furthermore allows the Admin to toggle the PGIOform on "Checkout" page.', 'PayGateKorea' ), sanitize_file_name( wp_hash( 'paygate' ) ) ),
					),
				);
		}

		/** 
		 * Admin Settings Page
		 **/
		public function admin_options(){
		    load_plugin_textdomain( 'PayGateKorea', false, plugin_basename( dirname(__FILE__) ) . '/languages' );  
			wp_register_style( 'PayGateAdminStylesheet', plugins_url('css/backend.css', __FILE__) );
			wp_enqueue_style( 'PayGateAdminStylesheet' );
			wp_register_script( 'admin_page', plugins_url().'/paygate-gateway/js/paygate_admin.js','woocommerce.min.js', '1.0', true);
			wp_enqueue_script('admin_page');
			$woocommerce_plugin_file =  trailingslashit( WP_PLUGIN_DIR ) . "woocommerce/woocommerce.php";
			$wc_data=get_plugin_data( $woocommerce_plugin_file);
			$paygate_plugin_file =  __FILE__ ;
			$pg_data=get_plugin_data( $paygate_plugin_file);
			$currentsystem = "Wordpress ".get_bloginfo('version')." | ".$wc_data['Title' ]." ".$wc_data['Version' ]." | ".$pg_data['Title' ]." ".$pg_data['Version' ];
			
			$warning="";
			echo '<table cellpadding="3" cellspacing="3">
					<tr>
						<td style="vertical-align:middle"><a href="http://www.paygate.net/" title=""><img class="help_tip" data-tip="'.__('Click here to visit PayGate.net','PayGateKorea').'" src='.$this->icon .'></a></td>
						<td style="vertical-align:middle"><input class="button-primary help_tip" data-tip="'.__('Click here to manage your PayGate merchant account.','PayGateKorea').'" type="button" value="admin area login" onclick="window.location= \'https://admin.paygate.net/\'"></td>
					</tr>
				</table>';
			echo '<h3>'.__(' ....the most popular payment gateway for online shopping in Korea!','PayGateKorea').'</h3>';

			if ( $this->is_valid_for_use() ) {
				echo '<table class="form-table">';
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
				if ( $this -> settings['merchant_id'] == "" ){
					$this->enabled = "no";
					$warning=$warning. '<div class="error settings-error" ><p>'.__('You need to set a merchant ID. GateWay can not be enabled!','PayGateKorea') .'</p></div>';//visibility="hidden"
				}
				if ( $this -> settings['merchant_salt'] == "" ){
					$this->enabled = "no";
					$warning=$warning. '<div class="error settings-error" ><p>'.__('You need to set a merchant salt. GateWay can not be enabled!','PayGateKorea') .'</p></div>';//visibility="hidden"
				}
				if ( count($this -> settings['pay_method_allowed']) == 0 ){
					$this->enabled = "no";
					$warning= $warning.'<div class="error settings-error" ><p>'.__('You need to select at least one pay method. GateWay can not be enabled!','PayGateKorea') .'</p></div>';//visibility="hidden"
				}
				echo $warning;
				echo '</table><!--/.form-table-->';
				echo '<hr>';
				echo'<table class="form-table">
					<tr valign="top">
						<th scope="row" class="titledesc"><label >'.__('Gateway Enabled','PayGateKorea').': </label><img class="help_tip" data-tip="'.__('Gateway Status','PayGateKorea').'" src="'. plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" /></th>
						<td class="forminp">'
						. $this -> settings['enabled'].
						'</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc"><label >'.__('Verification mode','PayGateKorea').': </label><img class="help_tip" data-tip="'.__('Server2Server Transaction Verification','PayGateKorea').'" src="'. plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" /></th>
						<td class="forminp">'
						. ($this->s2s_verification=='yes'?__('Server to Server transaction verification is <strong>active</strong>.','PayGateKorea'):__('Server to Server Transaction verification is <strong>inactive</strong>. To activate it select your own Merchant ID for use.','PayGateKorea')).
						'</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc"><label >'.__('Shop Currency','PayGateKorea').': </label><img class="help_tip" data-tip="'.__('Current shop currency','PayGateKorea').'" src="'. plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" /></th>
						<td class="forminp">'
						. get_woocommerce_currency().
						'</td>
					</tr>
					<tr valign="top">
						<th class="titledesc" scope="row">
							<label >'.__('Callback URL:', 'PayGateKorea').'</label>
							<img class="help_tip" data-tip="'.__('very important', 'PayGateKorea').'" width="16" height="16" src="'. plugins_url().'/woocommerce/assets/images/help.png">
						</th>
						<td class="forminp">'
							.home_url( '/wc-api/'.get_class($this).'/<br/>').
							
							'<p class="description">
							'.__('Fixed URL you have to register at your PayGate.net merchant account for Server to Server verification. Redirect Transaction Result go to https://admin.paygate.net / HomePage / Member Management / Setup Service Option / Redirect the transaction result...use "Use BackGround Redirect" and set the URL as shown above', 'PayGateKorea').'
							</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc"><label >'.__('Server Name','PayGateKorea').': </label><img class="help_tip" data-tip="'.__('Your domain server name','PayGateKorea').'" src="'. plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" /></th>
						<td class="forminp">'
						. $_SERVER['SERVER_NAME'].
						'</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc"><label >'.__('Transaction log','PayGateKorea').': </label><img class="help_tip" data-tip="'.__('information about transactions amount/currency and the transaction count/currency','PayGateKorea').'" src="'. plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" /></th>
						<td class="forminp">'
						. $this->read_transaction_log().
						'</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc"><label >'.__('System versions','PayGateKorea').': </label><img class="help_tip" data-tip="'.__('information about your systems versions','PayGateKorea').'" src="'. plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" /></th>
						<td class="forminp">'
						. $currentsystem.
						'</td>
					</tr>
				</table>';
				echo $warning;

			} 
			else {//hide the form for unsupported shop currencies
				echo '<div class="inline error"><p><strong>'. __( 'Gateway Disabled', 'PayGateKorea' ).'</strong>: '. __( 'PayGate does not support '.get_woocommerce_currency().' as store currency.', 'PayGateKorea' ).'</p></div>';
			}

		}

		/**
		 *  Payment fields for PayGate.
		 **/
		function payment_fields(){
            global $woocommerce; 
            
            $checkout = $woocommerce->checkout();
			if($this -> description) echo wpautop(wptexturize($this -> description));
			//var_dump(debug_backtrace());

			if ( 'yes' == $this->debug )
				$this->log->add( 'paygate', 'Paygate - function payment_fields() executed.');
								
		}//end of payment fields

		/**
		 * Receipt Page
		 **/
		function receipt_page($order){
		global $woocommerce;
			if (count($this->pay_method_allowed)>1){
				echo '<p class="woocommerce-info">'.__('PayGate - Thank you for your order, please select your desired payment method, then click the button below to pay with PayGate.', 'PayGateKorea').'</p>';
			}
			else
			{
				echo '<p class="woocommerce-info">'.__('PayGate - Thank you for your order, click the button below to pay with PayGate.', 'PayGateKorea').'</p>';
			}
			echo $this -> generate_paygate_form($order);
			if ( 'yes' == $this->debug )
				$this->log->add( 'paygate', 'Paygate - Receipt page opened.');
		}

		/**
		 * Retrieve Slug from Page_ID
		 **/			
		 //$redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
		public function getmyslug($post_id){
			global $post;
			$post = get_post($post_id);
			return $post->post_name;
		}	
		
		/**
		 * Generate PayGate form
		 **/
		public function generate_paygate_form($order_id){
		   global $woocommerce;

		   if ($this->css_style=='no'){ //custom styles for pgio form
	   			wp_register_style( 'PayGateAdminStylesheet', plugins_url('css/custom-form.css', __FILE__) );
				wp_enqueue_style( 'PayGateAdminStylesheet' );
			};

			$order = new WC_Order( $order_id );
			$txnid = $order_id.'_'.date("ymds");//internal transaction ID

		
				
			$redirect_url =  home_url().'/checkout/'.$this->getmyslug($this -> redirect_page_id).'?order-pay='.$_GET['order-pay'].'&key='.$_GET['key'];

			//$productinfo = "Order $order_id";
		
			$custid = (get_current_user_id()==0)? 'not registered':get_current_user_id();

			$available_paymethods = (array) $this->payment_methods;

			$allowed_pay_methods = (array) $this->pay_method_allowed;
			
			$this->single_pay_method()?$display='style="display:none"':$display='';//$display paymethod selection if more then one is allowed;

			$paymethod_radios='<span '.$display.'>'.__('Available payment methods:','PayGateKorea').'<br>';
					foreach($allowed_pay_methods as $key => $value)
					{
							$paymethod_radios.= '<input type="radio" name="methods" value="'.$value.'"><label for="'.$value.'">'.$available_paymethods[$value].'</label><br>';
					};
			$paymethod_radios.='</span>';
			(get_woocommerce_currency()=='KRW')? $currency='WON': $currency=get_woocommerce_currency();// re-key the curency because of paygate's legathy

			//show DEBUG and SIMULATE button in debug mode when role is admin
			IF( current_user_can( 'manage_options' ) && ('yes' == $this->debug)){
				$debug_button = '<a class="button pgioform" href="#" >'.__('PGIOform', 'PayGateKorea').'</a>';
				$simulate_button = '<a class="button callback" href="#" >'.__('Callback', 'PayGateKorea').'</a>';
			}
			ELSE{
				$debug_button = '';
				$simulate_button = '';
			};

			//encode identification string ()
			$identification = array('order' => $_GET['order-pay'], 'key' => $_GET['key'], 'check' => $this->nonce);
			$identification=base64_encode(json_encode($identification));

			ndc(get_woocommerce_currency())?$unitprice=intval($order->order_total): $unitprice=$order->order_total; //integer value for non decimal currencies

			$pgio_form_args = array(
									'kindcss' => $this->css_style,// CSS
									'langcode' => $this->paygate_language(),// Language code
									'charset' => 'UTF-8',// Character Set
									'mid' => $this->merchant_mode,//$m,// Merchant ID
									'goodcurrency' => $currency,// Good Currency
									'unitprice' => $unitprice,//$order->order_total,// Unit Price
									'goodname' => 'Order: '.($_GET['order-pay']).' / Customer: '.$custid ,// Good name
									'paymethod' => '',// Payment Method...will be set by javascript, depending on customer selection or for single allowed paymethod by merchant
									'cardtype' => '', //Card Type
									'cardexpiremonth' => '',// Card Expiry Month
									'cardexpireyear' => '',// Card Expiry Year
									'cardquota' => '',// Card Quota
									'cardownernumber' => '',// Card Owner Number
									'cardsecretnumber' => '',// Card Secret Number
									//'bankcode' => $this -> bank_code,// Bank Code
									'bankcode' => '',// Bank Code
									'bankaccount' => '',// Bank Account Number
									//'bankaccount' => $this -> merchant_bank_account,// Bank Account Number
									'tid' => 'tid',// TID
									'bankexpyear' => date('Y', strtotime("+" . $this -> expiry_time . " day")),// Bank Expiry Year
									'bankexpmonth' => date('m', strtotime("+" . $this -> expiry_time . " day")),// Bank Expiry Month
									'bankexpday' => date('d', strtotime("+" . $this -> expiry_time . " day")),// Bank Expiry Day
									'loanSt' => '',// Loan(escrow)
									'socialnumber' => '',// Social Number
									'replycode' => '',// Reply Code
									'ResultScreen' => '',// Reply Code
									'replyMsg' => '',// Reply Message
									'resultcode' => '',// Result Code
									'resultmsg' => '',// Result Message
									'demoresult' => '',// Demo Result 
									'taxflag' => '',// Tax flag
									'taxrepresentative' => '',// taxrepresentative
									'taxaddr' => '',// taxcompanyname
									'taxbiztype' => '',// taxbiztype
									'taxbizitem' => '',// taxbizitem
									'taxdepartment' => '',// taxdepartment
									'taxcontactname' => '',// taxcontactname
									'taxcontactemail' => '',// taxcontactemail
									'taxcontactphone' => '',// taxcontactphone
									'receipttoname' =>  $order->billing_first_name.' '.$order->billing_last_name,// Receipt to Name
									'receipttosocialnumber' => '',// Receipt to Social Number
									'carrier' => '',// Carrier
									'receipttotel' => $order->billing_phone,// Receipt to Telephone
									'receipttoemail' =>  $order->billing_email,// Receipt to Email
									'welcomeURL' => home_url(),// welcomeURL
									'MoveURL'  => $redirect_url,// MoveURL
									'cardauthcode' => '',// Card Authorisation Code
									'fromDT' => '',// Search date
									'receipttoaddr' => $order->billing_address_1.', '.
										$order->billing_address_2.', '.
										$order->billing_postcode.'-'.
										$order->billing_city.', '.
										$order->billing_state.' - '.
										$order->billing_country,// Receipt to Address
									'goodoption1' => '',// Good Option 1
									'goodoption2' => '',// Good Option 2
									'goodoption3' => '',// Good Option 3
									'goodoption4' => '',// Good Option 4
									'goodoption5' => $identification,// Good Option 5
									'hashresult' => '',// hashresult
									'mb_serial_no' => rand(10000, 99999),
									'check' => $this->nonce,// check pg_custom field
									//'order' => $_GET['order'],// order number pg_custom field
									//'key' => $_GET['key'],// order key pg_custom field
									//'wc-api' => 'shop'// api own call
								);
			ksort($pgio_form_args);
			$pgio_form_args_array = array();
			foreach($pgio_form_args as $key => $value){
					if ( 'yes' == $this->debug && current_user_can( 'manage_options' )){ //only for admin!
						$pgio_form_args_array[] = "<li>$key = <input name='$key' value='$value'/></li>";
						if (!each($pgio_form_args)) {//after last element
						$pgio_form_args_array[]='<input type="submit" class="button-alt" id="paygate" value="Simulate CallBackSuccess">';
						}
					}
					else{
					$pgio_form_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
					}
			}

			return $paymethod_radios.'
				<a class="button paygate"  href="#PGIO" >'.__('Pay via PayGate <img alt="Korean Flag" src="'.plugins_url().'/paygate-gateway/images/kr.png"></img>', 'PayGateKorea').'</a> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'PayGateKorea').'</a> ' . $debug_button . ' ' . $simulate_button .'

				<div id="PGIOscreen" style="width: 100%;"></div>
				<form id="PGIOForm" name="PGIOForm"  style="display:none" action="'.home_url('/wc-api/').get_class($this).'/" method="post">
				<ul>'.
					implode('', $pgio_form_args_array).'</ul>
				</form>';
				//echo var_dump($_REQUEST);

				if ( 'yes' == $this->debug )
				$this->log->add( 'paygate', 'Paygate - PayMethod form generated.');
		}

		/**
		 * determine language of PayGate's PGIO Screen
		 **/
		function paygate_language(){
			$lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];//Detecting Default Browser language
				switch(substr($lang,0, 2)){
				case "zh":
						$lc = "CH";
					break;
				case "ko":
					$lc = "KR";
					break;
				case "ja":
					$lc = "JP";
					break;
				default:
					$lc = "US";
					break;
				}

				if ( 'yes' == $this->debug )
				$this->log->add( 'paygate', 'Paygate - Language set to: '.$lc);

			return $lc;
		}
			
		/**
		 * Process the payment and return the result
		 **/
		function __process_payment($order_id){//goes to checkout->pay
			global $woocommerce;
			$order = new WC_Order( $order_id );
			return array('result' => 'success', 
			'redirect' => add_query_arg('order',$order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
			);

			$this -> msg['message'] = 'Thank you for shopping with us. Right now your payment status is processing, We will keep you posted regarding the status of your order through e-mail';
			$this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
			$woocommerce->add_message(__('Thank you for shopping with us. Right now your payment status is processing, We will keep you posted regarding the status of your order through e-mail', 'PayGateKorea'));

			$order -> add_order_note(__('PayGate - Payment status is pending<br/>Unique Id from PayGate:', 'PayGateKorea').' '.$_REQUEST['tid']);
			$order -> add_order_note($this->msg['message']);
			$order -> update_status('processing');

			if ( 'yes' == $this->debug )
			$this->log->add( 'paygate', 'Paygate - function process_payment() executed');
			
		}
		function process_payment($order_id){//goes to checkout->pay
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$paygate_adr=get_permalink(get_option('woocommerce_pay_page_id'));
			$paygate_args= array(
								'order-pay'=>$order->id,
								'_wpnonce'=>wp_create_nonce( 'woocommerce-process_checkout' ),
								'key'=> $order->order_key		
			);
			$paygate_args = http_build_query( $paygate_args, '', '&' );

			return array('result' => 'success', 
			'redirect'	=> $paygate_adr ."?". $paygate_args
			);

			$this -> msg['message'] = 'Thank you for shopping with us. Right now your payment status is processing, We will keep you posted regarding the status of your order through e-mail';
			$this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
			$woocommerce->add_message(__('Thank you for shopping with us. Right now your payment status is processing, We will keep you posted regarding the status of your order through e-mail', 'PayGateKorea'));

			$order -> add_order_note(__('PayGate - Payment status is pending<br/>Unique Id from PayGate:', 'PayGateKorea').' '.$_REQUEST['tid']);
			$order -> add_order_note($this->msg['message']);
			$order -> update_status('processing');

			if ( 'yes' == $this->debug )
			$this->log->add( 'paygate', 'Paygate - function process_payment() executed');
			
		}
		 /**
		 * Check PayGate PGIO Form validity
		 **/
		function check_pgio_request_is_valid() {
			global $woocommerce;
			// Get recieved values from get data
			$order = $this->decodeit($_REQUEST['goodoption5'],'order');
			$check = $this->decodeit($_REQUEST['goodoption5'],'check');
			
			if ( 'yes' == $this->debug )
				$this->log->add( 'paygate', 'PayGate - PGIO Response check: mid='  . $_REQUEST['mid'].' tid='  . $_REQUEST['tid'].' order='. $order.' Replycode ='. $_REQUEST['replycode'].' replyMsg'.$_REQUEST['replyMsg'] );
			// check to see if the request was valid
			if ( ($_REQUEST['replycode']==='0000'||$_REQUEST['replycode']==='NSP016') && $check==$this->nonce ) {//&& check_hash_validity($array)
				if ( 'yes' == $this->debug )
				$this->log->add( 'paygate', 'PayGate - Order:'.$order.' Received valid response from paygate' );
				return true;
			}
			else{
				if ( 'yes' == $this->debug ){ 
						$dump='';
						foreach($_SERVER as $key => $value)
						{
							$dump.= PHP_EOL.$key.' => '.$value;
						}
					$this->log->add( 'paygate', 'PayGate - Order:'.$order.' Received invalid response from paygate. Check=Nonce?'.$check.'='.$this->nonce.$dump);
				}
				return false;
			}
		}

		/**
		 * CALLBACK :Check for both valid PayGate server "Redirect Transaction Result" or Shop checkout/pay form submit or VIRTUAL BANK TRANSFER callback
		 **/
		function callback(){
		global $woocommerce;
			//@ob_clean();
				if ( 'yes' == $this->debug ){
					$dump='';
					foreach($_REQUEST as $key => $value)
					{
						$dump.= PHP_EOL.$key.' => '.$value;
					}
					$this->log->add( 'paygate', 'Paygate - function callback() called by HTTP_HOST:'.$_SERVER['REMOTE_ADDR'].'" -> Home URL:'.home_url().' - Server Name:'.$_SERVER['SERVER_ADDR']).$dump;
					};

			$array=$_REQUEST;
			
			$order = $this->decodeit($_REQUEST['goodoption5'],'order');
			$key = $this->decodeit($_REQUEST['goodoption5'],'key');
			
			//first check source of call...paygate or shop itself...distinguished by isset($_POST['check']) or $_SERVER('HTTP_HOST')
			//order status update depending on s2s_verification setting

			if ($_POST['check']==$this->nonce){ //Check for checkout/pay form submit - request comes from the shop itself

				if ( ! empty( $_POST ) && $this->check_pgio_request_is_valid() ) {
					//header( 'HTTP/1.1 200 OK' );


					if ( 'no' == $this->s2s_verification)//order status update depending on s2s_verification setting
					
						do_action( "woocommerce_update_order", $_POST );

					if ( 'yes' == $this->debug ){
						$this->log->add( 'paygate', 'Paygate - PGIO Form submit validated successful.');
					}
					wp_redirect( home_url('/').$this->getmyslug($this -> redirect_page_id).'?order='.$order.'&key='.$key, 302 );// go to the order receipt page
					exit;
				} else {

					if ( 'yes' == $this->debug ){
							$this->log->add( 'paygate', 'Paygate - PGIO Form submit validated NOT successful. Error: '.$_POST['replycode'].' Error message: '.$_POST['replyMsg']);
						}

					$woocommerce->add_error(__('Sorry, this transaction failed, please try again. Error: '.$_POST['replycode'].'<br/>Error message: '.$_POST['replyMsg'], 'PayGateKorea'));
					wp_redirect( home_url('/checkout/pay/').'?order='.$order.'&key='.$key, 302 );//go back to the .../checkout/pay/ page
					exit;
				}
			} else {//request comes from paygate (s2s_verification or VIRTUAL BANK TRANSFER callback)

				if ( $_REQUEST['payresultcode']==='0000' ) {
				//if ( ! empty( $_REQUEST ) && ($_REQUEST['replycode']==='0000')||($_REQUEST['replycode']==='NSP016') && ($_SERVER['SERVER_NAME']==$_SERVER['HTTP_HOST'])) {//for paygate purpose

					if ( 'yes' == $this->debug ){
						$this->log->add( 'paygate', 'Paygate - Order:'.$order.' Server2Server transaction Verification Callback Request Success.'.$dump);
					}
					do_action( "woocommerce_update_order", $_POST ); 
					//echo '<PGTL><VERIFYRECEIVED>RCVD</VERIFYRECEIVED><TID>'.$_REQUEST['tid'].'</TID></PGTL>';//receipt confirmation message
					echo '<VERIFYRECEIVED>RCVD</VERIFYRECEIVED>';//receipt confirmation message

					exit;

				} else {

					if ( 'yes' == $this->debug ){

						$this->log->add( 'paygate', 'Paygate - Order:'.$order.' Server2Server transaction Verification Callback failure. Error: '.$_REQUEST['replycode'].' Error message: '.$_REQUEST['replyMsg']).'<br/>';
					}
					wp_die( "PayGate - Callback Request Failure".var_dump($_REQUEST).'<hr>We apologize!<br/> When you see this something went terribly wrong, please get in touch with '.home_url()  );

					exit;
				}
			}
		}

		/**
 		 * generate woocomerce message boxes
		 **/
		function showMessage($content){
				return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
			}

		/**
 		 * get all pages...needed for admin backend
		 **/
		function get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
					$has_parent = $page->post_parent;
					while($has_parent) {
						$prefix .=  ' - ';
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
		
		/**
		 * decode function for string in $_post
		 **/
		function decodeit($string,$key){
			$json=json_decode(base64_decode($string));
			return $json->{$key};
		}	
		
	}//end of class WC_Gateway_PayGate

	/*******************ndc() = check if currency has decimals**********/
	function ndc($cc){ 
		$ndcarr= array('KRW','JPY'); //non decimal currencies
		return in_array($cc, $ndcarr);
	}

	/*******************verifytransactionService (an additional ajax callback service)**********/
	function verifytransactionService() {
		//global $wpdb;
		global $woocommerce;
		$options = get_option('woocommerce_paygate_settings'); //read the plugins options for the hash salt

		// Logs
		if ( 'yes' == $options['debug'] )
			//$log = $woocommerce->logger();
			$log = new WC_Logger();
					
		$order = new WC_Order( intval( $_POST['order_id'] ));
		
		(get_woocommerce_currency()=='KRW')? $currency='WON': $currency=get_woocommerce_currency();// re-key the curency because of paygate's legathy

		ndc(get_woocommerce_currency())?$unitprice=intval($order->order_total): $unitprice=$order->order_total; //integer value for non decimal currencies
		
		$data = $_POST['replycode'].$_POST['tid'].$_POST['mb_serial_no'].$unitprice.get_woocommerce_currency();
		//$data = replycode + tid + mb_serial_no + amount + currency
		$salt = $options['merchant_salt'];  // to be set in backend correlating with merchant_id
		$merchanthash = hash('sha256',$salt.$data);
		$log->add( 'paygate', $data );


		if($_POST['paygatehash'] == $merchanthash){
			$returncode='0000';
			//$errmsg=__('PayGate - Received valid hash from paygate','PayGateKorea');
		}
		else {
			$returncode='9999';
			//$errmsg=__('PayGate - Received invalid hash from paygate','PayGateKorea');
		}

		if ( 'yes' == $options['debug'] ) {
			//$log->add( 'paygate', $errmsg);
			$woocommerce->log->add( 'paygate', $errmsg);
			//$log->add( 'paygate', 'PayGate - PGIO hash check: Function RETURN="' . $returncode . '" - Salt in use="' . $salt . '" - merchanthash="' . $merchanthash . '" - paygatehash="' . $_POST['paygatehash'] .'" - replycode="' . $_POST['replycode'] . '" - tid="'.$_POST['tid'] . '" - mb_serial_no="' . $_POST['mb_serial_no']. '" - order_total="' . $unitprice . '" - currency="' . get_woocommerce_currency() . '"');
			$woocommerce->log->add( 'paygate', 'PayGate - PGIO hash check: Function RETURN="' . $returncode . '" - Salt in use="' . $salt . '" - merchanthash="' . $merchanthash . '" - paygatehash="' . $_POST['paygatehash'] .'" - replycode="' . $_POST['replycode'] . '" - tid="'.$_POST['tid'] . '" - mb_serial_no="' . $_POST['mb_serial_no']. '" - order_total="' . $unitprice . '" - currency="' . get_woocommerce_currency() . '"');
			//$log->add( 'paygate', 'PayGate - PGIO hash check: Function RETURN="' . $returncode . '" - Salt in use="' . $salt . '" - merchanthash="' . $merchanthash . '" - paygatehash="' . $_POST['paygatehash'] .'" - replycode="' . $_POST['replycode'] . '" - tid="'.$_POST['tid'] . '" - mb_serial_no="' . $_POST['mb_serial_no']. '" - order_total="' . $unitprice . '" - currency="' . $currency . '"');
		}
		echo $returncode;

		die();
	}
		//register verifytransactionService
		add_action('wp_ajax_vts', 'verifytransactionService');
		add_action('wp_ajax_nopriv_vts', 'verifytransactionService');
	/**************end verifytransactionService***************************/
		
	/**
	* Add the Gateway to WooCommerce
	**/
	function woocommerce_add_gateway_paygate_gateway($methods) {
		$methods[] = 'WC_Gateway_PayGate';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_paygate_gateway' );

}//END OFF woocommerce_gateway_paygate_init 

/**
 * remove plugin settings on plugin deactivation
 **/
register_deactivation_hook(__FILE__, 'pg_Deactivation');
if ( !function_exists('pg_Deactivation') )
{
	function pg_Deactivation()
	{
		delete_option( 'woocommerce_PayGate_settings' );
	}
}
