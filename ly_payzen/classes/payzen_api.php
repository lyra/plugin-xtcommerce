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

/**
 * @package payzen
 * @author Alain Dubrulle <supportvad@lyra-network.com>
 * @copyright www.lyra-network.com
 * PHP classes to integrate an e-commerce solution with the payment platform supported by lyra-network.
 */

if(!@class_exists('PayzenApi', false)) {
	/**
	 * Class managing parameters checking, form and signature building, response analysis and more
	 * @version 2.2
	 */
	class PayzenApi {
		// **************************************
		// PROPERTIES
		// **************************************
		/**
		 * The fields to send to the PayZen platform
		 * @var array[string]PayzenField
		 * @access private
		 */
		var $requestParameters;
		/**
		 * Certificate to send in TEST mode
		 * @var string
		 * @access private
		 */
		var $keyTest;
		/**
		 * Certificate to send in PRODUCTION mode
		 * @var string
		 * @access private
		 */
		var $keyProd;
		/**
		 * Url of the payment page
		 * @var string
		 * @access private
		 */
		var $platformUrl;
		/**
		 * Set to true to send the redirect_* parameters
		 * @var boolean
		 * @access private
		 */
		var $redirectEnabled;
		/**
		 * SHA-1 authentication signature
		 * @var string
		 * @access private
		 */
		var $signature;
		/**
		 * The original data encoding.
		 * @var string
		 * @access private
		 */
		var $encoding;
		
		/**
		 * The list of categories for payment with bank accord. To be sent with the products detail if you use this payment mean.
		 * @static
		 * @var array
		 * @access public
		 */
		var $ACCORD_CATEGORIES = array("FOOD_AND_GROCERY","AUTOMOTIVE","ENTERTAINMENT","HOME_AND_GARDEN","HOME_APPLIANCE","AUCTION_AND_GROUP_BUYING","FLOWERS_AND_GIFTS","COMPUTER_AND_SOFTWARE","HEALTH_AND_BEAUTY","SERVICE_FOR_INDIVIDUAL","SERVICE_FOR_BUSINESS","SPORTS","CLOTHING_AND_ACCESSORIES","TRAVEL","HOME_AUDIO_PHOTO_VIDEO","TELEPHONY");
	
		/**
		* The list of encodings supported by this API.
		* @static
		* @var array
		* @access public
		*/
		var $SUPPORTED_ENCODINGS = array("UTF-8", "ASCII", "Windows-1252", "ISO-8859-15", "ISO-8859-1", "ISO-8859-6", "CP1256");
		
		// **************************************
		// CONSTRUCTOR
		// **************************************
		/**
		 * Constructor.
		 * Initialize request fields definitions.
		 */
		function PayzenApi($encoding="UTF-8") {
			// Initialize encoding
			$this->encoding = in_array(strtoupper($encoding), $this->SUPPORTED_ENCODINGS) ? strtoupper($encoding) : "UTF-8"; 
			
			/*
			 * Définition des paramètres de la requête
			 */
			// Common or long regexes
			$ans = "[^<>]"; // Any character (except the dreadful "<" and ">")
			$an63 = '#^[A-Za-z0-9]{0,63}$#';
			$an255 = '#^[A-Za-z0-9]{0,255}$#';
			$ans255 = '#^' . $ans . '{0,255}$#';
			$ans127 = '#^' . $ans . '{0,127}$#';
			$supzero = '[1-9]\d*';
			$regex_payment_cfg = '#^(SINGLE|MULTI:first=\d+;count=' . $supzero
					. ';period=' . $supzero . ')$#';
			$regex_trans_date = '#^\d{4}' . '(1[0-2]|0[1-9])'
					. '(3[01]|[1-2]\d|0[1-9])' . '(2[0-3]|[0-1]\d)' . '([0-5]\d){2}$#';//AAAAMMJJhhmmss
			$regex_mail = '#^[^@]+@[^@]+\.\w{2,4}$#'; //TODO plus restrictif
			$regex_params = '#^([^&=]+=[^&=]*)?(&[^&=]+=[^&=]*)*$#'; //name1=value1&name2=value2...
	
			// Déclaration des paramètres, de leur valeurs par défaut, de leur format...
			// 		$this->_addRequestField('raw_signature', 'DEBUG Signature', '#^.+$#', false);
			$this->_addRequestField('signature', 'Signature', "#^[0-9a-f]{40}$#", true);
			$this->_addRequestField('vads_action_mode', 'Action mode',
							"#^INTERACTIVE|SILENT$#", true, 11);
			$this->_addRequestField('vads_amount', 'Amount', '#^' . $supzero . '$#',
							true);
			$this->_addRequestField('vads_available_languages', 'Available languages',
							"#^(|[A-Za-z]{2}(;[A-Za-z]{2})*)$#", false, 2);
			$this->_addRequestField('vads_capture_delay', 'Capture delay', "#^\d*$#");
			$this->_addRequestField('vads_contracts', 'Contracts', $ans255);
			$this->_addRequestField('vads_contrib', 'Contribution', $ans255);
			$this->_addRequestField('vads_ctx_mode', 'Mode', "#^TEST|PRODUCTION$#",
							true);
			$this->_addRequestField('vads_currency', 'Currency', "#^\d{3}$#", true, 3);
			$this->_addRequestField('vads_cust_antecedents', 'Customer history',
							"#^NONE|NO_INCIDENT|INCIDENT$#");
			$this->_addRequestField('vads_cust_address', 'Customer address', $ans255);
			$this->_addRequestField('vads_cust_country', 'Customer country',
							"#^[A-Za-z]{2}$#", false, 2);
			$this->_addRequestField('vads_cust_email', 'Customer email', $regex_mail,
							false, 127);
			$this->_addRequestField('vads_cust_id', 'Customer id',
							$an63, false, 63);
			$this->_addRequestField('vads_cust_name', 'Customer name',
							$ans127, false, 127);
			$this->_addRequestField('vads_cust_cell_phone', 'Customer cell phone',
							$an63, false, 63);
			$this->_addRequestField('vads_cust_phone', 'Customer phone', $an63, false,
							63);
			$this->_addRequestField('vads_cust_title', 'Customer title', '#^'.$ans.'{0,63}$#', false,
							63);
			$this->_addRequestField('vads_cust_city', 'Customer city',
							'#^' . $ans . '{0,63}$#', false, 63);
			$this->_addRequestField('vads_cust_state', 'Customer state/region', '#^'.$ans.'{0,63}$#', false,
							63);
			$this->_addRequestField('vads_cust_zip', 'Customer zip code', $an63, false,
							63);
			$this->_addRequestField('vads_language', 'Language', "#^[A-Za-z]{2}$#",
							false, 2);
			$this->_addRequestField('vads_order_id', 'Order id',
							"#^[A-za-z0-9]{0,12}$#", false, 12);
			$this->_addRequestField('vads_order_info', 'Order info', $ans255);
			$this->_addRequestField('vads_order_info2', 'Order info 2', $ans255);
			$this->_addRequestField('vads_order_info3', 'Order info 3', $ans255);
			$this->_addRequestField('vads_page_action', 'Page action', "#^PAYMENT$#",
							true, 7);
			$this->_addRequestField('vads_payment_cards', 'Payment cards',
							"#^([A-Za-z0-9\-_]+;)*[A-Za-z0-9\-_]*$#", false, 127);
			$this->_addRequestField('vads_payment_config', 'Payment config',
							$regex_payment_cfg, true);
			$this->_addRequestField('vads_payment_src', 'Payment source', "#^$#", false,
							0);
			$this->_addRequestField('vads_redirect_error_message',
							'Redirection error message', $ans255, false);
			$this->_addRequestField('vads_redirect_error_timeout',
							'Redirection error timeout', $ans255, false);
			$this->_addRequestField('vads_redirect_success_message',
							'Redirection success message', $ans255, false);
			$this->_addRequestField('vads_redirect_success_timeout',
							'Redirection success timeout', $ans255, false);
			$this->_addRequestField('vads_return_mode', 'Return mode',
							"#^NONE|GET|POST?$#", false, 4);
			$this->_addRequestField('vads_return_get_params', 'GET return parameters',
							$regex_params, false);
			$this->_addRequestField('vads_return_post_params',
							'POST return parameters', $regex_params, false);
			$this->_addRequestField('vads_ship_to_name', 'Shipping name',
							'#^' . $ans . '{0,127}$#', false, 127);
			$this->_addRequestField('vads_ship_to_phone_num', 'Shipping phone',
							$ans255, false, 63);
			$this->_addRequestField('vads_ship_to_street', 'Shipping street', $ans127,
							false, 127);
			$this->_addRequestField('vads_ship_to_street2', 'Shipping street (2)',
							$ans127, false, 127);
			$this->_addRequestField('vads_ship_to_state', 'Shipping state', $an63,
							false, 63);
			$this->_addRequestField('vads_ship_to_country', 'Shipping country',
							"#^[A-Za-z]{2}$#", false, 2);
			$this->_addRequestField('vads_ship_to_city', 'Shipping city',
							'#^' . $ans . '{0,63}$#', false, 63);
			$this->_addRequestField('vads_ship_to_zip', 'Shipping zip code', $an63,
							false, 63);
			$this->_addRequestField('vads_shop_name', 'Shop name', $ans127);
			$this->_addRequestField('vads_shop_url', 'Shop url', $ans127);
			$this->_addRequestField('vads_site_id', 'Site id', "#^\d{8}$#", true, 8);
			$this->_addRequestField('vads_theme_config', 'Theme', $ans255);
			$this->_addRequestField('vads_trans_date', 'Transaction date',
							$regex_trans_date, true, 14);
			$this->_addRequestField('vads_trans_id', 'Transaction id',
							"#^[0-8]\d{5}$#", true, 6);
			$this->_addRequestField('vads_url_success', 'Success url', $ans127, false,
							127);
			$this->_addRequestField('vads_url_referral', 'Referral url', $ans127,
							false, 127);
			$this->_addRequestField('vads_url_refused', 'Refused url', $ans127, false,
							127);
			$this->_addRequestField('vads_url_cancel', 'Cancel url', $ans127, false,
							127);
			$this->_addRequestField('vads_url_error', 'Error url', $ans127, false, 127);
			$this->_addRequestField('vads_url_return', 'Return url', $ans127, false,
							127);
			$this->_addRequestField('vads_user_info', 'User info', $ans255);
			$this->_addRequestField('vads_validation_mode', 'Validation mode',
							"#^[01]?$#", false, 1);
			$this->_addRequestField('vads_version', 'Gateway version', "#^V2$#", true,
							2);
			
			// Credit Card info
			$this->_addRequestField('vads_card_number', 'Card number', "#^\d{13,19}$#");
			$this->_addRequestField('vads_cvv', 'Card verification number', "#^\d{3,4}$#");
			$this->_addRequestField('vads_expiry_year', 'Year of card expiration', "#^20[0-9]{2}$#");
			$this->_addRequestField('vads_expiry_month', 'Month of card expiration', "#^\d[0-2]{1}$#");
			
			// Enable / disable 3D Secure 
			$this->_addRequestField('vads_threeds_mpi', 'Enable / disable 3D Secure', '#^[0-2]$#', false);
			
			// Declaration of parameters for Oney payment
			$this->_addRequestField('vads_cust_first_name', 'Customer first name', $an63, false, 63);
			$this->_addRequestField('vads_cust_last_name', 'Customer last name', $an63, false, 63);
			$this->_addRequestField('vads_cust_status', 'Customer status (private or company)', "#^PRIVATE|COMPANY$#", false, 7);
			
			$this->_addRequestField('vads_ship_to_first_name', 'Shipping first name', $an63, false, 63);
			$this->_addRequestField('vads_ship_to_last_name', 'Shipping last name', $an63, false, 63);
			$this->_addRequestField('vads_ship_to_status', 'Shipping status (private or company)', "#^PRIVATE|COMPANY$#", false, 7);
			$this->_addRequestField('vads_ship_to_delivery_company_name', 'Name of the delivery company', $ans127, false, 127);
			$this->_addRequestField('vads_ship_to_speed', 'Speed of the shipping method', "#^STANDARD|EXPRESS$#", false, 8);
			$this->_addRequestField('vads_ship_to_type', 'Type of the shipping method', 
							"#^RECLAIM_IN_SHOP|RELAY_POINT|RECLAIM_IN_STATION|PACKAGE_DELIVERY_COMPANY|ETICKET$#", false, 24);
			
			$this->_addRequestField('vads_insurance_amount', 'The amount of insurance', '#^' . $supzero . '$#', false, 12);
			$this->_addRequestField('vads_tax_amount', 'The amount of tax', '#^' . $supzero . '$#', false, 12);
			$this->_addRequestField('vads_shipping_amount', 'The amount of shipping', '#^' . $supzero . '$#', false, 12);
			$this->_addRequestField('vads_nb_products', 'Number of products', '#^' . $supzero . '$#', false);
			
			// Set some default parameters
			$this->set('vads_version', 'V2');
			$this->set('vads_page_action', 'PAYMENT');
			$this->set('vads_action_mode', 'INTERACTIVE');
			$this->set('vads_payment_config', 'SINGLE');
			$timestamp = time();
			$this->set('vads_trans_id', $this->_generateTransId($timestamp));
			$this->set('vads_trans_date', gmdate('YmdHis', $timestamp));
		}
	
		/**
		 * Generate a trans_id.
		 * To be independent from shared/persistent counters, we use the number of 1/10seconds since midnight,
		 * which has the appropriate format (000000-899999) and has great chances to be unique.
		 * @return string the generated trans_id
		 * @access private
		 */
		function _generateTransId($timestamp) {
			list($usec, $sec) = explode(" ", microtime()); // microseconds, php4 compatible
			$temp = ($timestamp + $usec - strtotime('today 00:00')) * 10;
			$temp = sprintf('%06d', $temp);
	
			return $temp;
		}
	
		/**
		 * Shortcut function used in constructor to build requestParameters
		 * @param string $name
		 * @param string $label
		 * @param string $regex
		 * @param boolean $required
		 * @param mixed $value
		 * @return boolean true on success
		 * @access private
		 */
		function _addRequestField($name, $label, $regex, $required = false,
				$length = 255, $value = null) {
			$this->requestParameters[$name] = new PayzenField($name, $label, $regex,
					$required, $length);
			
			if($value !== null) {
				return $this->set($name, $value);
			} else {
				return true;
			}		
		}
	
		// **************************************
		// INTERNATIONAL FUNCTIONS
		// **************************************
	
		/**
		 * Returns an array of languages accepted by the PayZen payment page
		 * @static
		 * @return array[string]string
		 */
		function getSupportedLanguages() {
			return array(
				'fr' => 'French', 'de' => 'German', 'en' => 'English', 'es' => 'Spanish', 'zh' => 'Chinese', 'it' => 'Italian', 'ja' => 'Japanese', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'sv' => 'Swedish', 'ru' => 'Russian'
			);
		}
		
		/**
		 * Returns true if the entered language is supported
		 * @static
		 * @param string $lang
		 * @return boolean
		 */
		function isSupportedLanguage($lang) {
			foreach ($this->getSupportedLanguages() as $code => $label) {
				if($code == strtolower($lang)) {
					return true;
				}
			}
			
			return false;
		}
	
		/**
		 * Return the list of currencies recognized by the PayZen platform
		 * @static
		 * @return array[int]PayzenCurrency 
		 */
		function getSupportedCurrencies() {
			$currencies = array(
				array('ARS', 32, 2), array('AUD', 36, 2), array('KHR', 116, 0), array('CAD', 124, 2), array('CNY', 156, 1), array('HRK', 191, 2), array('CZK', 203, 2), array('DKK', 208, 2), array('EKK', 233, 2), array('HKD', 344, 2), array('HUF', 348, 2), array('ISK', 352, 0), array('IDR', 360, 0), array('JPY', 392, 0), array('KRW', 410, 0), array('LVL', 428, 2), array('LTL', 440, 2), array('MYR', 458, 2), array('MXN', 484, 2), array('NZD', 554, 2), array('NOK', 578, 2), array('PHP', 608, 2), array('RUB', 643, 2), array('SGD', 702, 2), array('ZAR', 710, 2), array('SEK', 752, 2), array('CHF', 756, 2), array('THB', 764, 2), array('GBP', 826, 2), array('USD', 840, 2), array('TWD', 901, 1), array('RON', 946, 2), array('TRY', 949, 2), array('XOF', 952, 0), array('BGN', 975, 2), array('EUR', 978, 2), array('PLN', 985, 2), array('BRL', 986, 2)
			);
			
			$payzenCurrencies = array();
			
			foreach($currencies as $currency) {
				$payzenCurrencies[] = new PayzenCurrency($currency[0], $currency[1], $currency[2]);
			}
			
			return $payzenCurrencies;
		}
	
		/**
		 * Return a currency from its iso 3-letters code
		 * @static
		 * @param string $alpha3
		 * @return PayzenCurrency
		 */
		function findCurrencyByAlphaCode($alpha3) {
			$list = $this->getSupportedCurrencies();
			foreach ($list as $currency) {
				/** @var PayzenCurrency $currency */
				if ($currency->alpha3 == $alpha3) {
					return $currency;
				}
			}
			return null;
		}
	
		/**
		 * Returns a currency form its iso numeric code
		 * @static
		 * @param int $num
		 * @return PayzenCurrency
		 */
		function findCurrencyByNumCode($numeric) {
			$list = $this->getSupportedCurrencies();
			foreach ($list as $currency) {
				/** @var PayzenCurrency $currency */
				if ($currency->num == $numeric) {
					return $currency;
				}
			}
			return null;
		}
	
		/**
		 * Returns a currency numeric code from its 3-letters code
		 * @static
		 * @param string $alpha3
		 * @return int
		 */
		function getCurrencyNumCode($alpha3) {
			$currency = $this->findCurrencyByAlphaCode($alpha3);
			return is_a($currency, 'PayzenCurrency') ? $currency->num : null;
		}
		
		/**
		 * Returns an array of card types accepted by the PayZen payment platform
		 * @static
		 * @return array[string]string
		 */
		function getSupportedCardTypes() {
			return array(
				'CB' => 'CB', 'VISA' => 'Visa', 'VISA_ELECTRON' => 'Visa Electron', 'MASTERCARD' => 'Mastercard', 'MAESTRO' => 'Maestro', 'E-CARTEBLEUE' => 'E-Carte bleue', 'AMEX' => 'American Express', 'ACCORD_STORE' => 'Carte Privative Accord', 'ACCORD_STORE_SB' => 'Carte Privative Accord (sandbox)', 'ALINEA' => 'Carte Alinéa', 'ALINEA_CDX' => 'Carte Cadeau Alinéa', 'ALINEA_CDX_SB' => 'Carte Cadeau Alinéa (sandbox)', 'ALINEA_SB' => 'Carte Alinéa (sandbox)', 'ONEY' => 'Oney', 'ONEY_SANDBOX' => 'Oney (sandbox)', 'AURORE-MULTI' => 'Aurore Multipaiements', 'BIZZBEE_CDX' => 'Carte Cadeau Bizzbee', 'BIZZBEE_CDX_SB' => 'Carte Cadeau Bizzbee (sandbox)', 'BOULANGER' => 'Carte Boulanger', 'BOULANGER_SB' => 'Carte Boulanger (sandbox)', 'BRICE_CDX' => 'Carte Cadeau Brice', 'BRICE_CDX_SB' => 'Carte Cadeau Brice (sandbox)', 'BUT' => 'But', 'BUYSTER' => 'Buyster', 'CASINO' => 'Banque Casino', 'CDGP' => 'Carte Privilège', 'CDISCOUNT' => 'CDiscount', 'COF3XCB' => 'Cofinoga 3 fois CB', 'COF3XCB_SB' => 'Cofinoga 3 fois CB Sandbox', 'COFINOGA' => 'Cofinoga', 'CONFORAMA' => 'Conforama', 'DINERS' => 'Diners', 'ILLICADO' => 'Carte Illicado', 'ILLICADO_SB' => 'Carte Illicado (sandbox)', 'JCB' => 'JCB', 'LECLERC' => 'Carte Reglo', 'LEROY-MERLIN' => 'Carte Leroy Merlin','LEROY-MERLIN_SB' => 'Carte Leroy Merlin (sandbox)', 'NORAUTO_SB' => 'Carte Norauto (sandbox)', 'PAYPAL' => 'Paypal', 'PAYPAL_SB' => 'Paypal Sandbox', 'PAYSAFECARD' => 'PaysafeCard', 'S-MONEY' => 'S-money', 'SOFICARTE' => 'Soficarte', 'SOFINCO' => 'Sofinco', 'SYGMA' => 'Sygma', 'TRUFFAUT_CDX' => 'Carte Cadeau Truffaut', 'V_ME' => 'V.me by Visa', 'SDD' => 'Prélèvement SEPA'
			);
		}
	
		// **************************************
		// GETTERS/SETTERS
		// **************************************
		/**
		 * Shortcut for setting multiple values with one array
		 * @param array[string]mixed $parameters
		 * @return boolean true on success
		 */
		function setFromArray($parameters) {
			$ok = true;
			foreach ($parameters as $name => $value) {
				$ok &= $this->set($name, $value);
			}
			return $ok;
		}
	
		/**
		 * General getter.
		 * Retrieve an api variable from its name. Automatically add 'vads_' to the name if necessary.
		 * Example : <code><?php $siteId = $api->get('site_id'); ?></code>
		 * @param string $name
		 * @return mixed null if $name was not recognised
		 */
		function get($name) {
			if (!$name || !is_string($name)) {
				return null;
			}
	
			// V1/shortcut notation compatibility
			$name = (substr($name, 0, 5) != 'vads_') ? 'vads_' . $name : $name;
	
			if ($name == 'vads_key_test') {
				return $this->keyTest;
			} elseif ($name == 'vads_key_prod') {
				return $this->keyProd;
			} elseif ($name == 'vads_platform_url') {
				return $this->platformUrl;
			} elseif ($name == 'vads_redirect_enabled') {
				return $this->redirectEnabled;
			} elseif (array_key_exists($name, $this->requestParameters)) {
				return $this->requestParameters[$name]->getValue();
			} else {
				return null;
			}
		}
	
		/**
		 * General setter.
		 * Set an api variable with its name and the provided value. Automatically add 'vads_' to the name if necessary.
		 * Example : <code><?php $api->set('site_id', '12345678'); ?></code>
		 * @param string $name
		 * @param mixed $value
		 * @return boolean true on success
		 */
		function set($name, $value) {
			if (!$name || !is_string($name)) {
				return false;
			}
	
			// V1/shortcut notation compatibility
			$name = (substr($name, 0, 5) != 'vads_') ? 'vads_' . $name : $name;
	
			// Convert the parameters if they are not encoded in utf8
			if($this->encoding !== "UTF-8") {
				$value = iconv($this->encoding, "UTF-8", $value);
			}
			
			// Search appropriate setter
			if ($name == 'vads_key_test') {
				return $this->setCertificate($value, 'TEST');
			} elseif ($name == 'vads_key_prod') {
				return $this->setCertificate($value, 'PRODUCTION');
			} elseif ($name == 'vads_platform_url') {
				return $this->setPlatformUrl($value);
			} elseif ($name == 'vads_redirect_enabled') {
				return $this->setRedirectEnabled($value);
			} elseif (array_key_exists($name, $this->requestParameters)) {
				return $this->requestParameters[$name]->setValue($value);
			} else {
				return false;
			}
		}
	
		/**
		 * Set target url of the payment form
		 * @param string $url
		 * @return boolean
		 */
		function setPlatformUrl($url) {
			if (!preg_match('#https?://([^/]+/)+#', $url)) {
				return false;
			}
			$this->platformUrl = $url;
			return true;
		}
	
		/**
		 * Enable/disable redirect_* parameters
		 * @param mixed $enabled false, '0', a null or negative integer or 'false' to disable
		 * @return boolean
		 */
		function setRedirectEnabled($enabled) {
			$this->redirectEnabled = !(!$enabled || $enabled == '0'
					|| strtolower($enabled) == 'false');
			return true;
		}
	
		/**
		 * Set TEST or PRODUCTION certificate
		 * @param string $key
		 * @param string $mode
		 * @return boolean true if the certificate could be set
		 */
		function setCertificate($key, $mode) {
			// Check format
			if (!preg_match('#^\d{16}$#', $key)) {
				return false;
			}
	
			if ($mode == 'TEST') {
				$this->keyTest = $key;
			} elseif ($mode == 'PRODUCTION') {
				$this->keyProd = $key;
			} else {
				return false;
			}
			return true;
		}
		
		/**
		* Add product infos as request parameters.
		* @param string $label
		* @param int $amount
		* @param int $qty
		* @param string $ref
		* @param string $type
		* @return boolean true if product infos are set correctly
		*/
		function addProductRequestField($label, $amount, $qty, $ref, $type) {
			$index = $this->get("nb_products") ? $this->get("nb_products") : 0;
			
			$ok = true;
			
			// Add product infos as request parameters 
			$ok &= $this->_addRequestField("vads_product_label" . $index, "Product label", '#^[^<>"+-]{0,255}$#', false, 255, $label);
			$ok &= $this->_addRequestField("vads_product_amount" . $index, "Product amount", '#^[1-9]\d*$#', false, 12, $amount);
			$ok &= $this->_addRequestField("vads_product_qty" . $index, "Product quantity", '#^[1-9]\d*$#', false, 255, $qty);
			$ok &= $this->_addRequestField("vads_product_ref" . $index, "Product reference", '#^[A-Za-z0-9]{0,64}$#', false, 64, $ref);
			$ok &= $this->_addRequestField("vads_product_type" . $index, "Product type", "#^".implode("|", $this->ACCORD_CATEGORIES)."$#", 
				false, 30, $type);
			
			// Increment the number of products
			$ok &= $this->set("nb_products", $index + 1);
			
			return $ok;
		}
		
		/**
		* Add extra info as a request parameter.
		* @param string $key
		* @param string $value
		* @return boolean true if extra info is set correctly
		*/
		function addExtInfoRequestField($key, $value) {
			return $this->_addRequestField("vads_ext_info_" . $key, "Extra info " . $key, '#^.{0,255}$#', false, 255, $value);
		}
		
	
		/**
		 * Return certificate according to current mode, false if mode was not set
		 * @return string|boolean
		 */
		function getCertificate() {
			switch ($this->requestParameters['vads_ctx_mode']
					->getValue()) {
				case 'TEST':
					return $this->keyTest;
					break;
	
				case 'PRODUCTION':
					return $this->keyProd;
					break;
	
				default:
					return false;
					break;
			}
		}
	
		/**
		 * Generate signature from a list of PayzenField
		 * @param array[string]PayzenField $fields
		 * @return string
		 * @access private
		 */
		function _generateSignatureFromFields($fields = null, $hashed = true) {
			$params = array();
			$fields = ($fields !== null) ? $fields : $this->requestParameters;
			foreach ($fields as $field) {
				if ($field->isRequired() || $field->isFilled()) {
					$params[$field->getName()] = $field->getValue();
				}
			}
			return $this->sign($params, $this->getCertificate(), $hashed);
		}
	
		/**
		 * Public static method to compute a PayZen signature. Parameters must be in utf-8.
		 * @param array[string]string $parameters payment gateway request/response parameters
		 * @param string $key shop certificate
		 * @param boolean $hashed set to false to get the raw, unhashed signature
		 * @access public
		 * @static
		 */
		function sign($parameters, $key, $hashed = true) {
			$signContent = "";
			ksort($parameters);
			foreach ($parameters as $name => $value) {
				if (substr($name, 0, 5) == 'vads_') {
					$signContent .= $value . '+';
				}
			}
			$signContent .= $key;
			$sign = $hashed ? sha1($signContent) : $signContent;
			return $sign;
		}
	
		// **************************************
		// REQUEST PREPARATION FUNCTIONS
		// **************************************
		/**
		 * Unset the value of optionnal fields if they are unvalid
		 */
		function clearInvalidOptionnalFields() {
			$fields = $this->getRequestFields();
			foreach ($fields as $field) {
				if (!$field->isValid() && !$field->isRequired()) {
					$field->setValue(null);
				}
			}
		}
		
		/**
		 * Check all payment fields
		 * @param array $errors will be filled with the name of invalid fields
		 * @return boolean
		 */
		function isRequestReady(&$errors = null) {
			$errors = is_array($errors) ? $errors : array();
			$fields = $this->getRequestFields();
			foreach ($fields as $field) {
				if (!$field->isValid()) {
					$errors[] = $field->getName();
				}
			}
			return sizeof($errors) == 0;
		}
	
		/**
		 * Return the list of fields to send to the payment gateway
		 * @return array[string]PayzenField a list of PayzenField or false if a parameter was invalid
		 * @see PayzenField
		 */
		function getRequestFields() {
			$fields = $this->requestParameters;
	
			// Filter redirect_parameters if redirect is disabled
			if (!$this->redirectEnabled) {
				$redirectFields = array(
						'vads_redirect_success_timeout',
						'vads_redirect_success_message',
						'vads_redirect_error_timeout',
						'vads_redirect_error_message');
				foreach ($redirectFields as $fieldName) {
					unset($fields[$fieldName]);
				}
			}
	
			foreach ($fields as $fieldName => $field) {
				if (!$field->isFilled() && !$field->isRequired()) {
					unset($fields[$fieldName]);
				}
			}
	
			// Compute signature
			$fields['signature']->setValue($this->_generateSignatureFromFields($fields));
	
			// Return the list of fields
			return $fields;
		}
	
		/**
		 * Return the url of the payment page with urlencoded parameters (GET-like url)
		 * @return boolean|string
		 */
		function getRequestUrl() {
			$fields = $this->getRequestFields();
	
			$url = $this->platformUrl . '?';
			foreach ($fields as $field) {
				if ($field->isFilled()) {
					$url .= $field->getName() . '=' . rawurlencode($field->getValue())
							. '&';
				}
			}
			$url = substr($url, 0, -1); // remove last &
			return $url;
		}
	
		/**
		 * Return the html form to send to the payment gateway
		 * @param string $enteteAdd
		 * @param string $inputType
		 * @param string $buttonValue
		 * @param string $buttonAdd
		 * @param string $buttonType
		 * @return string
		 */
		function getRequestHtmlForm($enteteAdd = '', $inputType = 'hidden',
				$buttonValue = 'Aller sur la plateforme de paiement', $buttonAdd = '',
				$buttonType = 'submit') {
	
			$html = "";
			$html .= '<form action="' . $this->platformUrl . '" method="POST" '
					. $enteteAdd . '>';
			$html .= "\n";
			$html .= $this->getRequestFieldsHtml('type="' . $inputType . '"');
			$html .= '<input type="' . $buttonType . '" value="' . $buttonValue . '" '
					. $buttonAdd . '/>';
			$html .= "\n";
			$html .= '</form>';
			return $html;
		}
	
		/**
		 * Return the html code of the form fields to send to the payment page
		 * @param string $inputAttributes
		 * @return string
		 */
		function getRequestFieldsHtml($inputAttributes = 'type="hidden"') {
			$fields = $this->getRequestFields();
			
			$html = '';
			$format = '<input name="%s" value="%s" ' . $inputAttributes . "/>\n";
			foreach ($fields as $field) {
				if ($field->isFilled()) {
					// Convert special chars to HTML entities to avoid data troncation
					$value = htmlspecialchars($field->getValue(), ENT_QUOTES, 'UTF-8');
					
					$html .= sprintf($format, $field->getName(), $value);
				}
			}
			return $html;
		}
		
		/**
		 * Return the html fields to send to the payment page as a key/value array 
		 * @return array[string][string]
		 */
		function getRequestFieldsArray() {
			$fields = $this->getRequestFields();
				
			$result = array();
			foreach ($fields as $field) {
				if ($field->isFilled()) {
					// Convert special chars to HTML entities to avoid data troncation
					$result[$field->getName()] = htmlspecialchars($field->getValue(), ENT_QUOTES, 'UTF-8');
				}
			}
			
			return $result;
		}
	
		/**
		 * PHP is not yet a sufficiently advanced technology to be indistinguishable from magic...
		 * so don't use magic_quotes, they mess up with the gateway response analysis.
		 * 
		 * @param array $potentiallyMagicallyQuotedData
		 */
		function uncharm($potentiallyMagicallyQuotedData) {
			if (get_magic_quotes_gpc()) {
				$sane = array();
				foreach ($potentiallyMagicallyQuotedData as $k => $v) {
					$saneKey = stripslashes($k);
					$saneValue = is_array($v) ? $this->uncharm($v) : stripslashes($v);
					$sane[$saneKey] = $saneValue;
				}
			} else {
				$sane = $potentiallyMagicallyQuotedData;
			}
			return $sane;
		}
	}

}

