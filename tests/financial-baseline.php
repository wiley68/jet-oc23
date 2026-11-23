<?php

// The controllers are loaded into this test namespace so mail() cannot reach transport.
namespace JetFinancialBaseline;

function mail($to, $subject, $body, $headers) {
    $GLOBALS['jet_fixture_mail'] = array($to, mb_decode_mimeheader($subject), $body, $headers);
    $GLOBALS['jet_fixture_raw_subject'] = $subject;
    if (isset($GLOBALS['jet_fixture_effects'])) $GLOBALS['jet_fixture_effects'][] = 'mail';
    if (isset($GLOBALS['jet_fixture_concurrent'])) {
        $concurrent = $GLOBALS['jet_fixture_concurrent'];
        unset($GLOBALS['jet_fixture_concurrent']);
        $GLOBALS['jet_fixture_concurrent_result'] = $concurrent();
    }
    return !empty($GLOBALS['jet_fixture_accept_mail']);
}

class Services {
    private $services;

    public function __construct(array $services) {
        $this->services = $services;
    }

    public function __get($name) {
        return $this->services[$name];
    }
}

class Config {
    private $values;

    public function __construct(array $values) {
        $this->values = $values;
    }

    public function get($key) {
        return isset($this->values[$key]) ? $this->values[$key] : null;
    }
}

class Response {
    public $output;

    public function addHeader($header) {}

    public function setOutput($output) {
        $this->output = json_decode($output, true);
    }
}

class Environment {
    public function model($route) {}
    public function link($route) { return $route; }
    public function getCategories($id) { return array(array('category_id' => 1)); }
    public function getCategory($id) { return array('name' => 'Fixtures'); }
    public function getProduct($id) { return array('name' => 'Test product'); }
    public function getOrder($id) {
        return array('firstname' => 'Test', 'lastname' => 'Buyer', 'telephone' => '0000000000',
            'email' => 'buyer@example.test');
    }
}

class PromotionDatabase {
    public $lookups = array();
    private $rows;

    public function __construct(array $rows) {
        $this->rows = $rows;
    }

    public function escape($value) {
        return addslashes($value);
    }

    public function query($sql) {
        // Assert the production SQL contract, then emulate its SELECT on fixtures.
        $pattern = '~^\s*SELECT jet_product_percent, jet_product_id, id\s+FROM fixture_jet_kop\s+'
            . "WHERE \\(jet_product_id = '\\*' OR jet_product_id = '([0-9_]+)'\\)\\s+"
            . 'AND FIND_IN_SET\(([0-9]+), REPLACE\(jet_product_meseci, \'_\', \',\'\)\)\s+'
            . 'AND ([0-9.Ee+-]+) >= jet_product_price\s+'
            . 'AND jet_product_start <= CURDATE\(\)\s+AND jet_product_end >= CURDATE\(\)\s+'
            . "ORDER BY\\s+CASE WHEN jet_product_id = '\\1' THEN 0 ELSE 1 END,\\s+id ASC\\s+LIMIT 1\\s*\$~";
        if (!preg_match($pattern, $sql, $match)) {
            throw new \RuntimeException('Unexpected database query: ' . $sql);
        }
        list(, $product, $term, $principal) = $match;
        $this->lookups[] = array($product, (int)$term, (float)$principal);
        $today = '2026-11-23';
        $matches = array();
        foreach ($this->rows as $row) {
            if (($row['jet_product_id'] === $product || $row['jet_product_id'] === '*')
                && in_array($term, explode('_', $row['terms']), true)
                && $principal >= $row['minimum'] && $row['start'] <= $today && $row['end'] >= $today) {
                $matches[] = $row;
            }
        }
        usort($matches, function ($left, $right) use ($product) {
            $priority = (int)($right['jet_product_id'] === $product) - (int)($left['jet_product_id'] === $product);
            return $priority ?: $left['id'] - $right['id'];
        });
        return (object)array('num_rows' => count($matches), 'row' => $matches ? $matches[0] : array());
    }
}

