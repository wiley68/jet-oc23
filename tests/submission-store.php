<?php

namespace JetFinancialBaseline;

require __DIR__ . '/submission-fixture.php';

$count = 0;
function checkStore($label, $expected, $actual) {
    if ($expected !== $actual) throw new \RuntimeException($label . ': ' . var_export($actual, true));
    $GLOBALS['count']++;
}
function rejectedStore($label, $fn) {
    try {
        call_user_func($fn);
    } catch (\RuntimeException $error) {
        $GLOBALS['count']++;
        return;
    }
    throw new \RuntimeException($label . ' succeeded unexpectedly');
}

$basket = hash('sha256', 'a');
$order = hash('sha256', 'b');
$keys = array($basket, $order);
sort($keys);
$memory = new SubmissionMemory();
$memory->schema = false;
$store = fixtureSubmissionStore($memory);
rejectedStore('Missing table', function () use ($store, $keys) { $store->claim($keys, 'jet'); });
$store->prepare();
checkStore('Fresh preparation creates schema', true, $memory->schema);
checkStore('Preparation does not create application records', array(), $memory->rows);

foreach (array('engine' => 'MyISAM', 'primary' => false, 'missingColumn' => 'channel', 'extraUnique' => true) as $field => $value) {
    $bad = new SubmissionMemory();
    $bad->$field = $value;
    $bad->rows[$basket] = array('state' => 'complete', 'channel' => 'jet', 'execution_id' => str_repeat('a', 48));
    $existing = $bad->rows;
    $broken = fixtureSubmissionStore($bad);
    rejectedStore($field, function () use ($broken) { $broken->prepare(); });
    rejectedStore($field . ' closed at runtime', function () use ($broken, $keys) { $broken->claim($keys, 'jet'); });
    checkStore($field . ' not erased or repaired', $existing, $bad->rows);
}

$connection = new SubmissionSqlFixture($memory);
$connection->fault = function ($sql) { return strpos($sql, 'CREATE ') === 0; };
$memory->schema = false;
rejectedStore('CREATE permissions denied', function () use ($memory, $connection) {
    fixtureSubmissionStore($memory, $connection)->prepare();
});
$memory->schema = true;
fixtureSubmissionStore($memory, $connection)->prepare();
checkStore('Compatible existing table requires no CREATE privilege', true, $memory->schema);
foreach (array('transaction start' => 'START TRANSACTION', 'lock timeout' => ' FOR UPDATE',
    'deadlock after first row' => "identity_key='" . $keys[1] . "' FOR UPDATE") as $case => $needle) {
    $connection = new SubmissionSqlFixture($memory);
    $connection->fault = function ($sql) use ($needle) { return strpos($sql, $needle) !== false; };
    rejectedStore($case, function () use ($memory, $connection, $keys) { fixtureSubmissionStore($memory, $connection)->claim($keys, 'jet'); });
    checkStore($case . ' leaves no committed rows', array(), $memory->rows);
    checkStore($case . ' rolls back', true, in_array('ROLLBACK', $connection->statements, true));
}

$connection = new SubmissionSqlFixture($memory);
$connection->commitUnknown = true;
$unknown = fixtureSubmissionStore($memory, $connection);
rejectedStore('Lost COMMIT acknowledgement yields no lease', function () use ($unknown, $keys) { $unknown->claim($keys, 'jet'); });
checkStore('Both rows can have committed despite lost acknowledgement', array('sending', 'sending'),
    array_column(array_values($memory->rows), 'state'));
rejectedStore('Do not use the ambiguous connection', function () use ($unknown, $keys) { $unknown->blocked($keys); });
checkStore('Fresh connection blocks unknown committed result', 'submission_uncertain', fixtureSubmissionStore($memory)->blocked($keys));

$memory = new SubmissionMemory();
$store = fixtureSubmissionStore($memory);
$lease = $store->claim(array($keys[1]), 'jet')['lease'];
$lease->mark('accepted');
$lease->mark('complete');
checkStore('Duplicate key blocks all aliases', 'already_processed', $store->claim($keys, 'jet_card')['reason']);
checkStore('Claim did not leave a stray alias', array($keys[1]), array_keys($memory->rows));
$existing = $memory->rows;
$store->prepare();
checkStore('Existing completed rows and identity are preserved', $existing, $memory->rows);
$lease = $store->claim(array($keys[0]), 'jet')['lease'];
rejectedStore('Cannot skip directly from sending to complete', function () use ($lease) { $lease->mark('complete'); });
$lease->mark('failed');
$lease = $store->claim(array($keys[0]), 'jet_card')['lease'];
checkStore('Explicit failure permits fresh execution and channel', 'jet_card', $memory->rows[$keys[0]]['channel']);
$lease->mark('accepted');
rejectedStore('Accepted cannot transition to retryable failure', function () use ($lease) { $lease->mark('failed'); });
$lease->mark('uncertain');
checkStore('Uncertain persists', 'submission_uncertain', $store->blocked(array($keys[0])));

