<?php

namespace JetFinancialBaseline;

require_once __DIR__ . '/checkout-financing.php';

require_once DIR_SYSTEM . 'library/jet_submission.php';
require_once __DIR__ . '/submission-sql-fixture.php';
define('HTTP_SERVER', 'http://example.test/');

class SubmissionCartController extends CartController {
    protected function submissionStore() {
        $fixture = $this->load;
        if ($fixture->connectionFailed) throw new \RuntimeException('Fixture connection failure');
        return fixtureSubmissionStore($fixture->ledger, $fixture->connection);
    }
}

class SubmissionFixture extends CheckoutFixture {
    public $created;
    public $failHistory = false;
    public $ledger;
    public $connection;
    public $connectionFailed = false;
    private $createdStatus = 0;
    private $rowOffset;

    public function __construct($channel = 'jet') {
        static $nextOffset = 0;
        static $ledger;
        if (!$ledger) {
            $ledger = fixtureInstalledLedger();
        }
        $this->ledger = $ledger;
        $this->rowOffset = $nextOffset += 100;
        parent::__construct();
        $this->session->session_id = bin2hex(random_bytes(8));
        $this->session->data['payment_method']['code'] = $channel;
        $this->saveFixtureOrder();
    }

    public function product($id, $line) {
        return parent::product($id, $this->rowOffset + $line);
    }

    public function saveFixtureOrder() {
        parent::saveFixtureOrder();
        $this->order += array('firstname' => 'Test', 'lastname' => 'Buyer', 'telephone' => '0000000000',
            'email' => 'buyer@example.test');
    }

    public function getOrder($id) {
        return $id === 71 ? array('order_status_id' => $this->createdStatus) : parent::getOrder($id);
    }
    public function getId($code = null) { return $code === null ? 4 : 1; }
    public function getCustomer($id) {
        return $this->order + array('customer_group_id' => 1, 'fax' => '', 'custom_field' => '[]');
    }
    public function getCategories($id) { return array(array('category_id' => 1)); }
    public function getCategory($id) { return array('name' => 'Fixtures'); }
    public function getProduct($id) {
        return array('name' => 'Test product', 'model' => 'MODEL', 'price' => 225,
            'subtract' => false, 'tax_class_id' => 0, 'reward' => 0);
    }
    public function controller($route, $data) {
        if ($route === 'extension/mt_jet_credit/jetcredit/prepareSubmission') {
            return $this->controller->prepareSubmission($data);
        }
        return parent::controller($route, $data);
    }
    public function link($route) { return $route; }
    public function addOrder($data) {
        $this->created = $data;
        $GLOBALS['jet_fixture_effects'][] = 'order';
        return 71;
    }
    public function addOrderHistory($id, $status) {
        $GLOBALS['jet_fixture_effects'][] = 'history';
        if ($this->failHistory) throw new \RuntimeException('Test history failure');
        if ($id === 71) $this->createdStatus = $status;
        else $this->order['order_status_id'] = $status;
    }
    public function clear() {
        $GLOBALS['jet_fixture_effects'][] = 'clear';
        $this->products = array();
    }
}

function submissionQuote($fixture, $cart, $channel, $term, $down, $promotions = array(), $settings = array()) {
    $initial = array('mode' => 'initial', 'channel' => $channel, 'order' => (string)$fixture->session->data['order_id']);
    $result = checkoutRequest($fixture, $initial, $promotions, $settings, $cart);
    $post = array_replace($initial, array('mode' => 'explicit', 'context' => $result['context'], 'term' => $term,
        'down_payment' => $down));
    return checkoutRequest($fixture, $post, $promotions, $settings, $cart);
}

function submitFixture($fixture, $cart, $channel, $post, $promotions = array(), $settings = array()) {
    $settings += array('mt_jet_credit_count' => 42, 'mt_jet_credit_id' => 'JET-FIXTURE',
        'mt_jet_credit_mail' => 'finance@example.test', 'config_email' => 'shop@example.test',
        'jet_order_status_id' => 3, 'jet_card_order_status_id' => 4);
    $services = checkoutServices($fixture, $post, $promotions, $settings);
    $services['request']->server = array('HTTPS' => false, 'REMOTE_ADDR' => '127.0.0.1');
    $services['request']->cookie = array();
    $services += array('url' => $fixture, 'model_catalog_product' => $fixture, 'model_catalog_category' => $fixture,
        'model_account_customer' => $fixture);
    $fixture->controller = new SubmissionCartController($services);
    if ($cart === 'product') {
        $fixture->controller->jet_send();
    } elseif ($cart) {
        $fixture->controller->jet_send_cart();
    } else {
        $ctrl = $channel === 'jet' ? new CheckoutJetController($services) : new CheckoutCardController($services);
        $ctrl->confirm();
    }
    return $services['response']->output;
}

function productSubmissionPost() {
    return array('jet_card'=>'0', 'jet_lname'=>'', 'jet_name'=>'Test', 'jet_lastname'=>'Buyer',
        'jet_egn'=>'0000000000', 'jet_phone'=>'0000000000', 'jet_email'=>'buyer@example.test',
        'jet_product_id'=>'101', 'jet_quantity'=>'2', 'jet_variation_id'=>'[{"value":"Blue"}]',
        'jet_priceall'=>'450.00', 'jet_vnoska'=>'43.20', 'jet_parva'=>'50.00', 'jet_vnoski'=>'10');
}
