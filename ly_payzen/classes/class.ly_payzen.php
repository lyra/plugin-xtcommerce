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

require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.PayzenRequest.php';
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.PayzenLogger.php';

/**
 * Main payment gateway class (preparing and sending data form).
 */
class ly_payzen
{
    public static $payzen_plugin_features = array(
        'qualif' => false,
        'prodfaq' => true,
        'restrictmulti' => false,
        'shatwo' => true,

        'multi' => true
    );

    var $version = '1.1.0';

    public $data = array();

    public $post_form = true;
    public $external = false;
    public $subpayments = false;
    public $iframe = false;
    public $pay_frame = false;

    public $TARGET_URL;
    public $TARGET_PARAMS = array();

    function pspRedirect()
    {
        global $xtLink, $order;

        $logger = PayzenLogger::getLogger(__CLASS__);

        $logger->info('Generating payment form for order #' . $order->order_data['orders_id'] . '.');

        $request = new PayzenRequest();

        $request->set('contrib', 'xtCommerce_4.x-6.x_1.1.0/' . _SYSTEM_VERSION . '/' . PHP_VERSION);

        // Set order parameters.
        $request->set('order_id', $order->order_data['orders_id']);

        $payzenCurrency = PayzenApi::findCurrencyByAlphaCode($order->order_data['currency_code']);
        $request->set('currency', $payzenCurrency->getNum());

        $amount = $order->order_total['total']['plain'];
        $request->set('amount', $payzenCurrency->convertAmountToInteger($amount));

        $shopLanguage = $order->order_data['language_code']; // Current shop language.
        $payzenLanguage = PayzenApi::isSupportedLanguage($shopLanguage) ? $shopLanguage : LY_PAYZEN_DEFAULT_LANGUAGE;
        $request->set('language', $payzenLanguage);

        // Customer data.
        $request->set('cust_id', $order->order_customer['customers_id']);
        $request->set('cust_email', $order->order_customer['customers_email_address']);
        $request->set('cust_title', $this->_getGender($order->order_customer['customers_gender']));

        // Billing data.
        $address = $order->order_data['billing_street_address'];
        if ($order->order_data['billing_suburb']) {
            $address .= ' ' . $order->order_data['billing_suburb'];
        }

        $request->set('cust_first_name', $order->order_data['billing_firstname']);
        $request->set('cust_last_name', $order->order_data['billing_lastname']);
        $request->set('cust_address', $address);
        $request->set('cust_zip', $order->order_data['billing_postcode']);
        $request->set('cust_city', $order->order_data['billing_city']);
        $request->set('cust_state', $order->order_data['billing_federal_state_code']);
        $request->set('cust_country', $order->order_data['billing_country_code']);
        $request->set('cust_phone', $order->order_data['billing_phone']);

        // Delivery data.
        $request->set('ship_to_first_name', $order->order_data['delivery_firstname']);
        $request->set('ship_to_last_name', $order->order_data['delivery_lastname']);
        $request->set('ship_to_street', $order->order_data['delivery_street_address']);
        $request->set('ship_to_street2', $order->order_data['delivery_suburb']);
        $request->set('ship_to_zip', $order->order_data['delivery_postcode']);
        $request->set('ship_to_city', $order->order_data['delivery_city']);
        $request->set('ship_to_state', $order->order_data['delivery_federal_state_code']);
        $request->set('ship_to_country', $order->order_data['delivery_country_code']);
        $request->set('ship_to_phone_num', $order->order_data['delivery_phone']);
        $request->set('redirect_success_message', $this->_getSimpleLangValue(LY_PAYZEN_REDIRECT_SUCCESS_MESSAGE , $shopLanguage));
        $request->set('redirect_error_message', $this->_getSimpleLangValue(LY_PAYZEN_REDIRECT_ERROR_MESSAGE , $shopLanguage));

        // Set configuration parameters.
        $configKeys = array(
            'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url',
            'available_languages', 'capture_delay', 'validation_mode', 'payment_cards',
            'redirect_enabled', 'redirect_success_timeout', 'redirect_error_timeout',
            'return_mode', 'sign_algo'
        );
        foreach ($configKeys as $key) {
            $value = constant('LY_PAYZEN_' . strtoupper($key));

            if (($key === 'available_languages') || ($key === 'payment_cards')) {
                $value = str_replace(',', ';', $value);
            }

            $request->set($key, $value);
        }

        // Activate 3DS?
        $threedsMpi = null;
        if (LY_PAYZEN_3DS_MIN_AMOUNT && ($amount < LY_PAYZEN_3DS_MIN_AMOUNT)) {
            $threedsMpi = '2';
        }

        $request->set('threeds_mpi', $threedsMpi);

        // Return URL (same as INP URL).
        $request->set('url_return', $xtLink->_link(array('page' => 'callback', 'paction' => 'ly_payzen', 'conn' => 'SSL')));

        // Session data.
        $request->addExtInfo('session_name', session_name());
        $request->addExtInfo('session_id', session_id());

        // Optionally set multi payment parameters.
        if (isset($_SESSION['selected_payment_sub']) && $_SESSION['selected_payment_sub'] === 'multi') {
            $options = json_decode(stripslashes(LY_PAYZEN_MULTI_OPTIONS), true);
            $option = false;

            foreach ($options as $opt) {
                if ($opt['id'] === $_SESSION['payzen_multi_opt']) {
                    $option = $opt;
                    break;
                }
            }

            if ($option) {
                $first = $option['first'] ? $payzenCurrency->convertAmountToInteger($option['first'] / 100 * $amount) : null;
                $request->setMultiPayment(null /* to use already set amount */, $first, $option['count'], $option['period']);
                $request->addExtInfo('multi_option', $option['count'] . 'X');

                // Override CB contract.
                $request->set('contracts', ($option['contract']) ? 'CB=' . $option['contract'] : null);
            }
        }

        // Set default order status in case of payment once again.
        if ($order->order_data['orders_status_id'] !== LY_PAYZEN_ORDER_STATUS_NEW) {
            $order->_updateOrderStatus(LY_PAYZEN_ORDER_STATUS_NEW, $order->order_data['comments'], 'false', 'false');
        }

        // Load multilingual button text.
        define('BUTTON_START_PAYMENT', TEXT_PAYZEN_BUTTON_START_PAYMENT);

        $this->TARGET_URL = $request->get('platform_url');
        $this->TARGET_PARAMS = $request->getRequestFieldsArray();

        $logger->debug('Data to be sent to payment gateway: ' . print_r($request->getRequestFieldsArray(true /* To hide sensitive data. */), true));

        // Redirect to payment page.
        $xtLink->_redirect($request->getRequestUrl());
    }

    function _getGender($genderId)
    {
        $genderData = array(
            'm' => TEXT_MALE,
            'f' => TEXT_FEMALE,
            'c' => TEXT_COMPANY_GENDER
        );

        if (isset($genderData[$genderId])) {
            return @$genderData[$genderId];
        }

        return '';
    }

    function _getSimpleLangValue($multiLangValues, $language='en')
    {
        $value = json_decode(stripslashes($multiLangValues), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }

        if (isset($value[$language])) {
            return $value[$language];
        }

        return '';
    }
}