$memory = new SubmissionMemory();
$connection = new SubmissionSqlFixture($memory);
$lease = fixtureSubmissionStore($memory, $connection)->claim($keys, 'jet')['lease'];
$connection->fault = function ($sql) use ($keys) {
    return strpos($sql, "SET state='accepted',") !== false && strpos($sql, $keys[1]) !== false;
};
$result = $lease->execute(function () use ($lease) { $lease->mark('accepted'); });
checkStore('Failed state update after transport is uncertain', 'submission_uncertain', $result['reason']);
checkStore('State update rollbacks never leave a failed alias safe to retry', array('uncertain', 'uncertain'),
    array_column(array_values($memory->rows), 'state'));

$memory = new SubmissionMemory();
$store = fixtureSubmissionStore($memory);
$olderBackup = $memory->rows;
$lease = $store->claim($keys, 'jet')['lease'];
$lease->mark('accepted');
$lease->mark('complete');
$memory->rows = $olderBackup;
checkStore('Restoring a backup before the claim can remove the duplicate guard', null,
    fixtureSubmissionStore($memory)->blocked($keys));

require __DIR__ . '/submission-admin-fixture.php';

$controller_path = 'upload/admin/controller/extension/module/mt_jet_credit.php';
$template_path = 'upload/admin/view/template/extension/module/mt_jet_credit.tpl';
$baseline = '4bbc8fa70da7b029f3ff4cac9bf785174b73fe2c';
$root = dirname(__DIR__);
$template = file_get_contents($root . '/' . $template_path);
$controller = file_get_contents($root . '/' . $controller_path);
$original = shell_exec('git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg($baseline . ':' . $template_path));
$ten_month_option = "\t\t\t\t\t\t\t\t\t\t" .
    '<option value=10 <?php if ($mt_jet_credit_vnoski == 10) { echo \'selected\'; } ?>>10 месеца</option>' . "\n";
$original_layout = str_replace($ten_month_option, '', $template, $added_options);
checkStore('Only the approved ten-month option differs from the original administration template',
    array($original, 1), array($original_layout, $added_options));
foreach (array('Проверен начален номер при обновяване', 'Подгответе хранилището за заявки',
    'Следващ банков номер', 'Обновете номера') as $text) {
    checkStore('Rejected control is absent', false, strpos($template, $text));
}
foreach (array('prepare_jet_submission', 'advance_jet_reference', 'jet_initial_reference', 'jet_next_reference') as $field) {
    checkStore('Rejected controller action or input is absent', false, strpos($controller, $field));
}

$memory = new SubmissionMemory();
$memory->schema = false;
$memory->legacyReferences = array();
$admin = new LedgerAdminServices($memory);
adminFixture($admin, 'wrong')->install();
checkStore('Native install invalid token', array('HTTP/1.1 403 Forbidden'), $admin->headers);
adminFixture($admin)->install();
$counter = fixtureSubmissionStore($memory);
checkStore('Fresh core install creates InnoDB without in-memory module permissions', true, $memory->schema);
checkStore('Normal initial settings assigned', 1, $admin->settings['mt_jet_credit_status']);
checkStore('Fresh installation starts at the original initial number', '1', $counter->nextReference());
checkStore('Fresh installation can allocate atomically', '1', $counter->reserveReference());
$memory->rows += array($basket => array('state' => 'complete'), $order => array('state' => 'uncertain'));
$existing = $memory->rows;
adminFixture($admin)->install();
checkStore('Repeated native install preserves all ledger rows', $existing, $memory->rows);
checkStore('Repeated native install preserves next reference', '2', $counter->nextReference());
adminFixture($admin, 'wrong')->uninstall();
checkStore('Uninstall invalid token cannot drop the ledger', $existing, $memory->rows);
adminFixture($admin)->uninstall();
checkStore('Uninstall removes service table and all records', array(false, array()), array($memory->schema, $memory->rows));
checkStore('Uninstall only drops module-owned tables', array(
    'DROP TABLE IF EXISTS '.chr(96).'fixture_jet_submission'.chr(96),
    'DROP TABLE IF EXISTS '.chr(96).'fixture_jet_kop'.chr(96).';'),
    array_values(preg_grep('/^DROP TABLE/', $admin->statements)));
checkStore('Original promotion and settings cleanup remains', array(false, array()), array($admin->promotions, $admin->settings));
adminFixture($admin)->install();
checkStore('Fresh reinstall restarts a new reference lifecycle', '1', fixtureSubmissionStore($memory)->reserveReference());
checkStore('Previous identities have been removed', null, fixtureSubmissionStore($memory)->blocked(array($basket)));

$old = new SubmissionMemory();
$old->schema = false;
$admin = new LedgerAdminServices($old);
$admin->settings = array('mt_jet_credit_id' => 'BANK');
adminFixture($admin)->index();
checkStore('Viewing settings without modify permission cannot migrate', false, $old->schema);
$admin->allowed[] = 'extension/module/mt_jet_credit';
adminFixture($admin, 'wrong')->index();
checkStore('Automatic upgrade still requires the standard token', false, $old->schema);
adminFixture($admin)->index();
checkStore('Ordinary settings visit upgrades the installed legacy module', '42', $admin->output['mt_jet_credit_count']);
checkStore('Upgrade preserves business settings', 'BANK', $admin->settings['mt_jet_credit_id']);
$old->missingColumn = 'state';
adminFixture($admin)->index();
checkStore('Partial schema is reported through the original error banner', true, $admin->output['error_warning'] !== '');
checkStore('Failed upgrade does not replace the ledger', 2, count($old->rows));
$session = (object)array('data' => array('token' => 'test-admin-token'));
adminFixture($admin, 'test-admin-token', null, $session)->install();
checkStore('Native install failure redirects to the ordinary error banner', true, $admin->redirected !== null);
adminFixture($admin, 'test-admin-token', null, $session)->index();
checkStore('Native install provides the standard warning', true, $admin->output['error_warning'] !== '');
$old->schema = false;
$old->rows = array();
adminFixture($admin)->index();
checkStore('A registered installation does not recreate a lost ledger from the legacy setting', false, $old->schema);

printf("%d targeted store and installation checks passed (database doubles).\n", $count);

$count = 0;
$old = new SubmissionMemory();
$old->schema = false;
$admin = new LedgerAdminServices($old);
$admin->settings = array('mt_jet_credit_id' => 'Initial', 'mt_jet_credit_count' => '42');
$admin->allowed[] = 'extension/module/mt_jet_credit';
adminFixture($admin)->install();
$counter = fixtureSubmissionStore($old);
$counter->reserveReference();
$posted = array('mt_jet_credit_status'=>'1', 'mt_jet_credit_count'=>'1', 'mt_jet_credit_gap'=>'0',
    'mt_jet_credit_id'=>'BANK', 'mt_jet_credit_price'=>'150');
adminFixture($admin, 'test-admin-token', $posted)->index();
checkStore('Ordinary admin save ignores forged/stale counter', '43', $counter->nextReference());
checkStore('Ordinary admin save keeps unrelated posted settings', 'BANK', $admin->settings['mt_jet_credit_id']);
checkStore('Ordinary admin save preserves original legacy seed', '42', $admin->settings['mt_jet_credit_count']);
adminFixture($admin)->index();
checkStore('Original read-only field renders the next reference', '43', $admin->output['mt_jet_credit_count']);
$settings = $admin->settings;
adminFixture($admin, 'test-admin-token', array('prepare_jet_submission'=>'1', 'jet_initial_reference'=>'99'))->index();
checkStore('Old prepare form does not modify settings', $settings, $admin->settings);
adminFixture($admin, 'test-admin-token', array('advance_jet_reference'=>'1','jet_next_reference'=>'99'))->index();
checkStore('Old advance form does not allocate a reference', '43', $counter->nextReference());
checkStore('Old advance form does not modify settings', $settings, $admin->settings);
$old->legacyReferences = array();
adminFixture($admin)->install();
checkStore('A repeat install keeps the verified counter despite missing historical setting', '43', $counter->nextReference());
printf("%d additional admin bank counter checks passed (database doubles).\n", $count);
