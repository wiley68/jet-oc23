<?php

namespace JetFinancialBaseline;

require __DIR__ . '/submission-fixture.php';

$count = 0;
function counterCheck($label, $expected, $actual) {
    if ($expected !== $actual) throw new \RuntimeException($label . ': ' . var_export($actual, true));
    $GLOBALS['count']++;
}
function refused($label, $fn) {
    try {
        call_user_func($fn);
    } catch (\RuntimeException $e) {
        $GLOBALS['count']++;
        return;
    }
    throw new \RuntimeException($label . ' unexpectedly succeeded');
}

$memory = new SubmissionMemory();
$memory->referenceColumn = false;
$service = fixtureSubmissionStore($memory);
refused('Old schema cannot allocate before preparation', function () use ($service) { $service->reserveReference(); });
$existingKey = hash('sha256', 'old checkout application');
$memory->rows[$existingKey] = array('execution_id' => str_repeat('a',48), 'channel' => 'jet', 'state' => 'uncertain');
$service->prepare();
$service->prepareReference();
counterCheck('Additive schema upgrade', true, $memory->referenceColumn);
counterCheck('Old application remains protected', 'submission_uncertain', $service->blocked(array($existingKey)));
counterCheck('Legacy value means next to issue', '42', $service->reserveReference());
counterCheck('Resolved next number persisted', '43', fixtureSubmissionStore($memory)->nextReference());
$memory->legacyReferences[0]['value'] = '5';
$service->prepareReference();
counterCheck('Historical setting rollback cannot rewind migrated counter', '43', $service->nextReference());
counterCheck('Allocator has no manual counter override', false, method_exists($service, 'advanceReference'));
counterCheck('Repeat preparation preserves the next allocation', '43', $service->reserveReference());
$memory->legacyReferences = array();
$service->prepareReference(true);
counterCheck('Repeat preparation keeps an initialized counter without legacy settings', '44', $service->nextReference());

$counterKey = hash('sha256', 'jet-reference-counter:' . DB_PREFIX);
$memory->rows[$counterKey]['reference_next'] = '42';
refused('Single-row authoritative counter rollback conflicts with high water', function () use ($service) { $service->reserveReference(); });
refused('Preparation cannot silently repair inconsistent high water', function () use ($service) { $service->prepareReference(true); });
unset($memory->rows[$counterKey]);
refused('Missing counter row fails closed', function () use ($service) { $service->reserveReference(); });
refused('Marker prevents reseeding a deleted counter row', function () use ($service) { $service->prepareReference(true); });

foreach (array('', '0', '0042', 'abc', '42.0', '-1', '9223372036854775809') as $raw) {
    $bad = new SubmissionMemory();
    $bad->schema = false;
    $bad->legacyReferences[0]['value'] = $raw;
    $store = fixtureSubmissionStore($bad);
    $store->prepare();
    refused('Invalid legacy seed ' . $raw, function () use ($store) { $store->prepareReference(); });
    counterCheck('Invalid legacy seed rolls back preparation', array(), $bad->rows);
}
$bad = new SubmissionMemory();
$bad->schema = false;
$bad->legacyReferences[] = $bad->legacyReferences[0];
$duplicated = fixtureSubmissionStore($bad);
$duplicated->prepare();
refused('Duplicate legacy settings must fail', function () use ($duplicated) { $duplicated->prepareReference(); });
$bad->legacyReferences = array();
$bad->schema = false;
$duplicated->prepare();
refused('Missing legacy upgrade seed must fail', function () use ($duplicated) { $duplicated->prepareReference(); });
counterCheck('Missing legacy upgrade rolls back both counter rows', array(), $bad->rows);
$bad = new SubmissionMemory();
$bad->schema = false;
$bad->legacyReferences[0]['store_id'] = '1';
$overridden = fixtureSubmissionStore($bad);
$overridden->prepare();
refused('A store-specific legacy counter is ambiguous', function () use ($overridden) { $overridden->prepareReference(); });
$bad = new SubmissionMemory();
$uninitialized = fixtureSubmissionStore($bad);
refused('Cannot reseed an existing ledger with the new schema', function () use ($uninitialized) { $uninitialized->prepareReference(); });
counterCheck('Rejected reseed never writes reference rows', array(), $bad->rows);

$memory = fixtureInstalledLedger(\JetSubmissionStore::REFERENCE_MAX);
$service = fixtureSubmissionStore($memory);
counterCheck('Largest number uses decimal digits without PHP integer conversion', \JetSubmissionStore::REFERENCE_MAX, $service->reserveReference());
counterCheck('Beyond max is durable exhaustion', \JetSubmissionStore::REFERENCE_EXHAUSTED, $service->nextReference());
refused('Exhausted allocator fails rather than wrapping', function () use ($service) { $service->reserveReference(); });

