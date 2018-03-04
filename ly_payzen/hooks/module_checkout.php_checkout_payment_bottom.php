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

if(isset($_payment) && ($_payment == 'ly_payzen') && isset($_payment_sub) && ($_payment_sub == 'multi')) {
	// store selected option in session
	$_SESSION['payzen_multi_opt'] = $_POST['payzen_multi_opt'];
}