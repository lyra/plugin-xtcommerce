<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for xt:Commerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');

/**
 * Workaround to sort payment module config parameters.
 */
global $db;

// Get payment ID by code.
$query = 'SELECT `payment_id` FROM ' . TABLE_PAYMENT . " WHERE `payment_code` = 'ly_payzen'";
$payzenId = $db->GetOne($query);

if (($_REQUEST['load_section'] === 'payment') && ($_REQUEST['edit_id'] === $payzenId)) {
    foreach ($stores as $sdata) {
        $query = "SELECT * FROM " . TABLE_CONFIGURATION_PAYMENT . " WHERE `payment_id` = '" . $pID . "' AND `shop_id`='"
            . $sdata['id'] . "' ORDER BY `sort_order` ASC";
        $rs = $db->Execute($query);

        while (! $rs->EOF) {
            unset($data[0]['conf_' . $rs->fields['config_key'] . '_shop_' . $sdata['id']]);
            $data[0]['conf_' . $rs->fields['config_key'] . '_shop_' . $sdata['id']] = $rs->fields;

            $rs->MoveNext();
        }

        $rs->Close();
    }
}