if(!@class_exists('PayzenResponse', false)) {
	/**
	 * Class representing the result of a transaction (sent by the check url or by the client return)
	 */
	class PayzenResponse {
		/**
		 * Raw response parameters array
		 * @var array
		 * @access private
		 */
		var $raw_response = array();
		/**
		 * Certificate used to check the signature
		 * @see PayzenApi::sign
		 * @var boolean
		 * @access private
		 */
		var $certificate;
		/**
		 * Value of vads_result
		 * @var string
		 * @access private
		 */
		var $code;
		/**
		 * Translation of $code (vads_result)
		 * @var string
		 * @access private
		 */
		var $message;
		/**
		 * Value of vads_extra_result
		 * @var string
		 * @access private
		 */
		var $extraCode;
		/**
		 * Translation of $extraCode (vads_extra_result)
		 * @var string
		 * @access private
		 */
		var $extraMessage;
		/**
		 * Value of vads_auth_result
		 * @var string
		 * @access private
		 */
		var $authCode;
		/**
		 * Translation of $authCode (vads_auth_result)
		 * @var string
		 * @access private
		 */
		var $authMessage;
		/**
		 * Value of vads_warranty_result
		 * @var string
		 * @access private
		 */
		var $warrantyCode;
		/**
		 * Translation of $warrantyCode (vads_warranty_result)
		 * @var string
		 * @access private
		 */
		var $warrantyMessage;
		/**
		 * Transaction status (vads_trans_status)
		 * @var string
		 * @access private
		 */
		var $transStatus;
		
		/**
		 * Internal reference to PayzenApi for using util methods
		 * @var PayzenApi
		 * @access private
		 */
		var $api;
		
		/**
		 * Constructor for PayzenResponse class. Prepare to analyse check url or return url call.
		 * @param array[string]string $parameters $_REQUEST by default
		 * @param string $ctx_mode
		 * @param string $key_test
		 * @param string $key_prod
		 * @param string $encoding
		 */
		function PayzenResponse($parameters = null, $ctx_mode = null, $key_test = null, $key_prod = null) {
			$this->api = new PayzenApi(); // Use default API encoding (UTF-8) since the payment platform returns UTF-8 data 
			
			if(is_null($parameters)) {
				$parameters = $_REQUEST;
			}
			$parameters = $this->api->uncharm($parameters);
				
			// Load site credentials if provided
			if (!is_null($ctx_mode)) {
				$this->api->set('vads_ctx_mode', $ctx_mode);
			}
			if (!is_null($key_test)) {
				$this->api->set('vads_key_test', $key_test);
			}
			if (!is_null($key_prod)) {
				$this->api->set('vads_key_prod', $key_prod);
			}
			
			$this->load($parameters, $this->api->getCertificate());
		}
		
		/**
		 * Load response codes and translations from a parameter array.
		 * @param array[string]string $raw
		 * @param boolean $authentified
		 */
		function load($params, $certificate) {
			$this->raw_response = is_array($params) ? $params : array();
			$this->certificate = $certificate;
			
			// get codes
			$this->code = $this->_findInArray('vads_result', $this->raw_response, null);
			$this->extraCode = $this->_findInArray('vads_extra_result', $this->raw_response, null);
			$this->authCode = $this->_findInArray('vads_auth_result', $this->raw_response, null);
			$this->warrantyCode = $this->_findInArray('vads_warranty_code', $this->raw_response, null);
			
			$this->transStatus = $this->_findInArray('vads_trans_status', $this->raw_response, null);
			
			// load translations for used/default language
			$lang = $this->get('language');
			if(!key_exists($lang, self::$RESPONSE_TRANS)) {
				$lang = 'fr';
			}
			$translations = self::$RESPONSE_TRANS[$lang];
			
			// result message
			$code = ($this->code == null) ? 'empty' : $this->code;
			$this->message = $this->_findInArray($code, $translations['results'], self::UNKNOWN_TRANS);
			
			// extra result message
			if($code == '30') {
				$extraCode = ($this->extraCode == null) ? '99' /* OTHER */ : $this->extraCode;
				$this->extraMessage = $this->_findInArray($extraCode, self::$FORM_ERRORS, self::UNKNOWN_TRANS);
			} elseif($code == '05' || $code == '00') {
				$extraCode = ($this->extraCode == null) ? 'empty' : $this->extraCode;
				$this->extraMessage = $this->_findInArray($extraCode, $translations['extra_results'], self::UNKNOWN_TRANS);
			}
			
			// auth result message
			$authCode = ($this->authCode == null) ? 'empty' : $this->authCode;
			$this->authMessage = $this->_findInArray($authCode, $translations['auth_results'], self::UNKNOWN_TRANS);
			
			// warranty result message
			$warrantyCode = ($this->warrantyCode == null) ? 'empty' : $this->warrantyCode;
			$this->warrantyMessage = $this->_findInArray($warrantyCode, $translations['warranty_results'], self::UNKNOWN_TRANS);
		}
	
		/**
		 * Check response signature
		 * @return boolean
		 */
		function isAuthentified() {
			return $this->api->sign($this->raw_response, $this->certificate)
					== $this->getSignature();
		}
	
		/**
		 * Return the signature computed from the received parameters, for log/debug purposes.
		 * @param boolean $hashed apply sha1, false by default
		 * @return string
		 */
		function getComputedSignature($hashed = false) {
			return $this->api->sign($this->raw_response, $this->certificate, $hashed);
		}
	
		/**
		 * Check if the payment was successful (waiting confirmation or captured)
		 * @return boolean
		 */
		function isAcceptedPayment() {
			return $this->isPendingPayment() 
					|| $this->transStatus == 'AUTHORISED' 
							|| $this->transStatus == 'AUTHORISED_TO_VALIDATE' 
									|| $this->transStatus == 'CAPTURED' 
											|| $this->transStatus == 'CAPTURE_FAILED' /* capture will be redone */;
		}
	
		/**
		 * Check if the payment is waiting confirmation (successful but the amount has not been transfered and is not yet guaranteed)
		 * @return boolean
		 */
		function isPendingPayment() {
			return $this->transStatus == 'INITIAL'
					|| $this->transStatus == 'WAITING_AUTHORISATION'
							|| $this->transStatus == 'WAITING_AUTHORISATION_TO_VALIDATE'
									|| $this->transStatus == 'UNDER_VERIFICATION';
		}
	
		/**
		 * Check if the payment process was interrupted by the client
		 * @return boolean
		 */
		function isCancelledPayment() {
			return $this->transStatus == 'CANCELLED' || $this->transStatus == 'ABANDONED';
		}
	
		/**
		 * Return the value of a response parameter.
		 * @param string $name
		 * @return string
		 */
		function get($name) {
			// Manage shortcut notations by adding 'vads_'
			$name = (substr($name, 0, 5) != 'vads_') ? 'vads_' . $name : $name;
			
			return @$this->raw_response[$name];
		}
		
		/**
		* Shortcut for getting ext_info_* fields. 
		* @param string $key 
		* @return string
		*/
		function getExtInfo($key) {
			return $this->get("ext_info_$key");
		}
		
		/**
		* Returns the expected signature received from gateway.
		* @return string
		*/
		function getSignature() {
			return @$this->raw_response['signature'];
		}
	
		/**
		 * Return the paid amount converted from cents (or currency equivalent) to a decimal value
		 * @return float
		 */
		function getFloatAmount() {
			$currency = $this->api->findCurrencyByNumCode($this->get('currency'));
			return $currency->convertAmountToFloat($this->get('amount'));
		}
	
		/**
		 * Return a short description of the payment result, useful for logging
		 * @return string
		 */
		function getLogString() {
			$log = $this->message . ' (' . $this->code . ').';
			
			if(is_numeric($this->extraCode) && 
				(
					($this->code == '30') || 
					($this->code == '00' && intval($this->extraCode) > 0) || 
					($this->code == '05' && intval($this->extraCode) > 0)
				)
			) {
				$log .= ' ' . $this->extraMessage . ' (' . $this->extraCode . ').';
			}
			
			return $log;
		}
	
		/**
		 * Return a formatted string to output as a response to the check url call
		 * @param string $case shortcut code for current situations. Most useful : payment_ok, payment_ko, auth_fail
		 * @param string $extraMessage some extra information to output to the payment gateway
		 * @return string
		 */
		function getOutputForGateway($case = '', $extraMessage = '', $originalEncoding="UTF-8") {
			// predefined response messages according to case
			$cases = array(
					'payment_ok' => array(true, "Paiement valide traité"),
					'payment_ko' => array(true, "Paiement invalide traité"),
					'payment_ok_already_done' => array(true, "Paiement valide traité, déjà enregistré"),
					'payment_ko_already_done' => array(true, "Paiement invalide traité, déjà enregistré"),
					'order_not_found' => array(false, "Impossible de retrouver la commande"),
					'payment_ko_on_order_ok' => array(false, "Code paiement invalide reçu pour une commande déjà validée"),
					'auth_fail' => array(false, "Echec d\'authentification"),
					'ok' => array(true, ''),
					'ko' => array(false, '')
			);
	
			$success = key_exists($case, $cases) ? $cases[$case][0] : false;
			$message = key_exists($case, $cases) ? $cases[$case][1] : '';
	
			if(!empty($extraMessage)) {
				$message .= ' ' . $extraMessage;
			}
			$message = str_replace("\n", ' ', $message);
			
			// Set original CMS encoding to convert if necessary response to send to platform
			$encoding = in_array(strtoupper($originalEncoding), $this->api->SUPPORTED_ENCODINGS) ? strtoupper($originalEncoding) : "UTF-8";
			if($encoding !== "UTF-8") {
				$message = iconv($encoding, "UTF-8", $message);
			}
	
			$response = '';
			$response .= '<span style="display:none">';
			$response .= $success ? "OK-" : "KO-";
			$response .= $this->get('trans_id');
			$response .= "=$message\n";
			$response .= '</span>';
			return $response;
		}
	
		/**
		 * Private shortcut function
		 * @param string $value
		 * @param array[string]string $translations
		 * @param string $defaultTransation
		 * @access private
		 */
		function _findInArray($key, $array, $default) {
			if (is_array($array) && array_key_exists($key, $array)) {
				return $array[$key];
			}
			return $default;
		}
		
		const UNKNOWN_TRANS = '';
		
		/**
		 * Associative array containing human-readable translations of response codes.
		 * 
		 * @var array
		 * @access private
		 */
		static $RESPONSE_TRANS = array (
				'fr' => array (
						'results' => array (
								'empty' => '',
								'00' => 'Paiement réalisé avec succès',
								'02' => 'Le commerçant doit contacter la banque du porteur',
								'05' => 'Paiement refusé',
								'17' => 'Annulation client',
								'30' => 'Erreur de format de la requête',
								'96' => 'Erreur technique lors du paiement' 
						),
						'extra_results' => array (
								'empty' => 'Pas de contrôle effectué',
								'00' => 'Tous les contrôles se sont déroulés avec succès',
								'02' => 'La carte a dépassé l\'encours autorisé',
								'03' => 'La carte appartient à la liste grise du commerçant',
								'04' => 'Le pays d\'émission de la carte appartient à la liste grise du commerçant',
								'05' => 'L\'adresse IP appartient à la liste grise du commerçant',
								'06' => 'Le code BIN appartient à la liste grise du commerçant',
								'07' => 'Détection d\'une e-carte bleue',
								'08' => 'Détection d\'une carte commerciale nationale',
								'09' => 'Détection d\'une carte commerciale étrangère',
								'14' => 'La carte est à autorisation systématique',
								'20' => 'Aucun pays ne correspond (pays IP, pays carte, pays client)',
								'30' => 'Le pays de l\'adresse IP appartient à la liste grise',
								'99' => 'Problème technique rencontré par le serveur lors du traitement d\'un des contrôles locaux' 
						),
						'auth_results' => array (
								'empty' => '',
								'00' => 'Transaction approuvée ou traitée avec succès',
								'02' => 'Contacter l\'émetteur de carte',
								'03' => 'Accepteur invalide',
								'04' => 'Conserver la carte',
								'05' => 'Ne pas honorer',
								'07' => 'Conserver la carte, conditions spéciales',
								'08' => 'Approuver après identification',
								'12' => 'Transaction invalide',
								'13' => 'Montant invalide',
								'14' => 'Numéro de porteur invalide',
								'30' => 'Erreur de format',
								'31' => 'Identifiant de l\'organisme acquéreur inconnu',
								'33' => 'Date de validité de la carte dépassée',
								'34' => 'Suspicion de fraude',
								'41' => 'Carte perdue',
								'43' => 'Carte volée',
								'51' => 'Provision insuffisante ou crédit dépassé',
								'54' => 'Date de validité de la carte dépassée',
								'56' => 'Carte absente du fichier',
								'57' => 'Transaction non permise à ce porteur',
								'58' => 'Transaction interdite au terminal',
								'59' => 'Suspicion de fraude',
								'60' => 'L\'accepteur de carte doit contacter l\'acquéreur',
								'61' => 'Montant de retrait hors limite',
								'63' => 'Règles de sécurité non respectées',
								'68' => 'Réponse non parvenue ou reçue trop tard',
								'90' => 'Arrêt momentané du système',
								'91' => 'Emetteur de cartes inaccessible',
								'96' => 'Mauvais fonctionnement du système',
								'94' => 'Transaction dupliquée',
								'97' => 'Echéance de la temporisation de surveillance globale',
								'98' => 'Serveur indisponible routage réseau demandé à nouveau',
								'99' => 'Incident domaine initiateur' 
						),
						'warranty_results' => array (
								'empty' => 'Garantie de paiement non applicable',
								'YES' => 'Le paiement est garanti',
								'NO' => 'Le paiement n\'est pas garanti',
								'UNKNOWN' => 'Suite à une erreur technique, le paiment ne peut pas être garanti' 
						) 
				),
				
				'pt' => array (
						'results' => array (
								'empty' => '',
								'00' => 'Pagamento realizado com sucesso',
								'02' => 'O comerciante deve contactar o banco do portador',
								'05' => 'Pagamento recusado',
								'17' => 'Cancelamento cliente',
								'30' => 'Erro no formato dos dados',
								'96' => 'Erro técnico durante o pagamento' 
						),
						'extra_results_default' => array (
								'empty' => 'Nenhum controle efetuado',
								'00' => 'Todos os controles foram feitos com sucesso',
								'02' => 'Limite do cartão excedido',
								'03' => 'O cartão esta na lista cinza do comerciante',
								'04' => 'O país de emissão do cartão esta na lista cinza do comerciante',
								'05' => 'O endereço IP esta na lista cinza do comerciante',
								'06' => 'O código BIN esta na lista cinza do comerciante',
								'07' => 'Detecção de um cartão e-carte bleue',
								'08' => 'Detecção de um cartão comercial nacional',
								'09' => 'Detecção de um cartão comercial estrangeiro',
								'14' => 'O cartão é um cartão com autorização sistemática',
								'20' => 'Nenhum país corresponde (país IP, país cartão, país cliente)',
								'30' => 'O país do endereço IP pertence à lista cinza',
								'99' => 'Problema técnico encontrado pelo servidor durante o tratamento de um dos controles locais' 
						),
						'auth_results' => array (
								'empty' => '',
								'00' => 'Transação aprovada ou tratada com sucesso',
								'02' => 'Contactar o emissor do cartão',
								'03' => 'Recebedor inválido',
								'04' => 'Conservar o cartão',
								'05' => 'Não honrar',
								'07' => 'Conservar o cartão, condições especiais',
								'08' => 'Aprovar após identificação',
								'12' => 'Transação inválida',
								'13' => 'Valor inválido',
								'14' => 'Número do portador inválido',
								'30' => 'Erro no formato',
								'31' => 'Identificação do adquirente desconhecido',
								'33' => 'Data de validade do cartão ultrapassada',
								'34' => 'Suspeita de fraude',
								'41' => 'Cartão perdido',
								'43' => 'Cartão roubado',
								'51' => 'Saldo insuficiente ou limite excedido',
								'54' => 'Data de validade do cartão ultrapassada',
								'56' => 'Cartão ausente do arquivo',
								'57' => 'Transação não permitida para este portador',
								'58' => 'Transação proibida no terminal',
								'59' => 'Suspeita de fraude',
								'60' => 'O recebedor do cartão deve contactar o adquirente',
								'61' => 'Valor de saque fora do limite',
								'63' => 'Regras de segurança não respeitadas',
								'68' => 'Nenhuma resposta recebida ou recebida tarde demais',
								'90' => 'Parada momentânea do sistema',
								'91' => 'Emissor do cartão inacessível',
								'96' => 'Mau funcionamento do sistema',
								'94' => 'Transação duplicada',
								'97' => 'Limite do tempo de monitoramento global',
								'98' => 'Servidor indisponível nova solicitação de roteamento',
								'99' => 'Incidente no domínio iniciador' 
						),
						'warranty_results' => array (
								'empty' => 'Garantia de pagamento não aplicável',
								'YES' => 'O pagamento foi garantido',
								'NO' => 'O pagamento não foi garantido',
								'UNKNOWN' => 'Devido à un erro técnico, o pagamento não pôde ser garantido' 
						) 
				),
				
				'de' => array (
						'results' => array (
								'empty' => '',
								'00' => 'Zahlung mit Erfolg durchgeführt',
								'02' => 'Der Händler muss die Bank des Karteninhabers kontaktieren',
								'05' => 'Zahlung zurückgewiesen',
								'17' => 'Stornierung durch den Kunden',
								'30' => 'Fehler im Format der Anfrage',
								'96' => 'Technischer Fehler bei der Zahlung' 
						),
						'extra_results_default' => array (
								'empty' => 'Keine Kontrolle durchgeführt',
								'00' => 'Alle Kontrollen werden mit Erfolg durchgeführt',
								'02' => 'Die Karte hat den erlaubten ausstehenden Betrag überschritten',
								'03' => 'Die Karte befindet sich in der Blacklist des Händlers',
								'04' => 'Das kartenausgebende Land befindet sich in der Blacklist des Händlers',
								'05' => 'Die IP Adresse befindet sich in der Blacklist des Händlers',
								'06' => 'Der BIN Code befindet sich in der Blacklist des Händlers',
								'07' => 'Erkennen einer elektronisch ausgestellten Karte',
								'08' => 'Erkennen einer nationalen Karte',
								'09' => 'Erkennen einer internationalen Karte',
								'14' => 'Die Karte unterliegt systematischer Autorisierung',
								'20' => 'Kein Land entspricht sich (Land der IP, der Karte, des Kunden)',
								'99' => 'Technisches Problem des Servers bei Bearbeitung einer der lokalen Kontrollen' 
						),
						'auth_results' => array (
								'empty' => '',
								'00' => 'Zahlung durchgeführt oder mit Erfolg bearbeitet',
								'02' => 'Kartenausgebende Bank kontaktieren',
								'03' => 'Ungültiger Annehmer',
								'04' => 'Karte aufbewahren',
								'05' => 'Nicht einlösen',
								'07' => 'Karte aufbewahren, Sonderbedingungen',
								'08' => 'Nach Identifizierung genehmigen',
								'12' => 'Ungültige Transaktion',
								'13' => 'Ungültiger Betrag',
								'14' => 'Ungültige Nummer des Karteninhabers',
								'30' => 'Formatfehler',
								'31' => 'ID des Annehmers unbekannt',
								'33' => 'Gültigkeitsdatum der Karte überschritten',
								'34' => 'Verdacht auf Betrug',
								'41' => 'Verlorene Karte',
								'43' => 'Gestohlene Karte',
								'51' => 'Deckung unzureichend oder Kredit überschritten',
								'54' => 'Gültigkeitsdatum der Karte überschritten',
								'56' => 'Karte nicht in der Datei enthalten',
								'57' => 'Transaktion diesem Karteninhaber nicht erlaubt',
								'58' => 'Transaktion diesem Terminal nicht erlaubt',
								'59' => 'Verdacht auf Betrug',
								'60' => 'Der Kartenannehmer muss den Acquirer kontaktieren',
								'61' => 'Betrag der Abhebung überschreitet das Limit',
								'63' => 'Sicherheitsregelen nicht respektiert',
								'68' => 'Antwort nicht oder zu spät erhalten',
								'90' => 'Momentane Systemunterbrechung',
								'91' => 'Kartenausgeber nicht erreichbar',
								'96' => 'Fehlverhalten des Systems',
								'94' => 'Kopierte Transaktion',
								'97' => 'Fälligkeit der Verzögerung der globalen Überwachung',
								'98' => 'Server nicht erreichbar, Routen des Netzwerkes erneut angefragt',
								'99' => 'Vorfall der urhebenden Domain' 
						),
						'warranty_results' => array (
								'empty' => 'Zahlungsgarantie nicht anwendbar',
								'YES' => 'Die Zahlung ist garantiert',
								'NO' => 'Die Zahlung ist nicht garantiert',
								'UNKNOWN' => 'Die Zahlung kann aufgrund eines technischen Fehlers nicht gewährleistet werden' 
						) 
				) 
		);
		
		static $FORM_ERRORS = array(
				'00' => 'SIGNATURE',
				'01' => 'VERSION',
				'02' => 'SITE_ID',
				'03' => 'TRANS_ID',
				'04' => 'TRANS_DATE',
				'05' => 'VALIDATION_MODE',
				'06' => 'CAPTURE_DELAY',
				'07' => 'PAYMENT_CONFIG',
				'08' => 'PAYMENT_CARDS',
				'09' => 'AMOUNT',
				'10' => 'CURRENCY',
				'11' => 'CTX_MODE',
				'12' => 'LANGUAGE',
				'13' => 'ORDER_ID',
				'14' => 'ORDER_INFO',
				'15' => 'CUST_EMAIL',
				'16' => 'CUST_ID',
				'17' => 'CUST_TITLE',
				'18' => 'CUST_NAME',
				'19' => 'CUST_ADDRESS',
				'20' => 'CUST_ZIP',
				'21' => 'CUST_CITY',
				'22' => 'CUST_COUNTRY',
				'23' => 'CUST_PHONE',
				'24' => 'URL_SUCCESS',
				'25' => 'URL_REFUSED',
				'26' => 'URL_REFERRAL',
				'27' => 'URL_CANCEL',
				'28' => 'URL_RETURN',
				'29' => 'URL_ERROR',
				'30' => 'IDENTIFIER',
				'31' => 'CONTRIB',
				'32' => 'THEME_CONFIG',
				'33' => 'URL_CHECK',
				'34' => 'REDIRECT_SUCCESS_TIMEOUT',
				'35' => 'REDIRECT_SUCCESS_MESSAGE',
				'36' => 'REDIRECT_ERROR_TIMEOUT',
				'37' => 'REDIRECT_ERROR_MESSAGE',
				'38' => 'RETURN_POST_PARAMS',
				'39' => 'RETURN_GET_PARAMS',
				'40' => 'CARD_NUMBER',
				'41' => 'CARD_EXP_MONTH',
				'42' => 'CARD_EXP_YEAR',
				'43' => 'CARD_CVV',
				'44' => 'CARD_CVV_AND_BIRTH',
				'46' => 'PAGE_ACTION',
				'47' => 'ACTION_MODE',
				'48' => 'RETURN_MODE',
				'49' => 'ABSTRACT_INFO',
				'50' => 'SECURE_MPI',
				'51' => 'SECURE_ENROLLED',
				'52' => 'SECURE_CAVV',
				'53' => 'SECURE_ECI',
				'54' => 'SECURE_XID',
				'55' => 'SECURE_CAVV_ALG',
				'56' => 'SECURE_STATUS',
				'60' => 'PAYMENT_SRC',
				'61' => 'USER_INFO',
				'62' => 'CONTRACTS',
				'63' => 'RECURRENCE',
				'64' => 'RECURRENCE_DESC',
				'65' => 'RECURRENCE_AMOUNT',
				'66' => 'RECURRENCE_REDUCED_AMOUNT',
				'67' => 'RECURRENCE_CURRENCY',
				'68' => 'RECURRENCE_REDUCED_AMOUNT_NUMBER',
				'69' => 'RECURRENCE_EFFECT_DATE',
				'70' => 'EMPTY_PARAMS',
				'71' => 'AVAILABLE_LANGUAGES',
				'72' => 'SHOP_NAME',
				'73' => 'SHOP_URL',
				'74' => 'OP_COFINOGA',
				'75' => 'OP_CETELEM',
				'76' => 'BIRTH_DATE',
				'77' => 'CUST_CELL_PHONE',
				'79' => 'TOKEN_ID',
				'80' => 'SHIP_TO_NAME',
				'81' => 'SHIP_TO_STREET',
				'82' => 'SHIP_TO_STREET2',
				'83' => 'SHIP_TO_CITY',
				'84' => 'SHIP_TO_STATE',
				'85' => 'SHIP_TO_ZIP',
				'86' => 'SHIP_TO_COUNTRY',
				'87' => 'SHIP_TO_PHONE_NUM',
				'88' => 'CUST_STATE',
				'89' => 'REQUESTOR',
				'90' => 'PAYMENT_TYPE',
				'91' => 'EXT_INFO',
				'92' => 'CUST_STATUS',
				'93' => 'SHIP_TO_STATUS',
				'94' => 'SHIP_TO_TYPE',
				'95' => 'SHIP_TO_SPEED',
				'96' => 'SHIP_TO_DELIVERY_COMPANY_NAME',
				'97' => 'PRODUCT_LABEL',
				'98' => 'PRODUCT_TYPE',
				'100' => 'PRODUCT_REF',
				'101' => 'PRODUCT_QTY',
				'102' => 'PRODUCT_AMOUNT',
				'103' => 'PAYMENT_OPTION_CODE',
				'104' => 'CUST_FIRST_NAME',
				'105' => 'CUST_LAST_NAME',
				'106' => 'SHIP_TO_FIRST_NAME',
				'107' => 'SHIP_TO_LAST_NAME',
				'108' => 'TAX_AMOUNT',
				'109' => 'SHIPPING_AMOUNT',
				'110' => 'INSURANCE_AMOUNT',
				'111' => 'PAYMENT_ENTRY',
				'112' => 'CUST_ADDRESS_NUMBER',
				'113' => 'CUST_DISTRICT',
				'114' => 'SHIP_TO_STREET_NUMBER',
				'115' => 'SHIP_TO_DISTRICT',
				'116' => 'SHIP_TO_USER_INFO',
				'117' => 'RISK_PRIMARY_WARRANTY',
				'117' => 'DONATION',
				'99' => 'OTHER',
				'118' => 'STEP_UP_DATA',
				'201' => 'PAYMENT_AUTH_CODE',
				'202' => 'PAYMENT_CUST_CONTRACT_NUM',
				'888' => 'ROBOT_REQUEST',
				'999' => 'SENSITIVE_DATA'
		);
	}
}

