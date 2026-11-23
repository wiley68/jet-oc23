<?php

namespace JetFinancialBaseline;

require __DIR__ . '/submission-fixture.php';
require __DIR__ . '/submission-admin-fixture.php';

function checkDefaultTerm($label, $expected, $actual) {
    if ($expected !== $actual) {
        throw new \RuntimeException($label . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
    $GLOBALS['defaultTermChecks']++;
}

function installmentOptions($source, $id, array $values) {
    if (!preg_match('~<select\b[^>]*\bid="' . preg_quote($id, '~') . '"[^>]*>.*?</select>~s', $source, $select)) {
        throw new \RuntimeException('Missing selector ' . $id);
    }
    extract($values, EXTR_SKIP);
    ob_start();
    eval('?>' . $select[0]);
    $html = ob_get_clean();
    preg_match_all('~<option\s+value="?(\d+)"?(\s+selected)?\s*>~', $html, $options, PREG_SET_ORDER);
    $terms = $selected = array();
    foreach ($options as $option) {
        $terms[] = $option[1];
        if (isset($option[2])) $selected[] = $option[1];
    }
    return array($terms, $selected);
}

$GLOBALS['defaultTermChecks'] = 0;
$root = dirname(__DIR__);
$template = file_get_contents($root . '/upload/admin/view/template/extension/module/mt_jet_credit.tpl');
$key = 'mt_jet_credit_vnoski';
$memory = new SubmissionMemory();
$memory->schema = false;
$memory->legacyReferences = array();
$admin = new class($memory) extends LedgerAdminServices {
    public function get($key) {
        return $this->settings[$key] ?? null;
    }
};
$admin->allowed[] = 'extension/module/mt_jet_credit';
adminFixture($admin)->install();
adminFixture($admin)->index();
$terms = explode(' ', '3 6 9 10 12 15 18 24 30 36');
checkDefaultTerm('All selectable terms in order and the original unset default', array($terms, array('12')),
    installmentOptions($template, $key, $admin->output));

$settings = array('mt_jet_credit_status' => '1', 'mt_jet_credit_gap' => '0',
    'mt_jet_credit_id' => 'JET-FIXTURE', 'mt_jet_credit_price' => '100');
adminFixture($admin, 'test-admin-token', $settings + array($key => '10'))->index();
checkDefaultTerm('Existing settings save succeeds', 'extension/module/mt_jet_credit?token=test-admin-token', $admin->redirected);
checkDefaultTerm('The setting stores 10', '10', $admin->getSetting('mt_jet_credit')[$key]);
adminFixture($admin)->index();
checkDefaultTerm('A fresh admin request renders the saved 10-month option as selected', array($terms, array('10')),
    installmentOptions($template, $key, $admin->output));
$saved = $admin->getSetting('mt_jet_credit');
foreach (array_diff($terms, array('10')) as $existing) {
    $admin->settings[$key] = $existing;
    adminFixture($admin)->index();
    checkDefaultTerm('Existing selection ' . $existing . ' is retained', array($existing),
        installmentOptions($template, $key, $admin->output)[1]);
}

class ProductPageFixture extends Services {
    public function productData($injection, $price) {
        $product_info = array('special' => 0, 'price' => $price, 'tax_class_id' => 0);
        $data = array();
        eval($injection);
        return $data;
    }
}

$xml = simplexml_load_file($root . '/cc_jet.ocmod.xml', 'SimpleXMLElement', LIBXML_NONET);
$product_controller = $xml->xpath('/modification/file[@path="catalog/controller/product/product.php"]/operation/add[@position="after"]');
$product_template = $xml->xpath('/modification/file[@path="catalog/view/theme/*/template/product/product.tpl"]/operation/add');
$page_services = new class extends Environment {
    public function isLogged() { return false; }
    public function calculate($price, $tax_class_id, $enabled) { return $price; }
    public function getJetWideButtonInlineStyle($scheme) { return ''; }
};
$config = new Config($saved + array('mt_jet_credit_purcent' => '0.80', 'mt_jet_credit_purcent_card' => '0.90'));
$page = new ProductPageFixture(array('config' => $config, 'customer' => $page_services, 'tax' => $page_services,
    'load' => $page_services, 'model_extension_mt_jet_credit_jetcredit' => $page_services,
    'request' => (object)array('get' => array('product_id' => 43)),
    'session' => (object)array('data' => array('currency' => 'EUR'))));
checkDefaultTerm('Existing Product price override still selects 9 below 125 EUR', '9',
    $page->productData((string)$product_controller[0], 124.99)['jet_vnoski']);
checkDefaultTerm('Product renders the saved default at the existing price boundary', array($terms, array('10')),
    installmentOptions((string)$product_template[0], 'jet_vnoski', $page->productData((string)$product_controller[0], 125)));

$promotions = new PromotionDatabase(array(promotion(1, '*', '1.10', '10'), promotion(2, 43, '0.00', '10')));
$post = array('jet_priceall' => '450.00', 'jet_product_id' => '43');
$quote = controller(CartController::class, $config, $promotions, $post);
$quote->jet_calculate();
checkDefaultTerm('Product calculator looks up the configured term', array(array('43', 10, 450.0)), $promotions->lookups);
checkDefaultTerm('The product-specific 10-month promotion still beats the global rate', '45.00', $quote->response->output['jet_vnoska']);
$prohibited = array(promotion(1, 43, '-1.00', '10'));
$quote = controller(CartController::class, $config, new PromotionDatabase($prohibited), $post);
$quote->jet_calculate();
checkDefaultTerm('Product calculation still hides prohibited 10-month financing', false, $quote->response->output['jet_show_button']);

foreach (array('jet', 'jet_card') as $channel) {
    $fixture = new CheckoutFixture();
    $fixture->session->data['payment_method']['code'] = $channel;
    $fixture->saveFixtureOrder();
    foreach (array(true, false) as $cart) {
        $surface = ($cart ? 'Cart ' : 'Checkout ') . $channel;
        $initial = array('mode' => 'initial', 'channel' => $channel, 'order' => '42');
        $default = array($key => $saved[$key]);
        $result = checkoutRequest($fixture, $initial, array(), $default, $cart);
        $offer = $cart ? $result['channels'][$channel] : $result['offer'];
        checkDefaultTerm($surface . ' selects the eligible 10-month default',
            array(10, true), array($offer['term'], in_array(10, $offer['eligible_terms'], true)));
        $result = checkoutRequest($fixture, $initial, $prohibited, $default, $cart);
        $offer = $cart ? $result['channels'][$channel] : $result['offer'];
        checkDefaultTerm($surface . ' excludes the prohibited default from eligible terms',
            array(3, false), array($offer['term'], in_array(10, $offer['eligible_terms'], true)));
        $explicit = array_replace($initial, array('mode' => 'explicit', 'context' => $result['context'],
            'term' => $saved[$key], 'down_payment' => '0'));
        $result = checkoutRequest($fixture, $explicit, $prohibited, $default, $cart);
        checkDefaultTerm($surface . ' rejects the prohibited default when submitted explicitly', 'term_prohibited', $result['offer']['reason']);
    }
}

$fixture = new CheckoutFixture();
$initial = array('mode' => 'initial', 'channel' => 'jet', 'order' => '42');
$rows = array(promotion(1, 43, '0.00', '10'), promotion(2, 40, '0.00', '10'));
foreach (array(true, false) as $cart) {
    $scope = $cart ? 'cart' : 'checkout';
    $result = checkoutRequest($fixture, $initial, $rows, array($key => '10'), $cart);
    $offer = $cart ? $result['channels']['jet'] : $result['offer'];
    checkDefaultTerm($scope . ' owner basket initial quote', array(10, '0', '45.00'),
        array($offer['term'], $offer['rate'], $offer['financial']['jet_vnoska']));
    $initialQuote = $cart ? $result['quotes']['jet'] : $result['quote'];
    $explicit = array_replace($initial, array('mode' => 'explicit', 'context' => $result['context'],
        'term' => '10', 'down_payment' => '0'));
    $exact = checkoutRequest($fixture, $explicit, $rows, array($key => '10'), $cart);
    $initialBinding = \JetSubmission::lookup($fixture->session, $initialQuote, $scope, 'jet');
    $explicitBinding = \JetSubmission::lookup($fixture->session, $exact['quote'], $scope, 'jet');
    checkDefaultTerm($scope . ' initial reference binds to the exact zero-down offer', true,
        isset($initialBinding['binding'], $explicitBinding['binding']) &&
        $initialBinding['binding'] === $explicitBinding['binding']);

    foreach (array('6' => 6, '24' => 24, '99' => 3, '6junk' => 3) as $configured => $expected) {
        $result = checkoutRequest($fixture, $initial, array(), array($key => $configured), $cart);
        $offer = $cart ? $result['channels']['jet'] : $result['offer'];
        checkDefaultTerm($scope . ' with configured term ' . $configured, $expected, $offer['term']);
    }
}

$result = checkoutRequest($fixture, $initial, array(promotion(1, 43, '0.80', '6')), array($key => '6'), true);
checkDefaultTerm('Cart applies the preference independently by channel', array(6, 3),
    array($result['channels']['jet']['term'], $result['channels']['jet_card']['term']));

foreach (array('124.99' => 9, '125.00' => 6) as $amount => $cartTerm) {
    $fixture->components['sub_total'] = (float)$amount;
    $fixture->saveFixtureOrder();
    // Both terms must pass the monthly floor to distinguish the two preferences.
    $settings = array($key => '6', 'mt_jet_credit_purcent' => '6');
    $result = checkoutRequest($fixture, $initial, array(), $settings, true);
    checkDefaultTerm('Cart initial preference at EUR ' . $amount, $cartTerm, $result['channels']['jet']['term']);
    $result = checkoutRequest($fixture, $initial, array(), $settings);
    checkDefaultTerm('Checkout initial preference at EUR ' . $amount, 6, $result['offer']['term']);
}

$fixture->components['sub_total'] = 125;
$fixture->saveFixtureOrder();
$result = checkoutRequest($fixture, $initial, array(), array($key => '10'), true);
checkDefaultTerm('Cart falls back when the configured term fails the monthly floor', array(3, false),
    array($result['channels']['jet']['term'], in_array(10, $result['channels']['jet']['eligible_terms'], true)));

foreach (array('400.99' => array(15, 3), '401.00' => array(15, 15),
    '600.99' => array(30, 3), '601.00' => array(30, 30)) as $amount => list($configured, $expected)) {
    $fixture->components['sub_total'] = (float)$amount;
    $fixture->saveFixtureOrder();
    $settings = array($key => (string)$configured);
    $cart = checkoutRequest($fixture, $initial, array(), $settings, true);
    $checkout = checkoutRequest($fixture, $initial, array(), $settings);
    checkDefaultTerm('Preferred term respects the principal threshold at EUR ' . $amount,
        array($expected, $expected), array($cart['channels']['jet']['term'], $checkout['offer']['term']));
}

printf("%d default installment checks passed.\n", $GLOBALS['defaultTermChecks']);
