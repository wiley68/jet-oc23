<?php

namespace JetFinancialBaseline;

require __DIR__ . '/submission-fixture.php';

$count = 0;
function verifySubmission($message, $expected, $actual) {
    if ($expected !== $actual) {
        throw new \RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $GLOBALS['count']++;
}
function postFor($cart, $channel, $quote, $term = '10', $down = '0') {
    $prefix = $cart ? 'jet_' : $channel . '_';
    $post = array('quote' => $quote['quote'], $prefix . 'vnoski' => $term, $prefix . 'parva' => $down,
        $prefix . 'egn' => '0000000000');
    if ($cart) {
        $post += array('jet_card' => $channel === 'jet_card' ? '1' : '0', 'jet_lname' => '',
            'jet_name' => 'Test', 'jet_lastname' => 'Buyer', 'jet_phone' => '0000000000', 'jet_email' => 'buyer@example.test');
    }
    return $post;
}
function rejects($message, $reason, $fixture, $cart, $channel, $post, $rules = array(), $settings = array()) {
    $GLOBALS['jet_fixture_effects'] = array();
    $result = submitFixture($fixture, $cart, $channel, $post, $rules, $settings);
    verifySubmission($message, array('success' => 'unsuccess', 'reason' => $reason), $result);
    verifySubmission($message . ' has no side effects', array(), $GLOBALS['jet_fixture_effects']);
}

$rules = array(promotion(1, 43, '0.00', '10'), promotion(2, 40, '0.00', '10'), promotion(3, 42, '1.00', '10'));
$GLOBALS['jet_fixture_accept_mail'] = true;
$fixture = new SubmissionFixture();
$offer = submissionQuote($fixture, true, 'jet', '10', '0', $rules);
verifySubmission('43 + 40 zero-interest rate', '0', $offer['offer']['rate']);
$post = postFor(true, 'jet', $offer);
rejects('Forged product field', 'quote_mismatch', $fixture, true, 'jet', $post + array('jet_products' => '42'), $rules);
rejects('Forged total field', 'quote_mismatch', $fixture, true, 'jet', $post + array('jet_priceall' => '1.00'), $rules);
rejects('Forged installment', 'quote_mismatch', $fixture, true, 'jet', $post + array('jet_vnoska' => '1.00'), $rules);
rejects('Stale reference', 'stale_quote', $fixture, true, 'jet', array_replace($post, array('quote' => 'missing')), $rules);
$expires = $fixture->session->data['jet_quotes'][$post['quote']]['expires'];
$fixture->session->data['jet_quotes'][$post['quote']]['expires'] = time() - 1;
rejects('Expired reference', 'stale_quote', $fixture, true, 'jet', $post, $rules);
$fixture->session->data['jet_quotes'][$post['quote']]['expires'] = $expires;
rejects('Quote reference from another session', 'stale_quote', new SubmissionFixture(), true, 'jet', $post, $rules);
rejects('Term outside supported set', 'term_not_allowed', $fixture, true, 'jet', array_replace($post, array('jet_vnoski' => '4')), $rules);
rejects('Invalid down payment', 'invalid_down_payment', $fixture, true, 'jet', array_replace($post, array('jet_parva' => '450')), $rules);
rejects('Insufficient installment', 'insufficient_installment', $fixture, true, 'jet', array_replace($post, array('jet_parva' => '400')), $rules);
rejects('Changed exact promotion to prohibition', 'term_prohibited', $fixture, true, 'jet', $post,
    array(promotion(1, 43, '-1.00', '10')));
rejects('Changed rate while remaining eligible', 'stale_quote', $fixture, true, 'jet', $post);
rejects('Module disabled', 'channel_disabled', $fixture, true, 'jet', $post, $rules, array('mt_jet_credit_status' => 0));
$card = submissionQuote($fixture, true, 'jet_card', '10', '0', $rules);
rejects('Card disabled', 'channel_disabled', $fixture, true, 'jet_card', postFor(true, 'jet_card', $card),
    $rules, array('mt_jet_credit_status_card' => 0));
$fixture->session->data['currency'] = 'BGN';
rejects('Non-EUR session', 'invalid_currency', $fixture, true, 'jet', $post, $rules);
$fixture->session->data['currency'] = 'EUR';
$fixture->products[0]['quantity'] = 2;
rejects('Quantity changed', 'stale_quote', $fixture, true, 'jet', $post, $rules);
$fixture->products[0]['quantity'] = 1;
$row = array_pop($fixture->products);
rejects('A row was removed', 'stale_quote', $fixture, true, 'jet', $post, $rules);
$fixture->products[] = $row;
foreach (array('coupon', 'voucher', 'shipping') as $component) {
    $fixture->components[$component] = 10;
    rejects($component . ' changed', 'stale_quote', $fixture, true, 'jet', $post, $rules);
    $fixture->components[$component] = 0;
}
foreach (array(43, 40) as $product) {
    $fixture->products = array($fixture->product($product, 1), $fixture->product(42, 2));
    rejects($product . ' + 42 at 10 months', 'rate_mismatch', $fixture, true, 'jet', $post, $rules);
    verifySubmission('Alternative term available ' . $product, true,
        submissionQuote($fixture, true, 'jet', '9', '0', $rules)['offer']['eligible']);
}

$fixture = new SubmissionFixture();
$GLOBALS['jet_fixture_effects'] = array();
$offer = submissionQuote($fixture, true, 'jet', '10', '0', $rules);
$post = postFor(true, 'jet', $offer);
$GLOBALS['jet_fixture_concurrent'] = function () use ($fixture, $post, $rules) {
    return submitFixture($fixture, true, 'jet', $post, $rules);
};
verifySubmission('Verified Cart submits', 'success', submitFixture($fixture, true, 'jet', $post, $rules)['success']);
verifySubmission('Concurrent Cart handler refuses inside the first mail call', 'submission_uncertain',
    $GLOBALS['jet_fixture_concurrent_result']['reason']);
verifySubmission('Cart side-effect ordering', array('counter', 'mail', 'order', 'history', 'clear'), $GLOBALS['jet_fixture_effects']);
verifySubmission('Cart order uses verified products and total', array('43', '450.00'),
    array($fixture->created['products'][0]['product_id'], $fixture->created['total']));
rejects('Cart repeat after success or client response timeout', 'already_processed', $fixture, true, 'jet', $post, $rules);

foreach (array('jet', 'jet_card') as $channel) {
    $fixture = new SubmissionFixture($channel);
    $fixture->session->data['order_id'] = $channel === 'jet_card' ? 43 : 42;
    $offer = submissionQuote($fixture, false, $channel, '10', '0', $rules);
    $post = postFor(false, $channel, $offer);
    $fixture->products[0]['quantity'] = 2;
    rejects('Checkout order mismatch ' . $channel, 'stale_order', $fixture, false, $channel, $post, $rules);
    $fixture->products[0]['quantity'] = 1;
    $fixture->session->data['payment_method']['code'] = 'unrelated';
    rejects('Wrong Checkout channel ' . $channel, 'payment_method_changed', $fixture, false, $channel, $post, $rules);
    $fixture->session->data['payment_method']['code'] = $channel;
    $GLOBALS['jet_fixture_effects'] = array();
    $GLOBALS['jet_fixture_concurrent'] = function () use ($fixture, $channel, $post, $rules) {
        return submitFixture($fixture, false, $channel, $post, $rules);
    };
    $result = submitFixture($fixture, false, $channel, $post, $rules);
    verifySubmission('Concurrent Checkout handler refuses for ' . $channel, 'submission_uncertain',
        $GLOBALS['jet_fixture_concurrent_result']['reason']);
    verifySubmission('Checkout unchanged success redirect ' . $channel, array('success' => 'success', 'redirect' => 'checkout/success'), $result);
    verifySubmission('Checkout order and email chronology ' . $channel, array('counter', 'mail', 'history'), $GLOBALS['jet_fixture_effects']);
    rejects('Reused Checkout order ' . $channel, 'already_processed', $fixture, false, $channel, $post, $rules);
}

// The fixture emulates a transport rejection followed by a later explicit retry.
$fixture = new SubmissionFixture();
$fixture->session->data['order_id'] = 44;
$post = postFor(false, 'jet', submissionQuote($fixture, false, 'jet', '10', '0'));
$GLOBALS['jet_fixture_accept_mail'] = false;
$GLOBALS['jet_fixture_effects'] = array();
verifySubmission('Transport rejection is retryable', 'transport_failed', submitFixture($fixture, false, 'jet', $post)['reason']);
verifySubmission('Transport rejection has no order update', array('counter', 'mail'), $GLOBALS['jet_fixture_effects']);
$GLOBALS['jet_fixture_accept_mail'] = true;
$fixture->failHistory = true;
$GLOBALS['jet_fixture_effects'] = array();
verifySubmission('Accepted transport and failed order update is uncertain', 'submission_uncertain',
    submitFixture($fixture, false, 'jet', $post)['reason']);
verifySubmission('Uncertain email was attempted once', array('counter', 'mail', 'history'), $GLOBALS['jet_fixture_effects']);
$fixture->failHistory = false;
rejects('Uncertain outcome requires manual reconciliation', 'submission_uncertain', $fixture, false, 'jet', $post);

// Separate store/connection doubles share committed rows. Actual SQL concurrency is tested separately where available.
$memory = new SubmissionMemory();
foreach (array('cart', 'checkout') as $scope) {
    $keys = array(hash('sha256', 'worker-' . $scope));
    if ($scope === 'checkout') $keys[] = hash('sha256', 'order-worker');
    $lease = fixtureSubmissionStore($memory)->claim($keys, 'jet')['lease'];
    verifySubmission('Second ' . $scope . ' request sees committed sending', 'submission_uncertain',
        fixtureSubmissionStore($memory)->claim($keys, 'jet_card')['reason']);
    $lease->mark('accepted');
    $lease->mark('complete');
    unset($lease);
    verifySubmission('New connection sees durable ' . $scope . ' completion', 'already_processed',
        fixtureSubmissionStore($memory)->claim($keys, 'jet')['reason']);
}
$key = hash('sha256', 'crashed');
$worker = fixtureSubmissionStore($memory)->claim(array($key), 'jet');
verifySubmission('Intent persisted before simulated worker interruption', 'sending', $memory->rows[$key]['state']);
unset($worker);
verifySubmission('New connection after simulated worker loss refuses resend', 'submission_uncertain',
    fixtureSubmissionStore($memory)->claim(array($key), 'jet')['reason']);
printf("%d submission checks passed (database doubles).\n", $count);
