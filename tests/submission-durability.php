<?php

namespace JetFinancialBaseline;

require __DIR__ . '/submission-fixture.php';
require_once DIR_SYSTEM . 'library/jet_submission.php';

$count = 0;
function durableCheck($label, $expected, $actual) {
    if ($expected !== $actual) {
        throw new \RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $GLOBALS['count']++;
}

function durablePost($fixture, $cart, $channel, $term = '10') {
    $quote = submissionQuote($fixture, $cart, $channel, $term, '0');
    $prefix = $cart ? 'jet_' : $channel . '_';
    $post = array('quote' => $quote['quote'], $prefix . 'vnoski' => $term, $prefix . 'parva' => '0',
        $prefix . 'egn' => '0000000000');
    if ($cart) {
        $post += array('jet_card' => $channel === 'jet_card' ? '1' : '0', 'jet_lname' => '',
            'jet_name' => 'Test', 'jet_lastname' => 'Buyer', 'jet_phone' => '0000000000', 'jet_email' => 'buyer@example.test');
    }
    return $post;
}

function durableReject($label, $reason, $fixture, $cart, $channel, $post) {
    $GLOBALS['jet_fixture_effects'] = array();
    durableCheck($label, array('success' => 'unsuccess', 'reason' => $reason), submitFixture($fixture, $cart, $channel, $post));
    durableCheck($label . ' does not send or update', array(), $GLOBALS['jet_fixture_effects']);
}

function durableKeys($fixture, $post) {
    return $fixture->session->data['jet_quotes'][$post['quote']]['keys'];
}

$GLOBALS['jet_fixture_accept_mail'] = true;
$GLOBALS['jet_fixture_effects'] = array();
$fixture = new SubmissionFixture();
$initialLines = $fixture->products;
$post = durablePost($fixture, true, 'jet');
$crossRoute = durablePost($fixture, false, 'jet');
$journal = durableKeys($fixture, $post)[0];
durableReject('A Cart CC value must be one mailbox', 'invalid_applicant_email',
    $fixture, true, 'jet', array_replace($post, array('jet_email' => 'not-an-address')));
durableCheck('Cart completes', 'success', submitFixture($fixture, true, 'jet', $post)['success']);
durableCheck('Completion is persisted after Cart clear', 'complete', $fixture->ledger->rows[$journal]['state']);
durableReject('Old reference after Cart clear', 'already_processed', $fixture, true, 'jet', $post);
durableReject('Checkout cannot submit the completed Cart rows', 'already_processed', $fixture, false, 'jet', $crossRoute);

// Expiration and reclamation of session records must leave durable completion alone.
$fixture->session->data['jet_quotes'][$post['quote']]['expires'] = time();
durableReject('Exact expiration boundary', 'stale_quote', $fixture, true, 'jet', $post);
$fixture->session->data['jet_quotes'] = array();
$fixture->session->session_id = 'replaced-test-session';
$fixture->products = $initialLines;
durableReject('Old reference after session replacement', 'stale_quote', $fixture, true, 'jet', $post);
$fresh = durablePost($fixture, true, 'jet_card', '9');
durableCheck('Session replacement produces a distinct reference', false, $fresh['quote'] === $post['quote']);
durableReject('Fresh Card quote over the same Cart rows', 'already_processed', $fixture, true, 'jet_card', $fresh);
durableCheck('Journal survives quote expiry, deletion and replacement', 'complete', $fixture->ledger->rows[$journal]['state']);

$fixture->products[0]['cart_id'] += 10000;
$fixture->products[1]['cart_id'] += 10000;
$recreated = durablePost($fixture, true, 'jet_card');
durableCheck('Recreated rows receive a distinct application identity', false, $journal === durableKeys($fixture, $recreated)[0]);
durableReject('Old reference cannot authorize a recreated Cart', 'stale_quote', $fixture, true, 'jet', $post);

$fixture = new SubmissionFixture();
$fixture->failHistory = true;
$post = durablePost($fixture, false, 'jet');
$journals = durableKeys($fixture, $post);
$GLOBALS['jet_fixture_effects'] = array();
durableCheck('Accepted email followed by history failure', 'submission_uncertain',
    submitFixture($fixture, false, 'jet', $post)['reason']);
foreach ($journals as $journal) {
    durableCheck('Both Checkout identities recorded acceptance', 'uncertain', $fixture->ledger->rows[$journal]['state']);
}
$fixture->failHistory = false;
$fixture->session->data['order_id'] = 43;
$fixture->session->data['payment_method']['code'] = 'jet_card';
$fixture->saveFixtureOrder();
$reordered = durablePost($fixture, false, 'jet_card', '9');
durableReject('New Checkout order and channel for uncertain Cart rows', 'submission_uncertain',
    $fixture, false, 'jet_card', $reordered);
durableReject('Cart submission after uncertain Checkout', 'submission_uncertain',
    $fixture, true, 'jet', durablePost($fixture, true, 'jet'));
$fixture->session->data['order_id'] = 42;
$fixture->products[0]['cart_id'] += 10000;
$fixture->products[1]['cart_id'] += 10000;
$fixture->saveFixtureOrder();
durableReject('Same uncertain order after Cart row IDs changed', 'submission_uncertain',
    $fixture, false, 'jet_card', durablePost($fixture, false, 'jet_card'));

$emptySession = (object)array('data' => array());
$key = hash('sha256', 'offer limit');
for ($i = 0; $i < 25; $i++) {
    $reference = \JetSubmission::remember($emptySession, 'cart', array($key), 'binding-' . $i, 'jet');
    if ($i === 0) $oldest = $reference;
}
durableCheck('Only 24 references remain', 24, count($emptySession->data['jet_quotes']));
durableCheck('The oldest reference was evicted', null, \JetSubmission::lookup($emptySession, $oldest, 'cart', 'jet'));
durableCheck('Most recent reference is still usable', 'binding-24',
    \JetSubmission::lookup($emptySession, $reference, 'cart', 'jet')['binding']);
$expires = $emptySession->data['jet_quotes'][$reference]['expires'];
durableCheck('Reference lifetime is 900 seconds', true, $expires - time() >= 899 && $expires - time() <= 900);
durableCheck('Reusing a reference does not extend its expiry', $reference,
    \JetSubmission::remember($emptySession, 'cart', array($key), 'binding-24', 'jet'));
durableCheck('Expiry unchanged', $expires, $emptySession->data['jet_quotes'][$reference]['expires']);

$fixture = new SubmissionFixture();
$post = durablePost($fixture, true, 'jet');
$fixture->connectionFailed = true;
$GLOBALS['jet_fixture_effects'] = array();
durableCheck('Database connection failure fails closed', 'submission_unavailable', submitFixture($fixture, true, 'jet', $post)['reason']);
$fixture->connectionFailed = false;
$fixture->ledger->schema = false;
durableCheck('Missing table fails closed', 'submission_unavailable', submitFixture($fixture, true, 'jet', $post)['reason']);
$fixture->ledger->schema = true;
$key = durableKeys($fixture, $post)[0];
$fixture->ledger->rows[$key] = array('execution_id' => '', 'channel' => 'jet', 'state' => 'failed');
durableCheck('Invalid durable row fails closed', 'submission_uncertain', submitFixture($fixture, true, 'jet', $post)['reason']);
unset($fixture->ledger->rows[$key]);

$fixture = new SubmissionFixture();
$fixture->session->data['order_id'] = 44;
$post = durablePost($fixture, false, 'jet');
$keys = durableKeys($fixture, $post);
sort($keys);
$connection = new SubmissionSqlFixture($fixture->ledger);
$connection->fault = function ($sql) use ($keys) {
    return strpos($sql, 'INSERT INTO ') === 0 && strpos($sql, $keys[1]) !== false;
};
$fixture->connection = $connection;
durableCheck('Failed second identity write prevents transport', 'submission_unavailable',
    submitFixture($fixture, false, 'jet', $post)['reason']);
durableCheck('The first identity was rolled back too', false, isset($fixture->ledger->rows[$keys[0]]));
durableCheck('No side effects on unavailable/corrupt/partial claims', array(), $GLOBALS['jet_fixture_effects']);

$lease = fixtureSubmissionStore(new SubmissionMemory())->claim(array(hash('sha256', 'finished')), 'jet')['lease'];
$lease->execute(function () use ($lease) {
    $lease->mark('failed');
    return array('success' => 'unsuccess', 'reason' => 'transport_failed');
});
$afterClose = $lease->execute(function () { throw new \RuntimeException('Closed lease must not execute'); });
durableCheck('Closed lease never authorizes transport', 'submission_unavailable', $afterClose['reason']);

printf("%d additional submission durability checks passed (database doubles).\n", $count);