function promotion($id, $product, $rate, $terms = '12', $minimum = '0.00',
    $start = '2026-01-01', $end = '2026-12-31') {
    return array('id' => $id, 'jet_product_id' => (string)$product, 'jet_product_percent' => $rate,
        'terms' => $terms, 'minimum' => $minimum, 'start' => $start, 'end' => $end);
}

$root = dirname(__DIR__);
define('DB_PREFIX', 'fixture_');
class_alias(__NAMESPACE__ . '\Services', 'Model');
require $root . '/upload/catalog/model/extension/mt_jet_credit/jetcredit.php';
class_alias(__NAMESPACE__ . '\Services', __NAMESPACE__ . '\Controller');
foreach (array('mt_jet_credit/jetcredit', 'payment/jet', 'payment/jet_card') as $file) {
    $source = file_get_contents($root . '/upload/catalog/controller/extension/' . $file . '.php');
    if (substr($source, 0, 5) !== '<?php' || strpos($source, 'namespace ') !== false
        || preg_match('~(?:\\\\mail|call_user_func|sendmail)~i', $source)) {
        throw new \RuntimeException('Controller is incompatible with isolated mail capture.');
    }
    eval('namespace ' . __NAMESPACE__ . ';' . substr($source, 5));
}

trait CaptureWithoutWrites {
    public function editSettingValue($code = '', $key = '', $value = '', $store_id = 0) {
        if (isset($GLOBALS['jet_fixture_effects'])) $GLOBALS['jet_fixture_effects'][] = 'counter';
    }
}
class CartController extends ControllerExtensionMtJetCreditJetCredit { use CaptureWithoutWrites; }
class CheckoutJetController extends ControllerExtensionPaymentJet { use CaptureWithoutWrites; }
class CheckoutCardController extends ControllerExtensionPaymentJetCard { use CaptureWithoutWrites; }

function config($jet = '0.80', $card = '0.90') {
    return new Config(array('mt_jet_credit_purcent' => $jet, 'mt_jet_credit_purcent_card' => $card,
        'mt_jet_credit_price' => '100', 'mt_jet_credit_vnoski' => '12', 'mt_jet_credit_count' => '42',
        'mt_jet_credit_id' => 'JET-FIXTURE', 'mt_jet_credit_mail' => 'finance@example.test',
        'config_email' => 'shop@example.test'));
}

function controller($class, $config, $db, $post, $currency = 'EUR', $channel = 'jet') {
    $environment = new Environment();
    return new $class(array('config' => $config, 'db' => $db, 'load' => $environment,
        'url' => $environment, 'model_catalog_product' => $environment,
        'model_catalog_category' => $environment, 'model_checkout_order' => $environment,
        'response' => new Response(), 'request' => (object)array('post' => $post),
        'session' => (object)array('data' => array('currency' => $currency, 'order_id' => 42,
            'payment_method' => array('code' => $channel))),
        'model_extension_mt_jet_credit_jetcredit' => new \ModelExtensionMtJetCreditJetcredit(
            array('db' => $db, 'config' => $config))));
}

