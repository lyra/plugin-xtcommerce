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

global $db;

// Get payment ID by code.
$query = "SELECT `payment_id` FROM " . TABLE_PAYMENT . " WHERE `payment_code` = 'ly_payzen'";
$payzenId = $db->GetOne($query);

if (($_REQUEST['load_section'] === 'payment') && ($_REQUEST['edit_id'] === $payzenId)) {
    require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.PayzenExtAdminHandler.php';
    $form_grid = new PayzenExtAdminHandler($form_grid);
}
