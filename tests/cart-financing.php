<?php

namespace JetFinancialBaseline;

require __DIR__ . '/financial-baseline.php';
$root = dirname(__DIR__);
define('DIR_SYSTEM', $root . '/upload/system/');
require DIR_SYSTEM . 'library/jet_cart_eligibility.php';
require $root . '/upload/catalog/model/extension/mt_jet_credit/cart_financing.php';

class CartFixture {
    public $products;
    public $totals = array('sub_total' => 450, 'coupon' => 0, 'voucher' => 0, 'shipping' => 0, 'fee' => 0);
    public $calls = array();
    public $logged = true;

    public function getProducts() { return $this->products; }
    public function getTaxes() { return array(1 => 0); }
    public function isLogged() { return $this->logged; }
    public function calculate($amount, $taxClass, $enabled) { return $amount; }
    public function convert($amount, $from, $to) {
        if ($from !== 'BGN' || $to !== 'EUR') throw new \RuntimeException('Unexpected conversion');
        return $amount / 2;
    }
    public function getExtensions($type) {
        return array_map(function ($code) { return array('code' => $code); },
            array('fee', 'total', 'voucher', 'shipping', 'coupon', 'sub_total', 'disabled'));
    }
}

class TotalFixture {
    private $code;
    private $cart;
    public function __construct($code, CartFixture $cart) {
        $this->code = $code;
        $this->cart = $cart;
    }
    public function getTotal($data) {
        $this->cart->calls[] = $this->code;
        if ($this->code !== 'total') $data['total'] += $this->cart->totals[$this->code];
        // A totals extension can append a display value without changing the accumulator.
        $value = $this->code === 'total' ? $data['total'] : $this->cart->totals[$this->code];
        $data['totals'][] = array('code' => $this->code, 'value' => $value);
    }
}

function cartLine($id, $line = 1, $quantity = 2, $option = 'Blue') {
    return array('product_id' => $id, 'cart_id' => $line, 'quantity' => $quantity, 'price' => 225,
        'tax_class_id' => 1, 'option' => array(array('product_option_id' => 9, 'value' => $option)));
}

function cartRequest(CartFixture $cart, array $post, array $rows = array(), $currency = 'EUR', array $settings = array()) {
    $settings += array(
        'mt_jet_credit_status' => 1, 'mt_jet_credit_cart_show' => 1, 'mt_jet_credit_status_card' => 1,
        'mt_jet_credit_price' => 0, 'mt_jet_credit_purcent' => '0.80', 'mt_jet_credit_purcent_card' => '0.90',
        'config_currency' => 'EUR'
    );
    $codes = array('sub_total', 'shipping', 'coupon', 'voucher', 'total', 'fee', 'disabled');
    foreach ($codes as $index => $code) {
        $settings[$code . '_sort_order'] = $index;
        $settings[$code . '_status'] = $code !== 'disabled';
    }
    $services = array('load' => new Environment(), 'response' => new Response(),
        'request' => (object)array('post' => $post), 'session' => (object)array('data' => array('currency' => $currency)),
        'config' => new Config($settings), 'cart' => $cart, 'customer' => $cart, 'tax' => $cart, 'currency' => $cart,
        'model_extension_extension' => $cart);
    foreach ($codes as $code) {
        $services['model_extension_total_' . $code] = new TotalFixture($code, $cart);
    }
    $services['model_extension_mt_jet_credit_cart_financing'] = new \ModelExtensionMtJetCreditCartFinancing($services);
    $services['model_extension_mt_jet_credit_jetcredit'] = new \ModelExtensionMtJetCreditJetcredit(
        array('db' => new PromotionDatabase($rows)));
    $controller = new CartController($services);
    $controller->jet_cart_offer();
    return $controller->response->output;
}

