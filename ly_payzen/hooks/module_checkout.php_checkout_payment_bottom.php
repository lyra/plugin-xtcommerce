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

if (isset($_payment) && ($_payment === 'ly_payzen') && (isset($_payment_sub) && ($_payment_sub === 'multi'))
    || (isset($_POST['payzen_selected_payment_sub']) && ($_POST['payzen_selected_payment_sub'] === 'multi'))) {
    // Store selected option in session.
    $_SESSION['payzen_multi_opt'] = $_POST['payzen_multi_opt'];
}

if (isset($_POST['payzen_selected_payment_sub'])) {
    // Store selected submodule in session.
    $_SESSION['selected_payment_sub'] = $_POST['payzen_selected_payment_sub'];
}
