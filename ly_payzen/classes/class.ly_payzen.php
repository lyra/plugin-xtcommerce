<?php
/**
 * PayZen V2-Payment Module version 1.0 (revision 65291) for xtCommerce 4.1.x.
 *
 * Copyright (C) 2014 Lyra Network and contributors
 * Support contact : support@payzen.eu
 * Author link : http://www.lyra-network.com/
 *
 * The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software
 * without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit
 * persons to whom the Software is furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @category  payment
 * @package   payzen
 * @author    Lyra Network <supportvad@lyra-network.com>
 * @copyright 2014 Lyra Network and contributors
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version   1.0 (revision 65291)
*/

defined('_VALID_CALL' ) or die('Direct Access is not allowed.');

/**
 * Main PayZen payment class (preparing and sending data form).
 */
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/payzen_api.php';
class ly_payzen {
	var $version = '1.0';
	
	public $data = array ();
	
	public $post_form = true;
	public $external = false;
	public $subpayments = false;
	public $iframe = false;
	public $pay_frame = false;
	
	public $TARGET_URL;
	public $TARGET_PARAMS = array();
	
	function pspRedirect() {
		global $xtLink, $order;
		$api = new PayzenMultiApi ();
		
		// set order parameters
		$api->set('order_id', $order->order_data['orders_id']);
	
		$payzenCurrency = $api->findCurrencyByAlphaCode($order->order_data['currency_code']);
		$api->set('currency', $payzenCurrency->num);
		
		$amount = $order->order_total['total']['plain'];
		$api->set('amount', $payzenCurrency->convertAmountToInteger($amount));
		
		$shopLanguage = $order->order_data['language_code']; // current shop language
		$payzenLanguage = $api->isSupportedLanguage($shopLanguage) ? $shopLanguage : LY_PAYZEN_DEFAULT_LANGUAGE;
		$api->set('language', $payzenLanguage);
		
		$api->set('order_info', session_name() . '=' . session_id());
		
		// customer data
		$api->set('cust_id', $order->order_customer['customers_id']);
		$api->set('cust_email', $order->order_customer['customers_email_address']);
		$api->set('cust_title', $this->_getGender($order->order_customer['customers_gender']));
		
		// billing data
		$address = $order->order_data['billing_street_address'];
		if($order->order_data['billing_suburb']) {
			$address .= ' ' . $order->order_data['billing_suburb'];
		}
		
		$api->set('cust_first_name', $order->order_data['billing_firstname']);
		$api->set('cust_last_name', $order->order_data['billing_lastname']);
		$api->set('cust_address', $address);
		$api->set('cust_zip', $order->order_data['billing_postcode']);
		$api->set('cust_city', $order->order_data['billing_city']);
		$api->set('cust_state', $order->order_data['billing_federal_state_code']);
		$api->set('cust_country', $order->order_data['billing_country_code']);
		$api->set('cust_phone', $order->order_data['billing_phone']);
		
		// delivery data
		$api->set('ship_to_first_name', $order->order_data['delivery_firstname']);
		$api->set('ship_to_last_name', $order->order_data['delivery_lastname']);
		$api->set('ship_to_street', $order->order_data['delivery_street_address']);
		$api->set('ship_to_street2', $order->order_data['delivery_suburb']);
		$api->set('ship_to_zip', $order->order_data['delivery_postcode']);
		$api->set('ship_to_city', $order->order_data['delivery_city']);
		$api->set('ship_to_state', $order->order_data['delivery_federal_state_code']);
		$api->set('ship_to_country', $order->order_data['delivery_country_code']);
		$api->set('ship_to_phone_num', $order->order_data['delivery_phone']);
		
		// set configuration parameters
		$configKeys = array(
				'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url',
				'available_languages', 'capture_delay', 'validation_mode', 'payment_cards',
				'redirect_enabled', 'redirect_success_timeout', 'redirect_success_message',
				'redirect_error_timeout', 'redirect_error_message', 'return_mode'
		);
		foreach ($configKeys as $key) {
			$value = constant('LY_PAYZEN_' . strtoupper($key));
			
			if($key === 'available_languages' || $key === 'payment_cards') {
				$value = str_replace(',', ';', $value);
			}
			
			$api->set($key, $value);
		}
		
		// activate 3ds ?
		$threedsMpi = null;
		if (LY_PAYZEN_3DS_MIN_AMOUNT != '' && $amount < LY_PAYZEN_3DS_MIN_AMOUNT) {
			$threedsMpi = '2';
		}
		$api->set('threeds_mpi', $threedsMpi);

		// return URL
		$api->set('url_return', $xtLink->_link(array('page' => 'callback', 'paction' => 'ly_payzen', 'conn' => 'SSL')));
		
		// optionally set multi payment parameters
		if($_SESSION['selected_payment_sub'] == 'multi') {
			$options = json_decode(html_entity_decode(LY_PAYZEN_MULTI_OPTIONS), true);
			$option = false;
			
			foreach ($options as $opt) {
				if($opt['id'] == $_SESSION['payzen_multi_opt']) {
					$option = $opt;
					break;
				}
			}
			
			if($option) {
				$first = $option['first'] ? $payzenCurrency->convertAmountToInteger($option['first'] / 100 * $amount) : null;
				$api->setMultiPayment(null /* to use already set amount */, $first, $option['count'], $option['period']);
				$api->set('order_info2', $option['count'] . 'X');
				
				// override cb contract
				$api->set('contracts', ($option['contract']) ? 'CB=' . $option['contract'] : null);
			}
		}
		
		
		$this->TARGET_URL = $api->platformUrl;
		$this->TARGET_PARAMS = $api->getRequestFieldsArray();
	}
	
	function _getGender($genderId) {
		$genderData = array(
				'm' => TEXT_MALE, 
				'f' => TEXT_FEMALE,
				'c' => TEXT_COMPANY_GENDER
		);
		
		return @$genderData[$genderId];
	}
}