function expectCart($name, $expected, $actual) {
    if ($expected !== $actual) {
        throw new \RuntimeException($name . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
    $GLOBALS['cart_checks']++;
}

$GLOBALS['cart_checks'] = 0;
$cart = new CartFixture();
$initial = array('mode' => 'initial');
$offer = array('mode' => 'explicit', 'channel' => 'jet', 'term' => '10', 'down_payment' => '0');
$ownerRows = array(promotion(1, 43, '0.00', '10'), promotion(2, 40, '0.00', '10'), promotion(3, 42, '1.00', '10'));
foreach (array(array(43, 40), array(43, 42), array(40, 42), array(43, 40, 42)) as $ids) {
    $cart->products = array();
    foreach ($ids as $line => $id) $cart->products[] = cartLine($id, $line + 1);
    $discovery = cartRequest($cart, $initial, $ownerRows, 'EUR');
    $request = $offer + array('context' => $discovery['context'], 'jet_products' => '42', 'jet_priceall' => '1.00');
    $checked = cartRequest($cart, $request, $ownerRows)['offer'];
    expectCart('Owner fixture term remains selected ' . implode('+', $ids), '10', $checked['term']);
    expectCart('Owner fixture eligibility ' . implode('+', $ids), $ids === array(43, 40), $checked['eligible']);
    if ($checked['eligible']) {
        expectCart('Zero-interest 10-month installment uses server total', '45.00', $checked['financial']['jet_vnoska']);
        expectCart('Owner fixture rate', '0', $checked['rate']);
    } else {
        expectCart('Owner fixture mismatch', 'rate_mismatch', $checked['reason']);
        expectCart('Invalid offer contains no financial values', false, isset($checked['financial']));
        $request['term'] = '9';
        expectCart('Recovery on exact valid term', true, cartRequest($cart, $request, $ownerRows)['offer']['eligible']);
    }
}

$cart->products = array(cartLine(43), cartLine(40, 2));
$rows = array(promotion(1, 43, '0.80', '6'), promotion(2, 43, '-1.00', '3'));
$result = cartRequest($cart, $initial, $rows);
expectCart('JET first valid term', 6, $result['channels']['jet']['term']);
expectCart('Card first valid term independently', 9, $result['channels']['jet_card']['term']);
expectCart('Ascending eligible terms after filtering', array(6, 9, 10, 12, 15, 18, 24), $result['channels']['jet']['eligible_terms']);
$request = array_replace($offer, array('term' => '3', 'context' => $result['context']));
expectCart('Explicit prohibition', 'term_prohibited', cartRequest($cart, $request, $rows)['offer']['reason']);
$disabled = cartRequest($cart, $initial, array(), 'EUR', array('mt_jet_credit_status_card' => 0));
expectCart('Card disabled', 'channel_disabled', $disabled['channels']['jet_card']['reason']);
expectCart('JET remains available', true, $disabled['channels']['jet']['eligible']);

foreach (array('59.98' => false, '59.99' => true, '60.00' => true) as $amount => $valid) {
    $cart->totals['sub_total'] = (float)$amount;
    $rates = array('mt_jet_credit_purcent' => '0', 'mt_jet_credit_purcent_card' => '0');
    $result = cartRequest($cart, $initial, array(), 'EUR', $rates);
    expectCart('Monthly floor on displayed installment ' . $amount, $valid, $result['channels']['jet']['eligible']);
    if ($valid) {
        expectCart('Displayed installment exactly 20 EUR', '20.00', $result['channels']['jet']['financial']['jet_vnoska']);
    } else {
        expectCart('Monthly floor rejection reason', 'insufficient_installment', $result['channels']['jet']['reason']);
    }
}
foreach (array('400.99' => 15, '401.00' => 15, '600.99' => 30, '601.00' => 30) as $amount => $term) {
    $cart->totals['sub_total'] = (float)$amount;
    $result = cartRequest($cart, $initial);
    $request = array_replace($offer, array('term' => (string)$term, 'context' => $result['context']));
    $expected = $amount === '401.00' || $amount === '601.00';
    expectCart('Amount boundary initial ' . $amount, $expected, in_array($term, $result['channels']['jet']['eligible_terms'], true));
    expectCart('Amount boundary explicit ' . $amount, $expected, cartRequest($cart, $request)['offer']['eligible']);
}

$cart->calls = array();
$cart->totals = array('sub_total' => 450, 'coupon' => -50, 'voucher' => -25, 'shipping' => 30, 'fee' => 5);
$result = cartRequest($cart, $initial);
expectCart('Active total extensions in configured order', array('sub_total', 'shipping', 'coupon', 'voucher', 'total', 'fee'), $cart->calls);
expectCart('Coupon, voucher, shipping and post-total fee use accumulator, not last display row',
    '410.00', $result['channels']['jet']['financial']['jet_priceall']);
$request = $offer + array('context' => $result['context']);
$changed = $request;
$changed['down_payment'] = '230.00';
expectCart('Changed down payment fails 20 EUR floor', 'insufficient_installment', cartRequest($cart, $changed)['offer']['reason']);
$changed['down_payment'] = '411.00';
expectCart('Invalid amount of down payment', 'invalid_down_payment', cartRequest($cart, $changed)['offer']['reason']);
$changed['down_payment'] = '50.00';
expectCart('Changed down payment uses current total', '360.00',
    cartRequest($cart, $changed)['offer']['financial']['jet_total_credit_price']);

$cart->products = array(cartLine(43, 3, 2, "Blue & 'white'"), cartLine(43, 4, 3, 'Red'));
expectCart('Changed lines stale prior context', 'stale_context', cartRequest($cart, $request)['offer']['reason']);
$result = cartRequest($cart, $initial, $ownerRows);
expectCart('Repeat base ID retains legacy transport serialization', '43_43', $result['products']['jet_products']);
expectCart('Quantities stay aligned with lines', '2_3', $result['products']['jet_products_qt']);
expectCart('Options stay aligned with lines and do not lose characters', "Blue & 'white'",
    json_decode($result['products']['jet_products_vr'], true)[0][0]['value']);
$request['context'] = $result['context'];
$cart->products[0]['quantity'] = 5;
expectCart('Quantity change invalidates context even at the same total', 'stale_context', cartRequest($cart, $request)['offer']['reason']);
$result = cartRequest($cart, $initial);
$request['context'] = $result['context'];
array_pop($cart->products);
expectCart('Product removal invalidates context', 'stale_context', cartRequest($cart, $request)['offer']['reason']);
$result = cartRequest($cart, $initial);
$request['context'] = $result['context'];
$cart->totals['shipping'] = 31;
expectCart('Shipping change invalidates context', 'stale_context', cartRequest($cart, $request)['offer']['reason']);
$result = cartRequest($cart, $initial);
$request['context'] = $result['context'];
$cart->totals['coupon'] = -60;
expectCart('Coupon change invalidates context', 'stale_context', cartRequest($cart, $request)['offer']['reason']);
$result = cartRequest($cart, $initial);
$request['context'] = $result['context'];
$cart->totals['shipping'] += 5;
$cart->totals['voucher'] -= 5;
expectCart('Totals breakdown changes invalidate even when final amount is equal', 'stale_context',
    cartRequest($cart, $request)['offer']['reason']);
expectCart('Non-EUR explicit fails closed', 'invalid_currency', cartRequest($cart, $request, array(), 'BGN')['offer']['reason']);
expectCart('Non-EUR initial fails closed', 'invalid_currency',
    cartRequest($cart, $initial, array(), 'BGN')['channels']['jet']['reason']);
$saved = $cart->products;
$cart->products = array();
expectCart('Empty Cart', 'empty_cart', cartRequest($cart, $initial)['channels']['jet']['reason']);
$cart->products = $saved;
$cart->logged = false;
expectCart('Guest with hidden prices cannot receive an offer', 'amount_out_of_range',
    cartRequest($cart, $initial, array(), 'EUR', array('config_customer_price' => 1))['channels']['jet']['reason']);
$cart->logged = true;
$cart->totals = array('sub_total' => 450, 'coupon' => 0, 'voucher' => 0, 'shipping' => 0, 'fee' => 0);
expectCart('Base currency is converted to active EUR', '225.00',
    cartRequest($cart, $initial, array(), 'EUR', array('config_currency' => 'BGN'))['channels']['jet']['financial']['jet_priceall']);

$result = cartRequest($cart, $initial);
$baseline = json_decode(file_get_contents(__DIR__ . '/fixtures/financial-baseline.json'), true)['finance']['cart_standard'];
foreach (array('jet' => '', 'jet_card' => '_card') as $channel => $suffix) {
    $request = array_replace($offer, array('channel' => $channel, 'down_payment' => '50.00', 'context' => $result['context']));
    $checked = cartRequest($cart, $request)['offer'];
    foreach ($checked['financial'] as $field => $value) {
        $key = in_array($field, array('jet_priceall', 'jet_total_credit_price'), true) ? $field : $field . $suffix;
        expectCart('Numerical baseline ' . $channel . ' ' . $field, $baseline[$key], $value);
    }
}
printf("%d Cart adapter and endpoint checks passed.\n", $GLOBALS['cart_checks']);
