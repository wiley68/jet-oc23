<?php

namespace JetFinancialBaseline;

require_once __DIR__ . '/financial-baseline.php';
$root = dirname(__DIR__);
if (!defined('DIR_SYSTEM')) define('DIR_SYSTEM', $root . '/upload/system/');
foreach (array('mt_jet_credit/cart_financing', 'mt_jet_credit/checkout_financing', 'payment/jet', 'payment/jet_card') as $model) {
    require $root . '/upload/catalog/model/extension/' . $model . '.php';
}

class CheckoutFixture {
    public $products = array();
    public $components = array('sub_total' => 450, 'shipping' => 0, 'coupon' => 0, 'voucher' => 0);
    public $reads = 0;
    public $calculations = 0;
    public $shipping = false;
    public $currencyValue = 1;
    public $geoMatches = true;
    public $order;
    public $orderRows = array();
    public $session;
    public $controller;

    public function __construct() {
        $address = array('firstname' => 'Test', 'lastname' => 'Buyer', 'company' => '', 'address_1' => 'Main 1',
            'address_2' => '', 'city' => 'Sofia', 'postcode' => '1000', 'zone_id' => '9', 'country_id' => '33');
        $this->session = (object)array('data' => array('currency' => 'EUR', 'order_id' => 42,
            'payment_method' => array('code' => 'jet'), 'payment_address' => $address,
            'shipping_address' => $address, 'shipping_method' => array('code' => 'flat.flat')));
        $this->products = array($this->product(43, 1), $this->product(40, 2));
        $this->saveFixtureOrder();
    }

    public function product($id, $line) {
        return array('cart_id' => $line, 'product_id' => $id, 'quantity' => 1, 'price' => 225, 'total' => 225,
            'tax_class_id' => 0, 'option' => array(array('product_option_id' => '7', 'product_option_value_id' => '9',
                'name' => 'Colour', 'value' => 'Blue', 'type' => 'select')));
    }

    public function saveFixtureOrder() {
        $this->order = array('order_status_id' => 0, 'payment_code' => $this->session->data['payment_method']['code'],
            'currency_code' => 'EUR', 'currency_value' => $this->currencyValue, 'store_id' => 0, 'customer_id' => 4,
            'shipping_code' => $this->shipping ? 'flat.flat' : '', 'total' => array_sum($this->components));
        foreach (array('payment', 'shipping') as $kind) {
            foreach ($this->session->data[$kind . '_address'] as $name => $value) {
                $this->order[$kind . '_' . $name] = $value;
            }
        }
        $this->orderRows = array('product' => array(), 'option' => array(), 'total' => array());
        foreach (array_reverse($this->products) as $line) {
            $this->orderRows['product'][] = $line + array('order_product_id' => $line['cart_id'], 'tax' => '0.0000');
            foreach ($line['option'] as $option) {
                $this->orderRows['option'][] = $option + array('order_product_id' => $line['cart_id']);
            }
        }
        foreach (array_keys($this->components) as $index => $code) {
            $this->orderRows['total'][] = $this->totalRow($code, $index);
        }
    }

    public function totalRow($code, $index) {
        return array('code' => $code, 'title' => $code, 'value' => $this->components[$code], 'sort_order' => $index);
    }

    public function model($route) {}
    public function getOrder($id) { return in_array($id, array(42, 43, 44), true) ? $this->order + array('order_id' => $id) : false; }
    public function getProducts() { $this->reads++; return $this->products; }
    public function getTaxes() { return array(); }
    public function getTax($amount, $class) { return 0; }
    public function hasShipping() { return $this->shipping; }
    public function getId() { return 4; }
    public function getValue($code) { return $this->currencyValue; }
    public function isLogged() { return true; }
    public function calculate($price, $taxClass, $enabled) { return $price; }
    public function getExtensions($type) {
        return array_map(function ($code) { return array('code' => $code); }, array_keys($this->components));
    }

    public function controller($route, $data) {
        if ($route !== 'extension/mt_jet_credit/jetcredit/checkoutAvailability') throw new \RuntimeException($route);
        return $this->controller->checkoutAvailability($data);
    }