if(!@class_exists('PayzenField', false)) {
	/**
	 * Class representing a field of the form to send to the payment gateway
	 */
	class PayzenField {
		/**
		 * Field's name. Matches the html input attribute
		 * @var string
		 * @access private
		 */
		var $name;
		/**
		 * Field's label in english, to be used by translation systems
		 * @var string
		 * @access private
		 */
		var $label;
		/**
		 * Field's maximum length. Matches the html text input attribute
		 * @var int
		 * @access private
		 */
		var $length;
		/**
		 * PCRE regular expression the field value must match
		 * @var string
		 * @access private
		 */
		var $regex;
		/**
		 * Whether the form requires the field to be set (even to an empty string)
		 * @var boolean
		 * @access private
		 */
		var $required;
		/**
		 * Field's value. Null or string
		 * @var string
		 * @access private
		 */
		var $value = null;
	
		/**
		 * Constructor
		 * @param string $name
		 * @param string $label
		 * @param string $regex
		 * @param boolean $required
		 * @param string $value
		 * @return PayzenField
		 */
		function PayzenField($name, $label, $regex, $required = false, $length = 255) {
			$this->name = $name;
			$this->label = $label;
			$this->regex = $regex;
			$this->required = $required;
			$this->length = $length;
		}
	
		/**
		 * Setter for value
		 * @param mixed $value
		 * @return boolean true if the value is valid
		 */
		function setValue($value) {
			$value = ($value === null) ? null : (string) $value;
			// We save value even if invalid (in case the validate function is too restrictive, it happened once) ...
			$this->value = $value;
			if (!$this->validate($value)) {
				// ... but we return a "false" warning
				return false;
			}
			return true;
		}
	
		/**
		 * Checks the current value
		 * @return boolean false if the current value is invalid or null and required
		 */
		function isValid() {
			return $this->validate($this->value);
		}
	
		/**
		 * Check if a value is valid for this field
		 * @param string $value
		 * @return boolean
		 */
		function validate($value) {
			if ($value === null && $this->isRequired()) {
				return false;
			}
			if ($value !== null && !preg_match($this->regex, $value)) {
				return false;
			}
			return true;
		}
	
		/**
		 * Setter for the required attribute
		 * @param boolean $required
		 */
		function setRequired($required) {
			$this->required = (boolean) $required;
		}
	
		/**
		 * Is the field required in the payment request ?
		 * @return boolean
		 */
		function isRequired() {
			return $this->required;
		}
	
		/**
		 * Return the current value of the field.
		 * @return string
		 */
		function getValue() {
			return $this->value;
		}
	
		/**
		 * Return the name (html attribute) of the field.
		 * @return string
		 */
		function getName() {
			return $this->name;
		}
	
		/**
		 * Return the english human-readable name of the field.
		 * @return string
		 */
		function getLabel() {
			return $this->label;
		}
	
		/**
		 * Return the maximum length of the field's value.
		 * @return number
		 */
		function getLength() {
			return $this->length;
		}
	
		/**
		 * Has a value been set ?
		 * @return boolean
		 */
		function isFilled() {
			return !is_null($this->getValue());
		}
	}
}

