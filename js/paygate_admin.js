/** * Plugin Name: PayGate.net Payment Gateway for WooCommerce
 * Plugin URI: http://www.intelligent-it.asia
 * Description: PayGate.net - Korean Payment gateway for woocommerce
 * Version: 1.3 * Author: Intelligent IT * Author URI: http://www.intelligent-it.asia
 * @author Henry Krupp <henry.krupp@gmail.com>
 * @copyright 2013 Intelligent IT
 * @license  */
/* PayGate backend input check functions */
	
jQuery(document).ready(function() {
	//jQuery( ".settings-error" ).hide();
});

jQuery(window).bind("load", function() {
if(jQuery('input#woocommerce_paygate_merchant_id').val()=="") {
			//alert('Select at least one payment method!');
			jQuery('label[for="woocommerce_paygate_merchant_id"]').css("color", "red");			
   }
if(jQuery('input#woocommerce_paygate_merchant_salt').val()=="") {
			//alert('Select at least one payment method!');
			jQuery('label[for="woocommerce_paygate_merchant_salt"]').css("color", "red");			
   }
if(!jQuery('ul.chosen-choices li').hasClass('search-choice')) {
			//alert('Select at least one payment method!');
			jQuery('label[for="woocommerce_paygate_pay_method_allowed"]').css("color", "red");			
   }
});

jQuery( "#mainform" ).submit(function( event ) {
if((!jQuery('ul.chosen-choices li').hasClass('search-choice')) || (jQuery('input#woocommerce_paygate_merchant_id').val()=="") || (jQuery('input#woocommerce_paygate_merchant_salt').val()=="")) {
			jQuery( ".settings-error" ).show();
			jQuery('input[name=woocommerce_paygate_enabled]').attr('checked', false);
			//event.preventDefault();
   }
});