$memory = fixtureInstalledLedger();
$service = fixtureSubmissionStore($memory);
$connection = new SubmissionSqlFixture($memory);
$connection->fault = function ($sql) { return strpos($sql, 'SET reference_next=reference_next+1') !== false; };
$faulty = fixtureSubmissionStore($memory, $connection);
refused('Failed write yields no reference', function () use ($faulty) { $faulty->reserveReference(); });
counterCheck('Failed write did not consume number', '42', $service->nextReference());
$connection->fault = null;
$connection->commitUnknown = true;
refused('Unacknowledged commit yields no reference', function () use ($faulty) { $faulty->reserveReference(); });
counterCheck('Committed but unacknowledged number is skipped', '43', $service->reserveReference());

function checkedPost($fixture, $cart, $channel) {
    $quote = submissionQuote($fixture, $cart, $channel, '10', '0');
    $prefix = $cart ? 'jet_' : $channel . '_';
    $post = array('quote'=>$quote['quote'], $prefix.'vnoski'=>'10', $prefix.'parva'=>'0', $prefix.'egn'=>'0000000000');
    return $cart ? $post + array_intersect_key(productSubmissionPost(),
        array_flip(array('jet_card','jet_lname','jet_name','jet_lastname','jet_phone','jet_email'))) : $post;
}
$GLOBALS['jet_fixture_accept_mail'] = true;
foreach (array(array(true, 'jet', 42), array(false, 'jet_card', 43)) as $case) {
    list($cart, $channel, $order) = $case;
    $fixture = new SubmissionFixture($channel);
    $fixture->session->data['order_id'] = $order;
    $post = checkedPost($fixture, $cart, $channel);
    $before = (int)fixtureSubmissionStore($fixture->ledger)->nextReference();
    counterCheck('First request completes', 'success', submitFixture($fixture, $cart, $channel, $post)['success']);
    counterCheck('Duplicate request blocked', 'already_processed', submitFixture($fixture, $cart, $channel, $post)['reason']);
    counterCheck('Duplicate did not consume counter', (string)($before + 1), fixtureSubmissionStore($fixture->ledger)->nextReference());
}

$fixture = new SubmissionFixture();
$fixture->session->data['order_id'] = 44;
$post = checkedPost($fixture, false, 'jet');
$fixture->connection = new SubmissionSqlFixture($fixture->ledger);
$fixture->connection->fault = function ($sql) { return strpos($sql, 'SET reference_next=reference_next+1') !== false; };
$GLOBALS['jet_fixture_effects'] = array();
counterCheck('Reference allocation failure after claim is uncertain', 'submission_uncertain',
    submitFixture($fixture, false, 'jet', $post)['reason']);
counterCheck('Allocation failure never calls mail', array(), $GLOBALS['jet_fixture_effects']);
$fixture->connection = null;
counterCheck('Same application stays blocked after counter failure', 'submission_uncertain', submitFixture($fixture, false, 'jet', $post)['reason']);

$fixture = new SubmissionFixture();
$fixture->ledger = fixtureInstalledLedger();
$GLOBALS['jet_fixture_accept_mail'] = false;
$post = checkedPost($fixture, true, 'jet');
counterCheck('Explicit failed transport stays retryable', 'transport_failed', submitFixture($fixture, true, 'jet', $post)['reason']);
counterCheck('Failed transport consumed the first number', '43', fixtureSubmissionStore($fixture->ledger)->nextReference());
$GLOBALS['jet_fixture_accept_mail'] = true;
counterCheck('Retry reserves another number and succeeds', 'success', submitFixture($fixture, true, 'jet', $post)['success']);
counterCheck('Retry consumes the next number separately', '44', fixtureSubmissionStore($fixture->ledger)->nextReference());

$fixture = new SubmissionFixture();
$fixture->ledger = fixtureInstalledLedger();
$fixture->failHistory = true;
$post = checkedPost($fixture, true, 'jet');
counterCheck('Accepted transport followed by history failure remains uncertain', 'submission_uncertain',
    submitFixture($fixture, true, 'jet', $post)['reason']);
counterCheck('Transport uncertainty keeps the consumed number', '43', fixtureSubmissionStore($fixture->ledger)->nextReference());
counterCheck('Uncertain replay blocked without consuming more', 'submission_uncertain',
    submitFixture($fixture, true, 'jet', $post)['reason']);
counterCheck('Uncertain replay did not reserve again', '43', fixtureSubmissionStore($fixture->ledger)->nextReference());

$fixture = new SubmissionFixture();
$fixture->ledger = new SubmissionMemory();
$GLOBALS['jet_fixture_effects'] = array();
counterCheck('Product fails closed before preparation', 'unsuccess',
    submitFixture($fixture, 'product', 'jet', productSubmissionPost())['success']);
counterCheck('Product failure before reserve has no side effects', array(), $GLOBALS['jet_fixture_effects']);
fixtureSubmissionStore($fixture->ledger)->prepareReference(true);
counterCheck('Product retains existing successful order flow', 'success',
    submitFixture($fixture, 'product', 'jet', productSubmissionPost())['success']);
counterCheck('Product side effect chronology preserved', array('counter','mail','order','history'), $GLOBALS['jet_fixture_effects']);
counterCheck('Product does not create submission identities', 2, count($fixture->ledger->rows));

printf("%d targeted bank counter checks passed (database doubles).\n", $count);