function baseline() {
    $rows = array(
        promotion(1, '*', '1.10'),
        promotion(4, 101, '0.50', '12', '350.00', '2026-11-23', '2026-11-23'),
        promotion(2, 101, '0.00', '12', '400.00', '2026-11-23', '2026-11-23'),
        promotion(5, 102, '0.00', '12', '0.00', '2026-01-01', '2026-11-22'),
        promotion(6, 103, '0.00', '12', '0.00', '2026-11-24'),
        promotion(7, 104, '-1.00')
    );
    $db = new PromotionDatabase($rows);
    $result = array('finance' => array(), 'mail' => array());
    $cases = array(
        'cart_standard' => array('cart', '101', '450.00', '50.00', '10'),
        'cart_exact_zero_at_threshold' => array('cart', '101', '450.00', '50.00', '12'),
        'cart_exact_under_higher_priority_threshold' => array('cart', '101', '450.00', '50.01', '12'),
        'cart_global_below_exact_threshold' => array('cart', '101', '450.00', '100.01', '12'),
        'cart_global_after_exact_expiry' => array('cart', '102', '450.00', '50.00', '12'),
        'cart_global_before_exact_start' => array('cart', '103', '450.00', '50.00', '12'),
        'cart_legacy_combined_id' => array('cart', '101_104', '450.00', '50.00', '12'),
        'cart_legacy_ignores_prohibition' => array('cart', '104', '450.00', '50.00', '12'),
        'product_prohibition_flag' => array('product', '104', '450.00', '50.00', '12'),
        'product_zero_at_threshold_with_option' => array('product', '101', '450.00', '50.00', '12'),
        'cart_non_eur' => array('cart', '101', '450.00', '50.00', '12')
    );
    foreach ($cases as $name => $case) {
        list($flow, $product, $total, $down, $term) = $case;
        $post = array('jet_products' => $product, 'jet_product_id' => $product, 'jet_priceall' => $total,
            'jet_parva' => $down, 'jet_vnoski' => $term, 'jet_products_qt' => '2',
            'jet_products_pr' => '225.00', 'jet_products_vr' => json_encode(array(array(array('value' => 'Blue')))));
        $ctrl = controller(__NAMESPACE__ . '\CartController', config(), $db, $post,
            $name === 'cart_non_eur' ? 'BGN' : 'EUR');
        if ($flow === 'cart') {
            $ctrl->jet_calculate_cart();
        } else {
            $ctrl->jet_calculate();
        }
        $result['finance'][$name] = $ctrl->response->output;
    }

    $common = array('priceall' => '450.00', 'parva' => '50.00', 'vnoski' => '10',
        'vnoska' => '43.20', 'products' => '101', 'products_qt' => '2', 'products_pr' => '225.00',
        'products_vr' => 'Blue', 'egn' => '0000000000');
    foreach (array('cart_jet', 'cart_card', 'checkout_jet', 'checkout_card') as $flow) {
        $isCart = strpos($flow, 'cart_') === 0;
        $channel = substr($flow, -4) === 'card' ? 'jet_card' : 'jet';
        $input = $common;
        if ($channel === 'jet_card') {
            $input['vnoska'] = '43.60';
        }
        $post = array();
        $prefix = !$isCart && $channel === 'jet_card' ? 'jet_card_' : 'jet_';
        foreach ($input as $key => $value) {
            $post[$prefix . $key] = $value;
        }
        require_once __DIR__ . '/submission-fixture.php';
        $fixture = new SubmissionFixture($channel);
        $fixture->ledger = fixtureInstalledLedger();
        $fixture->products = array($fixture->product(101, 1));
        $fixture->products[0]['quantity'] = 2;
        $fixture->products[0]['total'] = 450;
        $fixture->saveFixtureOrder();
        if ($isCart) {
            $post += array('jet_card' => $channel === 'jet_card' ? '1' : '0', 'jet_lname' => '',
                'jet_name' => 'Test', 'jet_lastname' => 'Buyer', 'jet_phone' => '0000000000',
                'jet_email' => 'buyer@example.test');
            // The offer supplies the same Blue variant from the fixture Cart.
            unset($post['jet_products_vr'], $post['jet_products_pr']);
        }
        unset($GLOBALS['jet_fixture_mail']);
        $GLOBALS['jet_fixture_accept_mail'] = false;
        $selected = submissionQuote($fixture, $isCart, $channel, '10', '50.00');
        $post['quote'] = $selected['quote'];
        submitFixture($fixture, $isCart, $channel, $post);
        if (!isset($GLOBALS['jet_fixture_mail'])) {
            throw new \RuntimeException('Mail fixture did not reach capture: ' . $flow);
        }
        $result['mail'][$flow] = $GLOBALS['jet_fixture_mail'];
    }
    return $result;
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $actual = baseline();
    $expected = json_decode(file_get_contents(__DIR__ . '/fixtures/financial-baseline.json'), true);
    foreach ($actual as $group => $cases) {
        foreach ($cases as $name => $value) {
            if (!isset($expected[$group][$name]) || $expected[$group][$name] !== $value) {
                throw new \RuntimeException('Baseline changed: ' . $name);
            }
        }
        if (array_keys($cases) !== array_keys($expected[$group])) {
            throw new \RuntimeException('Baseline case list changed: ' . $group);
        }
    }
    printf("%d financial and %d email baseline cases passed.\n", count($actual['finance']), count($actual['mail']));
}
