<?php
/**
 * PayZen V2-Payment Module version 1.0.1 for xtCommerce 4.1.x. Support contact : support@payzen.eu.
 *
 * The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @category  payment
 * @package   payzen
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @copyright 2014-2016 Lyra Network and contributors
 * @license   http://www.opensource.org/licenses/mit-license.html  The MIT License (MIT)
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');

/**
 * Enabling PayZen payment module.
 */
global $page, $currency;

if(isset($payment_data['ly_payzen'])) {
	if ($page->page_action == 'payment') {
		// check currency
		require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/payzen_api.php';
		$api = new PayzenApi ();
		$payzenCurrency = $api->findCurrencyByAlphaCode($currency->code);
		if(!is_a($payzenCurrency, 'PayzenCurrency')) {
			unset($payment_data['ly_payzen']);
		}

		$amount = $_SESSION['cart']->total['plain'];

		// standard sub payment enabled ?
		$stdEnabled = constant('LY_PAYZEN_STD_ENABLED');

		// check amount restrictions
	 	$min = constant('LY_PAYZEN_STD_MIN_AMOUNT');
		$max = constant('LY_PAYZEN_STD_MAX_AMOUNT');
		$stdEnabled &= ((!$min || $amount >= $min) && (!$max || $amount <= $max));

		$_SESSION['payzen_std_enabled'] = $stdEnabled;
		$_SESSION['payzen_std_title'] = LY_PAYZEN_STD_TITLE;

		// multi sub payment enabled ?
		$multiEnabled = constant('LY_PAYZEN_MULTI_ENABLED');

		// check amount restrictions
		$min = constant('LY_PAYZEN_MULTI_MIN_AMOUNT');
		$max = constant('LY_PAYZEN_MULTI_MAX_AMOUNT');
		$multiEnabled &= ((!$min || $amount >= $min) && (!$max || $amount <= $max));

		// check multi payment options
		$enabledOptions = array();
		$options = json_decode(html_entity_decode(LY_PAYZEN_MULTI_OPTIONS), true);
		if(is_array($options) && count($options)) {
			foreach ($options as $option) {
				if((!$option['amount_min'] || $amount >= $option['amount_min']) && (!$option['amount_max'] || $amount <= $option['amount_max'])) {
					$enabledOptions[] = $option;
				}
			}
		}
		$multiEnabled &= count($enabledOptions) > 0;

		$_SESSION['payzen_multi_enabled'] = $multiEnabled;
		$_SESSION['payzen_multi_title'] = LY_PAYZEN_MULTI_TITLE;
		$_SESSION['payzen_multi_options'] = $enabledOptions;
	}
}