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

defined ('_VALID_CALL') or die ('Direct Access is not allowed.');

/**
 * Callback class to process return from payment platform.
 */
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/payzen_api.php';
class callback_ly_payzen extends callback {
	var $version = '1.0.1';

	private $from_server = false;

	function __construct() {
		$this->from_server = key_exists('vads_hash', $_REQUEST) && isset($_REQUEST['vads_hash']);

		if($this->from_server) {
			// reload initial session
			preg_match('#^([^=]+)=([^=]+)#', $_REQUEST['vads_order_info'], $matches) ;

			session_write_close();

			session_name($matches[1]);
			session_id($matches[2]);
			session_start();
		}
	}

	function process() {
		global $xtLink, $info;

		$payzenResponse = new PayzenResponse (
				$_REQUEST,
				LY_PAYZEN_CTX_MODE,
				LY_PAYZEN_KEY_TEST,
				LY_PAYZEN_KEY_PROD
		);

		$this->orders_id = (int)$payzenResponse->get('order_id');
		$this->customers_id = (int)$payzenResponse->get('cust_id');

		// prepare callback log data
		$log_data = array();
		$log_data['module'] = 'ly_payzen';
		$log_data['orders_id'] = $this->orders_id;
		$log_data['transaction_id'] = $payzenResponse->get('trans_id');

		// check request authenticity
		if (! $payzenResponse->isAuthentified()) {
			$log_data['class'] = 'error';
			$log_data['error_msg'] = 'Authentication error';
			$log_data['error_data'] = serialize($payzenResponse->raw_response);
			$this->_addLogEntry($log_data);

			if($this->from_server) {
				die($payzenResponse->getOutputForGateway('auth_fail'));
			} else {
				$info->_addInfoSession(TEXT_PAYZEN_PAYMENT_ERROR, 'error');
				$xtLink->_redirect($xtLink->_link(array ('page' => 'index')));
			}
		}

		// load order data from DB
		$order = new order($this->orders_id, $this->customers_id);
		if(!is_array($order->order_data)) {
			$log_data['class'] = 'error';
			$log_data['error_msg'] = 'Order not found';
			$log_data['error_data'] = serialize($payzenResponse->raw_response);
			$this->_addLogEntry($log_data);

			if($this->from_server) {
				die($payzenResponse->getOutputForGateway('order_not_found'));
			} else {
				$info->_addInfoSession(TEXT_PAYZEN_PAYMENT_ERROR, 'error');
				$xtLink->_redirect($xtLink->_link(array ('page' => 'index')));
			}
		}

		if(!$this->from_server && LY_PAYZEN_CTX_MODE === 'TEST') {
			$info->_addInfoSession(TEXT_PAYZEN_GOING_INTO_PROD_INFO . ': <a href="https://secure.payzen.eu/html/faq/prod" target="_blank">https://secure.payzen.eu/html/faq/prod</a>', 'info');
		}

		$status = $order->order_data['orders_status_id'];

		if($status === LY_PAYZEN_ORDER_STATUS_NEW) {
			// order not treated yet

			if ($payzenResponse->isAcceptedPayment()) {
				$log_data['class'] = 'success';
				$log_data['callback_data'] = serialize($payzenResponse->raw_response);
				$this->_addLogEntry($log_data);

				$order->_sendOrderMail();
				$this->_updateOrderStatus(LY_PAYZEN_SUCCESS_ORDER_STATUS, true, $payzenResponse->get('trans_id'), $payzenResponse->getLogString ());
				$this->_savePayment($payzenResponse);

				$this->_cleanSession();

				if($this->from_server) {
					die ($payzenResponse->getOutputForGateway('payment_ok'));
				} else {
					// order saved by client return, inform merchant in TEST mode
					if(LY_PAYZEN_CTX_MODE === 'TEST') {
						$info->_addInfoSession(TEXT_PAYZEN_CHECK_URL_WARN . '<br />' . TEXT_PAYZEN_CHECK_URL_WARN_DETAIL, 'warning');
					}

					// redirect to success page
					$xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'success')));
				}
			} else {
				$log_data['class'] = $payzenResponse->isCancelledPayment() ? 'cancel' : 'failure';
				$log_data['error_msg'] = $payzenResponse->getLogString();
				$log_data['error_data'] = serialize($payzenResponse->raw_response);
				$this->_addLogEntry($log_data);

				$this->_updateOrderStatus(LY_PAYZEN_FAILED_ORDER_STATUS, true, $payzenResponse->get('trans_id'), $payzenResponse->getLogString ());
				if (!$payzenResponse->isCancelledPayment()) {
					$this->_savePayment($payzenResponse); // save refused transaction details
				}

				if($this->from_server) {
					die ($payzenResponse->getOutputForGateway('payment_ko'));
				} else {
					$info->_addInfoSession(TEXT_PAYZEN_PAYMENT_FAILURE, 'error');

					// redirect to payment process
					$xtLink->_redirect($xtLink->_link(array ('page' => 'checkout', 'paction' => 'payment')));
				}
			}
		} else {
			if ($payzenResponse->isAcceptedPayment() && $status === LY_PAYZEN_SUCCESS_ORDER_STATUS) {
				if($this->from_server) {
					die ($payzenResponse->getOutputForGateway('payment_ok_already_done'));
				} else {
					// redirect to success page
					$xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'success')));
				}
			} elseif(!$payzenResponse->isAcceptedPayment() && $status == LY_PAYZEN_FAILED_ORDER_STATUS) {
				if($this->from_server) {
					die ($payzenResponse->getOutputForGateway('payment_ko_already_done'));
				} else {
					$info->_addInfoSession(TEXT_PAYZEN_PAYMENT_FAILURE, 'error');

					// redirect to payment process
					$xtLink->_redirect($xtLink->_link(array('page' => 'checkout', 'paction' => 'payment')));
				}
			} else {
				if($this->from_server) {
					die ($payzenResponse->getOutputForGateway('payment_ok_on_order_ko'));
				} else {
					$info->_addInfoSession(TEXT_PAYZEN_PAYMENT_ERROR, 'error');
					$xtLink->_redirect($xtLink->_link(array ('page' => 'index')));
				}
			}
		}
	}

	function _savePayment($payzenResponse) {
		global $db;

		$currency = $payzenResponse->api->findCurrencyByNumCode($payzenResponse->get('currency'));

		$expiry = '';
		if ($payzenResponse->get('expiry_month') && $payzenResponse->get('expiry_year')) {
			$expiry = str_pad ($payzenResponse->get ('expiry_month'), 2, '0', STR_PAD_LEFT) . ' / ' . $payzenResponse->get('expiry_year');
		}

		$paymentInfo = array(
				'PAYZEN_MESSAGE' => $payzenResponse->getLogString(),
				'PAYZEN_TRANSACTION_TYPE' => $payzenResponse->get('operation_type'),
				'PAYZEN_AMOUNT' => $currency->convertAmountToFloat($payzenResponse->get('amount')) . ' ' . $currency->alpha3,
				'PAYZEN_TRANSACTION_ID' => $payzenResponse->get('trans_id'),
				'PAYZEN_TRANSACTION_STATUS' => $payzenResponse->get('trans_status'),
				'PAYZEN_PAYMENT_MEAN' => $payzenResponse->get('card_brand'),
				'PAYZEN_CARD_NUMBER' => $payzenResponse->get('card_number'),
				'PAYZEN_EXPIRATION_DATE' => $expiry
		);

		if($payzenResponse->get('order_info2') != '') {
			$paymentInfo['PAYZEN_MULTI_OPTION'] = $payzenResponse->get('order_info2');
		}

		$db->AutoExecute(TABLE_ORDERS, array ('orders_data' => serialize($paymentInfo)), 'UPDATE', 'orders_id=' . $this->orders_id);
	}

	function _cleanSession() {
		$_SESSION['success_order_id'] = $_SESSION ['last_order_id'];

		unset($_SESSION['last_order_id']);
		unset($_SESSION['selected_shipping']);
		unset($_SESSION['selected_payment']);
		unset($_SESSION['conditions_accepted']);

		$_SESSION['cart']->_resetCart();
	}
}