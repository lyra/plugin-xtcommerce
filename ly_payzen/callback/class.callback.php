<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for xt:Commerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

defined ('_VALID_CALL') or die('Direct Access is not allowed.');

require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.PayzenResponse.php';
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.ly_payzen.php';
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.PayzenLogger.php';

/**
 * Callback class to process return from payment platform.
 */
class callback_ly_payzen extends callback
{
    var $version = '1.1.0';

    private $from_server;
    private $multi_option;
    private $logger;
    private $payzenResponse;

    function __construct()
    {
        $this->logger = PayzenLogger::getLogger(__CLASS__);

        // Add request parameters decoding.
        foreach ($_REQUEST as $name => $value) {
            $_REQUEST[$name] = html_entity_decode($value, ENT_QUOTES);
        }

        $this->from_server = isset($_REQUEST['vads_hash']) && ! empty($_REQUEST['vads_hash']);

        $this->payzenResponse = new PayzenResponse (
            $_REQUEST,
            LY_PAYZEN_CTX_MODE,
            LY_PAYZEN_KEY_TEST,
            LY_PAYZEN_KEY_PROD,
            LY_PAYZEN_SIGN_ALGO // Add signature algorithm to response.
        );

        $session_name = $this->payzenResponse->getExtInfo('session_name');
        $session_id = $this->payzenResponse->getExtInfo('session_id');

        $this->multi_option = $this->payzenResponse->getExtInfo('multi_option');

        if ($session_name && $session_id) {
            // Reload initial session.
            session_write_close();

            session_name($session_name);
            session_id($session_id);
            session_start();
        }
    }

    function _getValue($name, $array)
    {
        if (isset($array[$name])) {
            return $array[$name];
        }

        return null;
    }