if(!@class_exists('PayzenCurrency', false)) {
	/**
	 * Class representing a currency, used for converting alpha/numeric iso codes and float/integer amounts
	 */
	class PayzenCurrency {
		var $alpha3;
		var $num;
		var $decimals;
	
		function PayzenCurrency($alpha3, $num, $decimals = 2) {
			$this->alpha3 = $alpha3;
			$this->num = $num;
			$this->decimals = $decimals;
		}
	
		function convertAmountToInteger($float) {
			$coef = pow(10, $this->decimals);
	
			return intval(strval($float * $coef));
		}
	
		function convertAmountToFloat($integer) {
			$coef = pow(10, $this->decimals);
	
			return floatval($integer) / $coef;
		}
	}
}

if(!@class_exists('PayzenMultiApi', false)) {
	/**
	 * Class managing multi payment specific actions
	 * @version 2.2
	 */
	class PayzenMultiApi extends PayzenApi {
	
		/**
		 * Constructor.
		 */
		function PayzenMultiApi($encoding="UTF-8") {
			// call parent class constructor
			parent::PayzenApi($encoding);
		}
	
		/**
		* Set multi payment configuration
		* @param $total_in_cents total order amount in cents
		* @param $first_in_cents amount of the first payment in cents
		* @param $count total number of payments
		* @param $period number of days between 2 payments 
		* @return boolean true on success
		*/
		function setMultiPayment($total_in_cents = null, $first_in_cents = null, $count = 3, $period = 30) {
			$result = false;
			
			if (is_numeric($count) && $count > 1 && is_numeric($period) && $period > 0) {
				// Valeurs par défaut pour first et total
				$total_in_cents = ($total_in_cents === null) ? $this->get('amount')	: $total_in_cents;
				$first_in_cents = ($first_in_cents === null) ? round($total_in_cents / $count) : $first_in_cents;
	
				// Vérification des paramètres
				if (is_numeric($total_in_cents) && $total_in_cents > $first_in_cents
						&& $total_in_cents > 0 && is_numeric($first_in_cents)
						&& $first_in_cents > 0) {
					// Enregistrement du paramètres payment_config
					$payment_config = 'MULTI:first=' . $first_in_cents . ';count=' . $count	. ';period=' . $period;
					$result = $this->set('amount', $total_in_cents);
					if ($result === true) {
						// premier set ok, on continue
						$result = $this->set('payment_config', $payment_config);
					}
				}
			}
	
			return $result;
		}
	}
}