    public function query($sql) {
        if (strpos($sql, 'SELECT * FROM fixture_zone_to_geo_zone') === 0) {
            return (object)array('num_rows' => (int)$this->geoMatches);
        }
        if (preg_match('/^SELECT .+ FROM fixture_order_(product|option|total) WHERE order_id = (42|43|44)$/D', $sql, $match)) {
            return (object)array('rows' => $this->orderRows[$match[1]]);
        }
        throw new \RuntimeException('Unexpected DB query: ' . $sql);
    }
}

class CheckoutTotalFixture {
    private $fixture;
    private $code;
    private $index;
    public function __construct($fixture, $code, $index) {
        $this->fixture = $fixture;
        $this->code = $code;
        $this->index = $index;
    }
    public function getTotal($data) {
        $this->fixture->calculations++;
        $row = $this->fixture->totalRow($this->code, $this->index);
        $data['total'] += $row['value'];
        $data['totals'][] = $row;
    }
}

function checkoutServices(CheckoutFixture $fixture, array $post, array $promotions, array $settings = array()) {
    $settings += array('mt_jet_credit_status' => 1, 'mt_jet_credit_cart_show' => 1, 'mt_jet_credit_status_card' => 1,
        'mt_jet_credit_purcent' => '0.80', 'mt_jet_credit_purcent_card' => '0.90',
        'mt_jet_credit_price' => 0, 'jet_status' => 1, 'jet_card_status' => 1,
        'jet_sort_order' => 13, 'jet_card_sort_order' => 18, 'config_currency' => 'EUR', 'config_store_id' => 0);
    $services = array('response' => new Response(), 'request' => (object)array('post' => $post), 'session' => $fixture->session,
        'currency' => $fixture, 'load' => $fixture, 'db' => $fixture, 'cart' => $fixture, 'tax' => $fixture, 'customer' => $fixture,
        'model_checkout_order' => $fixture, 'model_extension_extension' => $fixture);
    foreach (array_keys($fixture->components) as $index => $code) {
        $settings[$code . '_status'] = 1;
        $settings[$code . '_sort_order'] = $index;
        $services['model_extension_total_' . $code] = new CheckoutTotalFixture($fixture, $code, $index);
    }
    $services['config'] = new Config($settings);
    $db = new PromotionDatabase($promotions);
    $services['model_extension_mt_jet_credit_jetcredit'] = new \ModelExtensionMtJetCreditJetcredit(
        array('db' => $db, 'config' => $services['config']));
    $services['model_extension_mt_jet_credit_cart_financing'] = new \ModelExtensionMtJetCreditCartFinancing($services);
    $services['model_extension_payment_jet'] = new \ModelExtensionPaymentJet($services);
    $services['model_extension_payment_jet_card'] = new \ModelExtensionPaymentJetCard($services);
    $services['model_extension_mt_jet_credit_checkout_financing'] = new \ModelExtensionMtJetCreditCheckoutFinancing($services);
    $fixture->controller = new CartController($services);
    return $services;
}

function checkoutRequest($fixture, $post, $promotions = array(), $settings = array(), $cart = false) {
    checkoutServices($fixture, $post, $promotions, $settings);
    if ($cart) {
        $fixture->controller->jet_cart_offer();
    } else {
        $fixture->controller->jet_checkout_offer();
    }
    return $fixture->controller->response->output;
}

