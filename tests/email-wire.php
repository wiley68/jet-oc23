<?php

namespace OriginalJet;

// Both versions are exercised with captured mail and fixture database services.
function mail($to, $subject, $body, $headers) {
    $GLOBALS['original_mail'] = array($to, $subject, $body, $headers);
    return false;
}

require __DIR__ . '/submission-fixture.php';
class_alias('JetFinancialBaseline\Services', 'OriginalJet\Controller');

$baseline = '4bbc8fa70da7b029f3ff4cac9bf785174b73fe2c';
$root = dirname(__DIR__);
foreach (array('mt_jet_credit/jetcredit', 'payment/jet', 'payment/jet_card') as $route) {
    $gitObject = $baseline . ':upload/catalog/controller/extension/' . $route . '.php';
    $source = shell_exec('git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg($gitObject));
    if (!is_string($source) || substr($source, 0, 5) !== '<?php'
        || strpos($source, 'namespace ') !== false || preg_match('~(?:\\\\mail|call_user_func|sendmail)~i', $source)) {
        throw new \RuntimeException('Unable to load the controller in the isolated mail namespace.');
    }
    eval('namespace OriginalJet;' . substr($source, 5));
}
class Cart extends ControllerExtensionMtJetCreditJetCredit { use \JetFinancialBaseline\CaptureWithoutWrites; }
class Jet extends ControllerExtensionPaymentJet { use \JetFinancialBaseline\CaptureWithoutWrites; }
class Card extends ControllerExtensionPaymentJetCard { use \JetFinancialBaseline\CaptureWithoutWrites; }

$passed = 0;
foreach (array(true, false) as $cart) {
    foreach (array('jet', 'jet_card') as $channel) {
        $fixture = new \JetFinancialBaseline\SubmissionFixture($channel);
        $fixture->ledger = \JetFinancialBaseline\fixtureInstalledLedger();
        $fixture->products = array($fixture->product(101, 1));
        $fixture->products[0]['quantity'] = 2;
        $fixture->products[0]['total'] = 450;
        $fixture->saveFixtureOrder();
        $prefix = $cart ? 'jet_' : $channel . '_';
        $data = array('priceall' => '450.00', 'parva' => '50.00', 'vnoski' => '10',
            'vnoska' => $channel === 'jet' ? '43.20' : '43.60',
            'products' => '101', 'products_qt' => '2', 'products_pr' => $cart ? '225' : '225.00',
            'products_vr' => $cart ? json_encode(array(array(array('value' => 'Blue')))) : 'Blue', 'egn' => '0000000000');
        $post = array();
        foreach ($data as $name => $value) $post[$prefix . $name] = $value;
        if ($cart) {
            $post += array('jet_card' => $channel === 'jet' ? '0' : '1', 'jet_lname' => '',
                'jet_name' => 'Test', 'jet_lastname' => 'Buyer', 'jet_phone' => '0000000000',
                'jet_email' => 'buyer@example.test');
        }
        unset($GLOBALS['original_mail'], $GLOBALS['jet_fixture_mail']);
        $services = array('session' => $fixture->session, 'load' => $fixture, 'url' => $fixture,
            'model_checkout_order' => $fixture, 'model_catalog_product' => $fixture, 'model_catalog_category' => $fixture,
            'request' => (object)array('post' => $post), 'response' => new \JetFinancialBaseline\Response(),
            'config' => \JetFinancialBaseline\config());
        if ($cart) {
            $legacy = new Cart($services);
            $legacy->jet_send_cart();
        } else {
            $legacy = $channel === 'jet' ? new Jet($services) : new Card($services);
            $legacy->confirm();
        }
        $quote = \JetFinancialBaseline\submissionQuote($fixture, $cart, $channel, '10', '50.00');
        $post['quote'] = $quote['quote'];
        if ($cart) $post['jet_products_vr'] = $quote['products']['jet_products_vr'];
        \JetFinancialBaseline\submitFixture($fixture, $cart, $channel, $post);
        if (!isset($GLOBALS['jet_fixture_mail'], $GLOBALS['original_mail'])) {
            throw new \RuntimeException('Expected both versions to reach captured mail.');
        }
        $actual = $GLOBALS['jet_fixture_mail'];
        $actual[1] = $GLOBALS['jet_fixture_raw_subject'];
        if ($actual !== $GLOBALS['original_mail']) {
            throw new \RuntimeException('Wire mismatch for ' . ($cart ? 'Cart ' : 'Checkout ') . $channel);
        }
        $passed++;
    }
}
printf("%d raw email comparisons against %s passed.\n", $passed, $baseline);

// Additional Product check against the same immutable transport baseline.
$fixture = new \JetFinancialBaseline\SubmissionFixture();
$fixture->ledger = \JetFinancialBaseline\fixtureInstalledLedger();
$post = \JetFinancialBaseline\productSubmissionPost();
unset($GLOBALS['original_mail'], $GLOBALS['jet_fixture_mail']);
$legacy = new Cart(array('session' => $fixture->session, 'load' => $fixture,
    'model_catalog_product' => $fixture, 'model_catalog_category' => $fixture,
    'request' => (object)array('post' => $post), 'response' => new \JetFinancialBaseline\Response(),
    'config' => \JetFinancialBaseline\config()));
$legacy->jet_send();
\JetFinancialBaseline\submitFixture($fixture, 'product', 'jet', $post);
if (!isset($GLOBALS['jet_fixture_mail'], $GLOBALS['original_mail'])) {
    throw new \RuntimeException('Both Product versions must reach captured mail.');
}
$actual = $GLOBALS['jet_fixture_mail'];
$actual[1] = $GLOBALS['jet_fixture_raw_subject'];
if ($actual !== $GLOBALS['original_mail']) throw new \RuntimeException('Product wire mismatch.');
echo "1 additional Product raw email comparison passed.\n";
