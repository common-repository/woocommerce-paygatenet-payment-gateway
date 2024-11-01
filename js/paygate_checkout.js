/** * Plugin Name: PayGate.net Payment Gateway for WooCommerce
 * Plugin URI: http://www.intelligent-it.asia
 * Description: PayGate.net - Korean Payment gateway for woocommerce
 * Version: 1.3 * Author: Intelligent IT * Author URI: http://www.intelligent-it.asia
 * @author Henry Krupp <henry.krupp@gmail.com>
 * @copyright 2013 Intelligent IT
 * @license  */
/* PayGate functions*/

	function startPayment() {
		jQuery("#PGIOscreen").html('<ul class="woocommerce-message"><li><img src="' + php_data.plugin_url + '/assets/images/ajax-loader.gif" alt="Redirecting..." style="float:left; margin-right: 10px;"/>Paygate - will start in a moment.</li></ul>');
		doTransaction(document.PGIOForm);
	}

	function getPGIOresult() {
		var replycode  = document.PGIOForm.elements['replycode'].value;
		var replyMsg   = document.PGIOForm.elements['replyMsg'].value;
		var tid = document.PGIOForm.elements['tid'].value; 
		var mb_serial_no = document.PGIOForm.elements['mb_serial_no'].value;
		//alert(replycode + ' - ' + replyMsg);
		var hashresult = getPGIOElement('hashresult');
		jQuery('input[name=hashresult]').val(hashresult);

		if(replycode=="0000"){
			jQuery.ajax({
				url:php_data.ajaxurl,
				type:'POST',
				data:{replycode:replycode,tid:tid,mb_serial_no:mb_serial_no,paygatehash:hashresult,action:'vts',order_id:php_data.order_id,},
				error:function(e){alert("error connection")},
				success:function(data){
				if(data=="0000"){
					//alert("success verification");
					callbacksuccess();
				}
				else{
					//alert("error verification");
					callbackfail();
					}  
				}
			});
		}
		else{
			//alert("error verification");
			callbackfail();
		}		
	}
 
	function callbacksuccess() {
		if ((getPGIOElement('replycode') === '0000' || getPGIOElement('replycode') === 'NPS016') && getPGIOElement('check') === php_data.check) { //for transaction success document.PGIOForm.action = 'shop.co.kr/pay/record_payment.jsp';
			jQuery("#PGIOscreen").html('<ul class="woocommerce-message"><li><img src="' + php_data.plugin_url + '/assets/images/ajax-loader.gif" alt="Redirecting..." style="float:left; margin-right: 10px;"/>Paygate - Successful Transaction. Please wait we redirect you to the receipt page.</li></ul>');
			//update_order();
			//ajax call to woocommerce
			document.PGIOForm.submit(); // to go to the order-received page	
		} else {
			if (replycode === '9805') { //9805 transaction cancelled	
				//alert("Transaction has been Cancelled");
				//reset_form()
				jQuery("#PGIOscreen").html('<ul class="woocommerce-error"><li>PayGate - Transaction has been Cancelled!</li></ul>');
			} else { // for transaction failure
				//alert("Transaction has failed. Please try again. Error Code: "+replycode);
				jQuery("#PGIOscreen").html('<ul class="woocommerce-error"><li>PayGate - Transaction has failed. Please try again.<br>' + getPGIOElement('ResultScreen') + '. Code: ' + getPGIOElement('replycode') + '</li></ul>');
				//reset_form()
			}
		}
	}

	function callbackfail() {
		// paygate system error or invalid transaction
		var mid   = document.PGIOForm.elements['mid'].value;
		var tid = document.PGIOForm.elements['tid'].value; 
		var mb_serial_no = document.PGIOForm.elements['mb_serial_no'].value;

		var hashresult = getPGIOElement('hashresult');
		jQuery.ajax({
			url:"https://service.paygate.net/payment/pgtlCancel.jsp",
			type:'POST',
			data:{pmemberid:mid, pmemberpasshash:hashresult, transactionid:tid},
			error:function(e){alert("error connection")},
			success:function(data){
			}
		});		
		jQuery("#PGIOscreen").html('<ul class="woocommerce-error"><li>PayGate - Sorry, something went wrong...please <button type="button" onclick="location.reload()">try again</button>.<br/>' + document.PGIOForm.elements['replyMsg'].value + '\nError Code : ' + document.PGIOForm.elements['replycode'].value + '</li></ul>');
	}

	function reset_form() {
		jQuery('#PGIOscreen').empty(); //remove PGIOScreen content
		jQuery('input[name=replycode]').val(''); //remove the replycode in PGIOForm
		jQuery('input[name=replyMsg]').val(''); //remove the replyMSG in PGIOForm
		jQuery('input[name=tid]').val(''); //remove the transaction ID in PGIOForm
		jQuery("#PGIOscreen").html('');
		//jQuery("[name=methods]").removeAttr("checked");	//reset the radio buttons
		//jQuery('input[name=pg_replycode]').val('');//remove the replycode
		//jQuery('#my_method option[value=""]').attr('selected','selected'); //reset method selectbox
	}

	jQuery(document).ready(function () {

		//jQuery('form[name=checkout]').append('<input type="hidden" name="pg_replycode" value="0000" />');
		//jQuery('form[name=checkout]').append('<input type="hidden" name="pg_method" value="" />');
		reset_form(); //reset PGIOform etc.
		if ('yes' === php_data.single_pay_method) {
			jQuery("#PGIOscreen").html('<ul class="woocommerce-message"><li>Paymethod "' + jQuery("input[name=methods]").next().text() + '"</li></ul>');
			jQuery("input[name='methods']").attr('checked', 'checked');
			jQuery('input[name=paymethod]').val(jQuery("input[name=methods]:radio").val());
		}
	});

	jQuery(window).load(function () {}); //Click Pay Button

	jQuery(".paygate").click(function (e) {
		e.preventDefault(); //return false;
		if (!jQuery("input[name='methods']").is(':checked')) {
			//alert('Nothing is checked!');	
			//jQuery("#PGIOscreen").html
			jQuery("#PGIOscreen").html('<ul class="woocommerce-error"><li>Please select a paymethod first!</li></ul>');
			jQuery('#selected_method').html('<strong style="color:red">nothing selected!<strong>');
		} else {
			//alert('One of the radio buttons is checked!');
			startPayment();
		}
	});

	//set form field "paymethod" in PGIO form according to selection
	jQuery("input[name=methods]:radio").bind("change", function (event, ui) {
		console.log('Method: ' + jQuery(this).val());
		//jQuery('#selected_method').html('Selected Method: '+jQuery(this).val());
		jQuery("#PGIOscreen").html('<ul class="woocommerce-message"><li>You selected paymethod "' + jQuery(this).next().text() + '"</li></ul>');
		//jQuery('#selected_method').html(jQuery(this).next().text());
		jQuery('input[name=paymethod]').val(jQuery(this).val());
	});

	//Debuging buttons
	//Click Toggle Button
	jQuery(".pgioform").click(function (e) {
		e.preventDefault(); //return false;
		jQuery('#PGIOForm').slideToggle("slow");
	});
	//Click Callback Button
	jQuery(".callback").click(function (e) {
		e.preventDefault(); //return false;
		callbacksuccess();
	});

	/****verifytransactionService (the wordpress way***/
	function vts() {
		var data = {
			action: 'vts',
			order_id: php_data.order_id,
			replycode: getPGIOElement('replycode'),
			paygatehash: getPGIOElement('hashResult'),
			tid:getPGIOElement('tid'),
			mb_serial_no : getPGIOElement('mb_serial_no')
		};

		jQuery.post(php_data.ajaxurl, data, function(response) {
			return response;
		});
	};
	