function verifyCheckout($name, $expected, $actual) {
    if ($expected !== $actual) {
        throw new \RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $GLOBALS['checkout_checks']++;
}

if (realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) return;

$GLOBALS['checkout_checks'] = 0;
$fixture = new CheckoutFixture();
$address = $fixture->session->data['payment_address'];
$services = checkoutServices($fixture, array(), array());
verifyCheckout('JET exposed', 'jet', $services['model_extension_payment_jet']->getMethod($address, 450)['code']);
verifyCheckout('Card independently exposed', 18, $services['model_extension_payment_jet_card']->getMethod($address, 450)['sort_order']);
verifyCheckout('Availability reuses the same Cart snapshot', 1, $fixture->reads);
verifyCheckout('Availability reuses OpenCart totals', 0, $fixture->calculations);
$services = checkoutServices($fixture, array(), array(), array('mt_jet_credit_purcent' => '0', 'mt_jet_credit_purcent_card' => '1'));
verifyCheckout('JET cannot reach monthly floor', array(), $services['model_extension_payment_jet']->getMethod($address, 59));
verifyCheckout('Card can independently pass the monthly floor', 'jet_card',
    $services['model_extension_payment_jet_card']->getMethod($address, 59)['code']);
$fixture->geoMatches = false;
$services = checkoutServices($fixture, array(), array(), array('jet_geo_zone_id' => 4, 'jet_card_total' => 451));
verifyCheckout('Geo zone still rejects JET', array(), $services['model_extension_payment_jet']->getMethod($address, 450));
verifyCheckout('Per-method minimum still rejects Card', array(), $services['model_extension_payment_jet_card']->getMethod($address, 450));
$fixture->geoMatches = true;
$services = checkoutServices($fixture, array(), array(), array('mt_jet_credit_price' => 451));
verifyCheckout('Module minimum still applies', array(), $services['model_extension_payment_jet']->getMethod($address, 450));

$initial = array('mode' => 'initial', 'channel' => 'jet', 'order' => '42', 'order_id' => 999, 'jet_priceall' => '1.00');
$rows = array(promotion(1, 43, '0.00', '10'), promotion(2, 40, '0.00', '10'), promotion(3, 42, '1.00', '10'));
$result = checkoutRequest($fixture, $initial, $rows);
verifyCheckout('Initial selects first valid term', 3, $result['offer']['term']);
verifyCheckout('Checkout uses server total', '450.00', $result['offer']['financial']['jet_priceall']);
verifyCheckout('Legacy options serialization', 'Blue_Blue', $result['products']['products_vr']);
$cart = checkoutRequest($fixture, $initial, $rows, array(), true);
verifyCheckout('Initial Checkout and Cart numerical and eligibility parity', $cart['channels']['jet'], $result['offer']);
$explicit = array_replace($initial, array('mode' => 'explicit', 'context' => $result['context'], 'term' => '10', 'down_payment' => '0'));
$result = checkoutRequest($fixture, $explicit, $rows);
verifyCheckout('43 + 40 eligible at 0%', '0', $result['offer']['rate']);
verifyCheckout('43 + 40 installment', '45.00', $result['offer']['financial']['jet_vnoska']);
$cartExplicit = array_replace($explicit, array('context' => $cart['context']));
verifyCheckout('Explicit Checkout and Cart parity', checkoutRequest($fixture, $cartExplicit, $rows, array(), true)['offer'], $result['offer']);

$fixture->products[1]['product_id'] = 42;
$fixture->saveFixtureOrder();
$explicit['context'] = checkoutRequest($fixture, $initial, $rows)['context'];
$result = checkoutRequest($fixture, $explicit, $rows);
verifyCheckout('43 + 42 rejected', 'rate_mismatch', $result['offer']['reason']);
verifyCheckout('10-month invalid choice preserved', '10', $result['offer']['term']);
verifyCheckout('Refusal contains no financial values', false, isset($result['offer']['financial']));
$result = checkoutRequest($fixture, array_replace($explicit, array('term' => '9')), $rows);
verifyCheckout('Recovery with exact term', true, $result['offer']['eligible']);
$prohibited = array(promotion(3, 42, '-1.00', '10'));
verifyCheckout('Product prohibition', 'term_prohibited', checkoutRequest($fixture, $explicit, $prohibited)['offer']['reason']);
$equal = array(promotion(3, 42, '0.80', '10'));
verifyCheckout('Mixed equal configured and promotional rate', '0.8', checkoutRequest($fixture, $explicit, $equal)['offer']['rate']);
verifyCheckout('Down payment recomputes principal', '400.00',
    checkoutRequest($fixture, array_replace($explicit, array('down_payment' => '50.00')), $equal)['offer']['financial']['jet_total_credit_price']);
verifyCheckout('Down payment triggers month minimum', 'insufficient_installment',
    checkoutRequest($fixture, array_replace($explicit, array('down_payment' => '300.00')), $equal)['offer']['reason']);

$fixture->products[0]['quantity'] = 2;
verifyCheckout('Quantity mismatch even without a total change', 'stale_order', checkoutRequest($fixture, $explicit)['offer']['reason']);
$fixture->products[0]['quantity'] = 1;
$item = array_pop($fixture->products);
verifyCheckout('Removed product rejects current order', 'stale_order', checkoutRequest($fixture, $explicit)['offer']['reason']);
$fixture->products[] = $item;
$fixture->products[0]['option'][0]['value'] = 'Red';
verifyCheckout('Option changed without amount change', 'stale_order', checkoutRequest($fixture, $explicit)['offer']['reason']);
$fixture->products[0]['option'][0]['value'] = 'Blue';
$fixture->components['shipping'] = 10;
$fixture->components['voucher'] = -10;
verifyCheckout('Shipping and voucher changes net to same total but invalidate order', 'stale_order',
    checkoutRequest($fixture, $explicit)['offer']['reason']);
$fixture->components['coupon'] = -1;
verifyCheckout('Changed grand total rejects old order', 'stale_order', checkoutRequest($fixture, $initial)['offer']['reason']);
$fixture->components['coupon'] = 0;
$fixture->components['shipping'] = $fixture->components['voucher'] = 0;
$fixture->session->data['payment_address']['address_1'] = 'Other';
verifyCheckout('Changed billing address', 'stale_order', checkoutRequest($fixture, $initial)['offer']['reason']);
$fixture->session->data['payment_address']['address_1'] = 'Main 1';
$fixture->shipping = true;
$fixture->saveFixtureOrder();
$fixture->session->data['shipping_method']['code'] = 'other.other';
verifyCheckout('Changed shipping method', 'stale_order', checkoutRequest($fixture, $initial)['offer']['reason']);
$fixture->shipping = false;
$fixture->saveFixtureOrder();
$fixture->session->data['order_id'] = 43;
verifyCheckout('Old fragment reference rejected even with equivalent order contents', 'stale_order',
    checkoutRequest($fixture, $initial)['offer']['reason']);
verifyCheckout('Old explicit context rejected when order ID changes', 'stale_context',
    checkoutRequest($fixture, array_replace($explicit, array('order' => '43')))['offer']['reason']);
$fixture->session->data['order_id'] = 999;
verifyCheckout('Missing order rejected', 'stale_order', checkoutRequest($fixture, array_replace($initial, array('order' => '999')))['offer']['reason']);
$fixture->session->data['order_id'] = 42;
$fixture->order['order_status_id'] = 1;
verifyCheckout('Already confirmed order cannot quote', 'stale_order', checkoutRequest($fixture, $initial)['offer']['reason']);
$fixture->order['order_status_id'] = 0;
$fixture->session->data['payment_method']['code'] = 'jet_card';
verifyCheckout('Checkout payment change rejects old channel', 'payment_method_changed',
    checkoutRequest($fixture, $initial)['offer']['reason']);
$fixture->saveFixtureOrder();
$card = array_replace($initial, array('channel' => 'jet_card'));
$result = checkoutRequest($fixture, $card, array(), array('mt_jet_credit_cart_show' => 0, 'mt_jet_credit_status_card' => 0));
verifyCheckout('Checkout Card independent of Cart switches', true, $result['offer']['eligible']);
$fixture->session->data['currency'] = 'BGN';
verifyCheckout('Checkout EUR only', 'invalid_currency', checkoutRequest($fixture, $card)['offer']['reason']);
$fixture->session->data['currency'] = 'EUR';
$fixture->currencyValue = 1.1;
verifyCheckout('Order exchange rate no longer matches', 'stale_order', checkoutRequest($fixture, $card)['offer']['reason']);
$fixture->currencyValue = 1;

$baseline = json_decode(file_get_contents(__DIR__ . '/fixtures/financial-baseline.json'), true)['finance']['cart_standard'];
foreach (array('jet' => '', 'jet_card' => '_card') as $channel => $suffix) {
    $fixture->session->data['payment_method']['code'] = $channel;
    $fixture->saveFixtureOrder();
    $request = array_replace($initial, array('channel' => $channel));
    $state = checkoutRequest($fixture, $request);
    $state = checkoutRequest($fixture, array_replace($request, array('mode' => 'explicit', 'context' => $state['context'],
        'term' => '10', 'down_payment' => '50.00')));
    foreach ($state['offer']['financial'] as $field => $value) {
        $key = in_array($field, array('jet_priceall', 'jet_total_credit_price'), true) ? $field : $field . $suffix;
        verifyCheckout('Checkout exact financial baseline ' . $channel . ' ' . $field, $baseline[$key], $value);
    }
}
printf("%d Checkout PHP checks passed.\n", $GLOBALS['checkout_checks']);
