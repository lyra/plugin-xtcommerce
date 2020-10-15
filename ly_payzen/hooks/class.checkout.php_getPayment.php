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
 * Enable payment module.
 */
global $page, $currency;

if (isset($payment_data['ly_payzen'])) {
    if (version_compare(_SYSTEM_VERSION, '5.0.0', '<')) { // Charge a specific template for xt:Commerce 4.x and less.
        $payment_data['ly_payzen']['payment_tpl'] = 'payzen_bc.html';
    }

    if ($page->page_action === 'payment') {
        // Check currency.
        require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.PayzenApi.php';

        $payzenCurrency = PayzenApi::findCurrencyByAlphaCode($currency->code);
        if (! is_a($payzenCurrency, 'PayzenCurrency')) {
            unset($payment_data['ly_payzen']);
        }

        $amount = $_SESSION['cart']->total['plain'];
        $shopLanguage = $_SESSION['selected_language']; // Current shop language.

        // Standard submodule enabled?
        $stdEnabled = constant('LY_PAYZEN_STD_ENABLED');

        // Check amount restrictions.
        $min = constant('LY_PAYZEN_STD_MIN_AMOUNT');
        $max = constant('LY_PAYZEN_STD_MAX_AMOUNT');
        $stdEnabled &= ((! $min || $amount >= $min) && (! $max || $amount <= $max));

        $_SESSION['payzen_std_enabled'] = $stdEnabled;

        $titles = json_decode(stripslashes(LY_PAYZEN_STD_TITLE), true);
        $_SESSION['payzen_std_title'] = $titles[$shopLanguage];

        // Multi submodule enabled?
        $multiEnabled = constant('LY_PAYZEN_MULTI_ENABLED');

        // Check amount restrictions.
        $min = constant('LY_PAYZEN_MULTI_MIN_AMOUNT');
        $max = constant('LY_PAYZEN_MULTI_MAX_AMOUNT');
        $multiEnabled &= ((! $min || $amount >= $min) && (! $max || $amount <= $max));

        // Check multi payment options.
        $enabledOptions = array();
        $options = json_decode(stripslashes(LY_PAYZEN_MULTI_OPTIONS), true);
        if (is_array($options) && count($options)) {
            foreach ($options as $option) {
                if ((! $option['min_amount'] || $amount >= $option['min_amount']) &&
                    (! $option['max_amount'] || $amount <= $option['max_amount'])) {
                    $enabledOptions[] = $option;
                }
            }
        }

        $multiEnabled &= count($enabledOptions) > 0;

        $_SESSION['payzen_multi_enabled'] = $multiEnabled;

        $titles = json_decode(stripslashes(LY_PAYZEN_MULTI_TITLE), true);
        $_SESSION['payzen_multi_title'] = $titles[$shopLanguage];
        $_SESSION['payzen_multi_options'] = $enabledOptions;
    }
}