    function process()
    {
        global $xtLink, $info;

        $this->orders_id = (int) $this->payzenResponse->get('order_id');
        $this->customers_id = (int) $this->payzenResponse->get('cust_id');

        // Check request authenticity.
        if (! $this->payzenResponse->isAuthentified()) {
            $this->logger->error('Authentication failed: received invalid response with parameters: ' . print_r($_REQUEST, true));
            $this->logger->error('Signature algorithm selected in module settings must be the same as one selected in gateway Back Office.');

            if ($this->from_server) {
                $this->logger->info('IPN URL PROCESS END.');
                $this->_die($this->payzenResponse->getOutputForGateway('auth_fail'));
            } else {
                $info->_addInfoSession(TEXT_PAYZEN_TECHNICAL_ERROR, 'error');
                $this->logger->info('RETURN URL PROCESS END.');
                $xtLink->_redirect($xtLink->_link(array('page' => 'index')));
            }
        }

        // Load order data from DB.
        $order = new order($this->orders_id, $this->customers_id);
        if (! is_array($order->order_data)) {
            $this->logger->error("Error: order #$this->orders_id not found in database.");

            if ($this->from_server) {
                $this->logger->info('IPN URL PROCESS END.');
                $this->_die($this->payzenResponse->getOutputForGateway('order_not_found'));
            } else {
                $info->_addInfoSession(TEXT_PAYZEN_TECHNICAL_ERROR, 'error');
                $this->logger->info('RETURN URL PROCESS END.');
                $xtLink->_redirect($xtLink->_link(array('page' => 'index')));
            }
        }

        // Add prodfaq domain feautres to show going to production messages.
        if (! $this->from_server && (LY_PAYZEN_CTX_MODE === 'TEST') && ly_payzen::$payzen_plugin_features['prodfaq']) {
            $info->_addInfoSession(TEXT_PAYZEN_GOING_INTO_PROD_INFO, 'info');
        }

        $status = $order->order_data['orders_status_id'];
        if ($status === LY_PAYZEN_ORDER_STATUS_NEW) {
            // Order not treated yet.
            if ($this->payzenResponse->isAcceptedPayment()) {
                $order->_sendOrderMail();
                $this->_updateOrderStatus(LY_PAYZEN_SUCCESS_ORDER_STATUS, true, $this->payzenResponse->get('trans_id'), $this->payzenResponse->getLogMessage());
                $this->_savePayment($this->payzenResponse);

                $this->_cleanSession();

                if ($this->from_server) {
                    $this->logger->info("Payment processed successfully by IPN URL call for order #$this->orders_id.");
                    $this->logger->info('IPN URL PROCESS END.');
                    $this->_die($this->payzenResponse->getOutputForGateway('payment_ok'));
                } else {
                    $this->logger->warn("Warning! IPN URL call has not worked. Payment completed by return URL call for order #$this->orders_id.");
                    // Order saved by client return, inform merchant in TEST mode.
                    if (LY_PAYZEN_CTX_MODE === 'TEST') {
                        $info->_addInfoSession(TEXT_PAYZEN_CHECK_URL_WARN . '<br />' . TEXT_PAYZEN_CHECK_URL_WARN_DETAIL, 'warning');
                    }

                    $this->logger->info('RETURN URL PROCESS END.');

                    // Redirect to success page.
                    $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'success')));
                }
            } else {
                $this->_updateOrderStatus(LY_PAYZEN_FAILED_ORDER_STATUS, true, $this->payzenResponse->get('trans_id'), $this->payzenResponse->getLogMessage());

                if (! $this->payzenResponse->isCancelledPayment()) {
                    $this->_savePayment($this->payzenResponse);
                }

                if ($this->from_server) {
                    $this->logger->info("Payment failed or cancelled for order #$this->orders_id. {$this->payzenResponse->getLogMessage()}");
                    $this->logger->info('IPN URL PROCESS END.');
                    $this->_die($this->payzenResponse->getOutputForGateway('payment_ko'));
                } else {
                    $this->logger->info("Payment failed or cancelled for order #$this->orders_id. {$this->payzenResponse->getLogMessage()}");
                    $info->_addInfoSession(TEXT_PAYZEN_PAYMENT_ERROR, 'error');
                    $this->logger->info('RETURN URL PROCESS END.');

                    // Redirect to payment process.
                    $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'payment')));
                }
            }
        } else {
            $this->logger->info("Order #$this->orders_id is already saved.");
            if ($this->payzenResponse->isAcceptedPayment() && ($status === LY_PAYZEN_SUCCESS_ORDER_STATUS)) {
                $this->logger->info("Payment successful confirmed for order #$this->orders_id.");

                if ($this->from_server) {
                    $this->logger->info('IPN URL PROCESS END.');
                    $this->_die($this->payzenResponse->getOutputForGateway('payment_ok_already_done'));
                } else {
                    // Redirect to success page.
                    $this->logger->info('RETURN URL PROCESS END.');
                    $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'success')));
                }
            } elseif (! $this->payzenResponse->isAcceptedPayment() && ($status === LY_PAYZEN_FAILED_ORDER_STATUS)) {
                $this->logger->error("Error! Invalid payment result received for already saved order #$this->orders_id  Payment result : {$this->payzenResponse->getTransStatus()}, Order status : {$status}.");

                if ($this->from_server) {
                    $this->logger->info('IPN URL PROCESS END.');
                    $this->_die($this->payzenResponse->getOutputForGateway('payment_ko_already_done'));
                } else {
                    $info->_addInfoSession(TEXT_PAYZEN_PAYMENT_ERROR, 'error');
                    $this->logger->info('RETURN URL PROCESS END.');

                    // Redirect to payment process.
                    $xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'payment')));
                }
            } else {
                $this->logger->info("Payment failed or cancelled confirmed for order #$this->orders_id.");

                if ($this->from_server) {
                    $this->logger->info('IPN URL PROCESS END.');
                    $this->_die($this->payzenResponse->getOutputForGateway('payment_ok_on_order_ko'));
                } else {
                    $info->_addInfoSession(TEXT_PAYZEN_TECHNICAL_ERROR, 'error');
                    $this->logger->info('RETURN URL PROCESS END.');
                    $xtLink->_redirect($xtLink->_link(array('page' => 'index')));
                }
            }
        }
    }

    function _savePayment($payzenResponse)
    {
        global $db;

        $currency = PayzenApi::findCurrencyByNumCode($payzenResponse->get('currency'));

        $expiry = '';
        if ($payzenResponse->get('expiry_month') && $payzenResponse->get('expiry_year')) {
            $expiry = str_pad ($payzenResponse->get ('expiry_month'), 2, '0', STR_PAD_LEFT) . ' / ' . $payzenResponse->get('expiry_year');
        }

        $amount = $payzenResponse->get('amount');
        $floatAmount = round($currency->convertAmountToFloat($amount), $currency->getDecimals());
        $amountDetail = $floatAmount . ' ' . $currency->getAlpha3();

        // Save effective amount in the currency selected by the client.
        if ($payzenResponse->get('effective_currency') &&
            $payzenResponse->get('currency') !== $payzenResponse->get('effective_currency')) {
            $effectiveCurrency = PayzenApi::findCurrencyByNumCode($payzenResponse->get('effective_currency'));

            // Get effective amount.
            if ((substr($payzenResponse->get('payment_config'), 0, 5) === 'MULTI') && ($rate = $payzenResponse->get('change_rate'))) {
                $effectiveAmount = (int) ($amount / $rate);
            } else {
                $effectiveAmount = $payzenResponse->get('effective_amount');
            }

            $floatEffectiveAmount = round($currency->convertAmountToFloat($effectiveAmount), $effectiveCurrency->getDecimals());
            $amountDetail = $amountDetail . ' (' . $floatEffectiveAmount . ' ' . $effectiveCurrency->getAlpha3() . ')';
        }

        $paymentInfo = array(
            'PAYZEN_MESSAGE' => $payzenResponse->getLogMessage(),
            'PAYZEN_TRANSACTION_TYPE' => $payzenResponse->get('operation_type'),
            'PAYZEN_AMOUNT' => $amountDetail,
            'PAYZEN_TRANSACTION_ID' => $payzenResponse->get('trans_id'),
            'PAYZEN_TRANSACTION_STATUS' => $payzenResponse->get('trans_status'),
            'PAYZEN_PAYMENT_MEAN' => $payzenResponse->get('card_brand'),
            'PAYZEN_CARD_NUMBER' => $payzenResponse->get('card_number'),
            'PAYZEN_EXPIRATION_DATE' => $expiry
        );

        if ($this->multi_option) {
            $paymentInfo['PAYZEN_MULTI_OPTION'] = $this->multi_option;
        }

        $db->AutoExecute(TABLE_ORDERS, array('orders_data' => serialize($paymentInfo)), 'UPDATE', 'orders_id=' . $this->orders_id);
    }

    function _cleanSession()
    {
        $_SESSION['success_order_id'] = $_SESSION['last_order_id'];

        unset($_SESSION['last_order_id']);
        unset($_SESSION['selected_shipping']);
        unset($_SESSION['selected_payment']);
        unset($_SESSION['conditions_accepted']);

        $_SESSION['cart']->_resetCart();
    }

    function _die($message)
    {
        die($message);
    }
}
