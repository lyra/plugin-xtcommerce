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
 * Provide drop-down lists for configuration.
 */
require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.PayzenApi.php';

if ($request['get'] === 'ly_payzen:cmodes') {
    $result[] = array(
        'id'   => 'TEST',
        'name' => 'TEST'
    );
    $result[] = array(
        'id'   => 'PRODUCTION',
        'name' => 'PRODUCTION'
    );
} elseif ($request['get'] === 'ly_payzen:vmodes') {
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
} elseif ($request['get'] === 'ly_payzen:rmodes') {
    $result[] = array(
        'id'   => 'GET',
        'name' => 'GET'
    );
    $result[] = array(
        'id'   => 'POST',
        'name' => 'POST'
    );
} elseif ($request['get'] === 'ly_payzen:redirects') {
    $result[] = array(
        'id'   => 'False',
        'name' => TEXT_PAYZEN_DISABLED
    );
    $result[] = array(
        'id'   => 'True',
        'name' => TEXT_PAYZEN_ENABLED
    );
} elseif ($request['get'] === 'ly_payzen:languages') {
    foreach (PayzenApi::getSupportedLanguages() as $key => $language) {
        $result[] = array(
            'id'   => $key,
            'name' => constant('TEXT_PAYZEN_' . strtoupper($language))
        );
    }
} elseif ($request['get'] === 'ly_payzen:cards') {
    foreach (PayzenApi::getSupportedCardTypes() as $key => $card) {
        $result[] = array(
            'id'   => $key,
            'name' => $card
        );
    }
} elseif ($request['get'] === 'ly_payzen:salgos') {
    // Add sign algo values.
    $result[] = array(
        'id'   => 'SHA-1',
        'name' => 'SHA-1'
    );
    $result[] = array(
        'id'   => 'SHA-256',
        'name' => 'HMAC-SHA-256'
    );
}
