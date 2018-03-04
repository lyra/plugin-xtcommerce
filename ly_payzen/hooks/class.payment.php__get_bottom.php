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
 * Workaround to sort PayZen payment module config parameters.
 */
global $db;

// get payment id by code
$query = "SELECT `payment_id` FROM " . TABLE_PAYMENT . " WHERE `payment_code` = 'ly_payzen'";
$payzenId = $db->GetOne($query);

if($_REQUEST['load_section'] == 'payment' && $_REQUEST['edit_id'] == $payzenId) {
	foreach ($stores as $sdata) {
		$query = "SELECT * FROM " . TABLE_CONFIGURATION_PAYMENT . " WHERE `payment_id` = '".$pID."' AND `shop_id`='".$sdata['id']."' ORDER BY `sort_order` ASC";
		//	echo $query;
		$rs = $db->Execute($query);
	
		while (!$rs->EOF) {
			unset($data[0]['conf_'.$rs->fields['config_key'].'_shop_'.$sdata['id']]);
			$data[0]['conf_'.$rs->fields['config_key'].'_shop_'.$sdata['id']] = $rs->fields;
	
			$rs->MoveNext();
		}
	
		$rs->Close();
	}
}