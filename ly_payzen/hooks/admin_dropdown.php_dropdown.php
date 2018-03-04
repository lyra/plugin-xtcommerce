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

defined('_VALID_CALL') or die('Direct Access is not allowed.');


/**
 * Provide drop-down lists for configuration.
 */
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/payzen_api.php';

if ($request['get'] == 'ly_payzen:cmodes') {
	$result[] = array(
			'id'   => 'TEST',
			'name' => 'TEST'
	);
	$result[] = array(
			'id'   => 'PRODUCTION',
			'name' => 'PRODUCTION'
	);
} elseif ($request['get'] == 'ly_payzen:vmodes') {
	$result[] = array(
			'id'   => '',
			'name' => TEXT_PAYZEN_DEFAULT
	);
	$result[] = array(
			'id'   => '0',
			'name' => TEXT_PAYZEN_AUTOMATIC
	);
	$result[] = array(
			'id'   => '1',
			'name' => TEXT_PAYZEN_MANUAL
	);
} elseif ($request['get'] == 'ly_payzen:rmodes') {
	$result[] = array(
			'id'   => 'GET',
			'name' => 'GET'
	);
	$result[] = array(
			'id'   => 'POST',
			'name' => 'POST'
	);

} elseif ($request['get'] == 'ly_payzen:redirects') {
	$result[] = array(
			'id' => 'False',
			'name' => TEXT_PAYZEN_DISABLED
	);
	$result[] = array(
			'id' => 'True',
			'name' => TEXT_PAYZEN_ENABLED
	);

} elseif ($request['get'] == 'ly_payzen:languages') {
	$payzenApi = new PayzenApi();
	foreach ($payzenApi->getSupportedLanguages() as $key => $language) {
		$result[] = array(
				'id'   => $key,
				'name' => constant('TEXT_PAYZEN_' . strtoupper($language))
		);
	}
} elseif ($request['get'] == 'ly_payzen:cards') {
	$payzenApi = new PayzenApi();
	foreach ($payzenApi->getSupportedCardTypes() as $key => $card) {
		$result[] = array(
				'id'   => $key,
				'name' => $card
		);
	}
} elseif ($request['get'] == 'ly_payzen:multi_options') {
	$result = array();
	
	if(defined('LY_PAYZEN_MULTI_OPTIONS') && LY_PAYZEN_MULTI_OPTIONS) {
		$result = json_decode(html_entity_decode(LY_PAYZEN_MULTI_OPTIONS), true);
	}
}