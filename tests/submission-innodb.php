<?php

namespace JetFinancialBaseline;

require __DIR__ . '/submission-fixture.php';
require __DIR__ . '/submission-admin-fixture.php';

class IsolatedMariaDb {
    private $db;
    public $observer;
    public function __construct($socket, $database = '') {
        $this->db = new \mysqli('localhost', 'root', '', $database, 0, $socket);
    }
    public function escape($value) {
        return $this->db->real_escape_string($value);
    }
    public function query($sql) {
        if ($this->observer) call_user_func($this->observer, $sql, false);
        $result = $this->db->query($sql);
        if ($this->observer) call_user_func($this->observer, $sql, true);
        if ($result instanceof \mysqli_result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
            return (object)array('rows' => $rows, 'num_rows' => count($rows));
        }
        return $result;
    }
}

class RealLedgerAdminServices extends LedgerAdminServices {
    private $connection;

    public function __construct(IsolatedMariaDb $connection) {
        $this->connection = $connection;
    }
    public function query($sql) {
        return $this->connection->query($sql);
    }
    public function getSetting($code) {
        $settings = array();
        $rows = $this->query("SELECT * FROM fixture_setting WHERE store_id=0 AND code='" . $this->connection->escape($code) . "'")->rows;
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['serialized'] ? json_decode($row['value'], true) : $row['value'];
        }
        return $settings;
    }
    public function editSetting($code, $settings) {
        $this->deleteSetting($code);
        foreach ($settings as $key => $value) {
            if (strpos($key, $code) !== 0) continue;
            $serialized = is_array($value);
            $literal = $this->connection->escape($serialized ? json_encode($value) : (string)$value);
            $this->query("INSERT INTO fixture_setting (store_id,`key`,code,value,serialized) VALUES (0,'" .
                $this->connection->escape($key) . "','" . $this->connection->escape($code) . "','" . $literal . "'," . (int)$serialized . ")");
        }
    }
    public function deleteSetting($code) {
        $this->query("DELETE FROM fixture_setting WHERE store_id=0 AND code='" . $this->connection->escape($code) . "'");
    }
}

if (($argv[1] ?? '') === '--worker') {
    list(,, $socket, $schema, $role, $kind, $channel, $order, $storeId) = $argv;
    $connection = new IsolatedMariaDb($socket, $schema);
    $fixture = new SubmissionFixture($channel);
    $fixture->connection = $connection;
    $fixture->session->data['order_id'] = (int)$order;
    $fixture->order['store_id'] = (int)$storeId;
    $settings = array('config_store_id' => (int)$storeId);
    $cart = $kind === 'cart';
    $first = true;
    $reserving = false;
    $basket = hash('sha256', serialize(array(DB_PREFIX, (int)$storeId, 'basket-rows', array('101', '102'))));
    if ((int)$storeId === 3) {
        $orderKey = hash('sha256', serialize(array(DB_PREFIX, (int)$storeId, 'checkout', (int)$order)));
        $basket = min($basket, $orderKey);
    }
    $connection->observer = function ($sql, $after) use ($role, $basket, &$first, &$reserving) {
        $marker = hash('sha256', 'jet-reference-seeded:' . DB_PREFIX);
        $referenceLock = strpos($sql, "identity_key='" . $marker . "' FOR UPDATE") !== false;
        $installLock = $role === 'counter-install' && !$after
            && strpos($sql, 'INSERT INTO ') === 0 && strpos($sql, $marker) !== false;
        if ($referenceLock && $after) $reserving = true;
        if ($first && ($installLock || ($referenceLock && (($role === 'counter-hold' && $after)
            || ($role === 'counter-contend' && !$after))))) {
            $first = false;
            echo "counter\n";
            fflush(STDOUT);
            if ($role === 'counter-hold' && fgets(STDIN) !== "release\n") exit(2);
        }
        if ($after && $sql === 'COMMIT' && ($role === 'lost-commit' || ($role === 'counter-lost-commit' && $reserving))) {
            throw new \RuntimeException('Simulated loss of the commit acknowledgement.');
        }
        if ($first && strpos($sql, 'INSERT INTO ') === 0 && strpos($sql, $basket) !== false
            && (($role === 'hold' && $after) || ($role === 'contend' && !$after))) {
            $first = false;
            echo "basket\n";
            fflush(STDOUT);
            if ($role === 'hold' && fgets(STDIN) !== "release\n") exit(2);
        }
    };
    $GLOBALS['jet_fixture_accept_mail'] = true;
    $GLOBALS['jet_fixture_effects'] = array();
    if ($role === 'interrupt') {
        $GLOBALS['jet_fixture_concurrent'] = function () {
            echo "interrupt\n";
            fflush(STDOUT);
            fgets(STDIN);
            exit(2);
        };
    }
    if ($role === 'counter-install') {
        $services = new RealLedgerAdminServices($connection);
        adminFixture($services)->install();
        echo json_encode(array(array('success' => $services->redirected === null ? 'success' : 'unsuccess'),
            $GLOBALS['jet_fixture_effects'])) . "\n";
        exit;
    }
    unset($GLOBALS['jet_fixture_mail']);
    if ($kind === 'product') {
        $result = submitFixture($fixture, 'product', $channel, productSubmissionPost(), array(), $settings);
        echo json_encode(array($result, $GLOBALS['jet_fixture_effects'], isset($GLOBALS['jet_fixture_mail']) ? $GLOBALS['jet_fixture_mail'][1] : null)) . "\n";
        exit;
    }
    $quote = submissionQuote($fixture, $cart, $channel, '10', '0', array(), $settings);
    if (empty($quote['quote'])) throw new \RuntimeException('Worker could not obtain the fixture quote.');
    $prefix = $cart ? 'jet_' : $channel . '_';
    $post = array('quote' => $quote['quote'], $prefix . 'vnoski' => '10', $prefix . 'parva' => '0',
        $prefix . 'egn' => '0000000000');
    if ($cart) $post += array('jet_card' => $channel === 'jet' ? '0' : '1', 'jet_lname' => '',
        'jet_name' => 'Test', 'jet_lastname' => 'Buyer', 'jet_phone' => '0000000000', 'jet_email' => 'buyer@example.test');
    $result = submitFixture($fixture, $cart, $channel, $post, array(), $settings);
    echo json_encode(array($result, $GLOBALS['jet_fixture_effects'],
        isset($GLOBALS['jet_fixture_mail']) ? $GLOBALS['jet_fixture_mail'][1] : null)) . "\n";
    exit;
}

$socket = $argv[1] ?? '';
if ($socket === '' || filetype($socket) !== 'socket' || basename($socket) !== 'jet.sock') {
    fwrite(STDERR, "Supply the dedicated local test server's jet.sock. The test creates and drops its own randomly named schema.\n");
    exit(2);
}
$schema = 'jet_test_' . bin2hex(random_bytes(8));
$admin = new IsolatedMariaDb($socket);
$admin->query('CREATE DATABASE `' . $schema . '`');
register_shutdown_function(function () use ($admin, $schema) { $admin->query('DROP DATABASE `' . $schema . '`'); });
$db = new IsolatedMariaDb($socket, $schema);
$db->query("CREATE TABLE fixture_setting (store_id INT, `key` VARCHAR(64), code VARCHAR(32), value TEXT, serialized TINYINT) ENGINE=MyISAM");
$db->query("INSERT INTO fixture_setting VALUES (0,'mt_jet_credit_count','mt_jet_credit','42',0)");
$store = new \JetSubmissionStore($db, DB_PREFIX);
$store->prepare();
$store->prepareReference();

function realCheck($expected, $actual, $label) {
    if ($expected !== $actual) throw new \RuntimeException($label . ': ' . var_export($actual, true));
    $GLOBALS['dbChecks']++;
}
function launch($role, $kind, $channel, $order, $store) {
    global $socket, $schema;
    $args = array(PHP_BINARY, __FILE__, '--worker', $socket, $schema, $role, $kind, $channel, $order, $store);
    $process = proc_open('exec ' . implode(' ', array_map('escapeshellarg', $args)),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => STDERR), $pipes);
    stream_set_timeout($pipes[1], 12);
    return array($process, $pipes[0], $pipes[1]);
}
function finish($worker) {
    $result = json_decode(fgets($worker[2]), true);
    fclose($worker[1]);
    fclose($worker[2]);
    if (proc_close($worker[0]) !== 0 || !is_array($result)) throw new \RuntimeException('Worker failed.');
    return $result;
}
function race(array $first, array $second, $store) {
    $one = launch('hold', $first[0], $first[1], $first[2], $store);
    $two = null;
    try {
        realCheck("basket\n", fgets($one[2]), 'first transaction inserted shared key');
        $two = launch('contend', $second[0], $second[1], $second[2], $store);
        realCheck("basket\n", fgets($two[2]), 'second transaction attempted shared key');
        $read = array($two[2]); $write = $except = null;
        realCheck(0, stream_select($read, $write, $except, 0, 150000), 'contender blocked while first holds write lock');
        fwrite($one[1], "release\n");
        $winner = finish($one);
        $loser = finish($two);
        realCheck('success', $winner[0]['success'], 'winner completes');
        realCheck(1, count(array_keys($winner[1], 'mail', true)), 'only one intercepted transport in winner');
        realCheck('unsuccess', $loser[0]['success'], 'loser rejected');
        realCheck(array(), $loser[1], 'loser performs no counter, mail or order action');
    } finally {
        foreach (array($one, $two) as $running) {
            if ($running && is_resource($running[0])) {
                proc_terminate($running[0], 9);
                fclose($running[1]);
                fclose($running[2]);
                proc_close($running[0]);
            }
        }
    }
}

$GLOBALS['dbChecks'] = 0;
race(array('cart','jet',42), array('cart','jet',42), 1);
race(array('cart','jet',42), array('checkout','jet_card',43), 2);
race(array('checkout','jet',42), array('checkout','jet_card',42), 3);
race(array('checkout','jet',42), array('checkout','jet_card',43), 4);
$store->prepare();
$completed = finish(launch('normal', 'checkout', 'jet_card', 44, 4));
realCheck('already_processed', $completed[0]['reason'], 'new quote / new order blocked after successful claim and repeated preparation');
realCheck(array(), $completed[1], 'new completed replay has no effect');

$crash = launch('interrupt', 'checkout', 'jet', 42, 5);
try {
    realCheck("interrupt\n", fgets($crash[2]), 'transport stub reached after committed intent');
    proc_terminate($crash[0], 9);
} finally {
    fclose($crash[1]);
    fclose($crash[2]);
    proc_close($crash[0]);
}
$store->prepare();
$afterCrash = finish(launch('normal', 'checkout', 'jet_card', 43, 5));
realCheck('submission_uncertain', $afterCrash[0]['reason'], 'fresh process and order after crash cannot resend');
realCheck(array(), $afterCrash[1], 'uncertain result leaves no side effects');

$lostCommit = finish(launch('lost-commit', 'checkout', 'jet', 42, 6));
realCheck('submission_unavailable', $lostCommit[0]['reason'], 'unacknowledged commit does not authorize transport');
realCheck(array(), $lostCommit[1], 'no side effects after acknowledgement loss');
$afterLoss = finish(launch('normal', 'cart', 'jet_card', 42, 6));
realCheck('submission_uncertain', $afterLoss[0]['reason'], 'the actually committed intent still blocks a cross-path retry');
realCheck(array(), $afterLoss[1], 'no side effects for the cross-path retry');

$holder = launch('hold', 'cart', 'jet', 42, 7);
$waiter = null;
try {
    realCheck("basket\n", fgets($holder[2]), 'timeout holder reached the contested row');
    $waiter = launch('contend', 'checkout', 'jet_card', 43, 7);
    realCheck("basket\n", fgets($waiter[2]), 'timeout contender reached the contested row');
    $timeout = finish($waiter);
    realCheck('submission_unavailable', $timeout[0]['reason'], 'server lock timeout fails closed');
    realCheck(array(), $timeout[1], 'lock timeout causes no side effects');
    fwrite($holder[1], "release\n");
    realCheck('success', finish($holder)[0]['success'], 'the uncontested worker still completes');
} finally {
    foreach (array($holder, $waiter) as $running) {
        if ($running && is_resource($running[0])) {
            proc_terminate($running[0], 9);
            fclose($running[1]);
            fclose($running[2]);
            proc_close($running[0]);
        }
    }
}
printf("%d real InnoDB checks passed (%s, isolated Unix socket, separate PHP processes).\n", $GLOBALS['dbChecks'],
    $admin->query('SELECT VERSION() AS version')->rows[0]['version']);

$GLOBALS['dbChecks'] = 0;
function bankNumber(array $result) {
    $subject = isset($result[2]) ? $result[2] : '';
    if (!preg_match('/^JET-FIXTURE, онлайн заявка по поръчка ([1-9][0-9]*)$/uD', $subject, $match)) {
        throw new \RuntimeException('Reference format changed: ' . var_export($result, true));
    }
    return $match[1];
}
function counterRace(array $first, array $second, $storeId, $installing = false) {
    global $store;
    $initial = $store->nextReference();
    $one = launch('counter-hold', $first[0], $first[1], 42, $storeId);
    $two = null;
    try {
        realCheck("counter\n", fgets($one[2]), 'first worker holds common counter row');
        $two = launch($installing ? 'counter-install' : 'counter-contend', $second[0], $second[1], 42, $storeId + 1);
        realCheck("counter\n", fgets($two[2]), 'independent worker reaches common counter');
        $read = array($two[2]); $write = $except = null;
        realCheck(0, stream_select($read, $write, $except, 0, 150000), 'second worker waits on counter');
        fwrite($one[1], "release\n");
        $firstResult = finish($one);
        $secondResult = finish($two);
        realCheck($initial, bankNumber($firstResult), 'first application gets the next reference');
        if ($installing) {
            realCheck('success', $secondResult[0]['success'], 'concurrent repeat install validates the live counter');
            realCheck(array(), $secondResult[1], 'repeat install does not allocate or send');
        } else {
            realCheck((string)((int)$initial + 1), bankNumber($secondResult), 'second application gets a distinct reference');
            realCheck('success', $secondResult[0]['success'], 'distinct second application completes');
        }
        realCheck((string)((int)$initial + ($installing ? 1 : 2)), $store->nextReference(), 'next reference persisted');
    } finally {
        foreach (array($one, $two) as $running) {
            if ($running && is_resource($running[0])) {
                proc_terminate($running[0], 9);
                fclose($running[1]);
                fclose($running[2]);
                proc_close($running[0]);
            }
        }
    }
}

$nextStore = 10;
foreach (array(
    array(array('product','jet'), array('product','jet')),
    array(array('product','jet'), array('cart','jet')),
    array(array('product','jet'), array('checkout','jet')),
    array(array('product','jet'), array('checkout','jet_card')),
    array(array('cart','jet'), array('checkout','jet')),
    array(array('checkout','jet'), array('checkout','jet_card'))
) as $pair) {
    counterRace($pair[0], $pair[1], $nextStore);
    $nextStore += 2;
}
counterRace(array('product','jet'), array('product','jet'), $nextStore, true);

$many = array(); $numbers = array();
$initial = (int)$store->nextReference();
for ($i = 0; $i < 12; $i++) {
    $many[] = launch('normal', array('product', 'cart', 'checkout')[$i % 3], $i % 2 ? 'jet' : 'jet_card', 42, 100 + $i);
}
try {
    foreach ($many as $worker) $numbers[] = (int)bankNumber(finish($worker));
} finally {
    foreach ($many as $running) {
        if (is_resource($running[0])) {
            proc_terminate($running[0], 9);
            fclose($running[1]);
            fclose($running[2]);
            proc_close($running[0]);
        }
    }
}
sort($numbers, SORT_NUMERIC);
realCheck(range($initial, $initial + 11), $numbers, 'twelve distinct workers reserve unique numbers');
realCheck((string)($initial + 12), $store->nextReference(), 'counter reflects all twelve allocations');

$initial = (int)$store->nextReference();
$unknown = finish(launch('counter-lost-commit', 'checkout', 'jet', 42, 190));
realCheck('submission_uncertain', $unknown[0]['reason'], 'lost counter commit response is uncertain after a successful claim');
realCheck(null, $unknown[2], 'no mail after losing the counter commit response');
realCheck((string)($initial + 1), $store->nextReference(), 'unacknowledged committed reference remains consumed');
$replay = finish(launch('normal', 'cart', 'jet_card', 42, 190));
realCheck('submission_uncertain', $replay[0]['reason'], 'cross-path retry remains protected');
realCheck(array(), $replay[1], 'cross-path retry does not allocate a reference or apply side effects');
realCheck((string)($initial + 1), $store->nextReference(), 'protected retry leaves the counter untouched');

$holder = launch('counter-hold', 'product', 'jet', 42, 191);
$waiter = null;
try {
    realCheck("counter\n", fgets($holder[2]), 'timeout holder locked bank counter');
    $waiter = launch('counter-contend', 'product', 'jet', 42, 192);
    realCheck("counter\n", fgets($waiter[2]), 'timeout waiter reached bank counter');
    $timeout = finish($waiter);
    realCheck('unsuccess', $timeout[0]['success'], 'server lock timeout rejects reference reservation');
    realCheck(array(), $timeout[1], 'failed reservation causes no mail or order effects');
    fwrite($holder[1], "release\n");
    realCheck((string)($initial + 1), bankNumber(finish($holder)), 'holder receives the unambiguous reference');
    realCheck((string)($initial + 2), $store->nextReference(), 'lock timeout did not consume another reference');
} finally {
    foreach (array($holder, $waiter) as $running) {
        if ($running && is_resource($running[0])) {
            proc_terminate($running[0], 9);
            fclose($running[1]);
            fclose($running[2]);
            proc_close($running[0]);
        }
    }
}

$db->query('UPDATE fixture_jet_submission SET reference_next=' . \JetSubmissionStore::REFERENCE_MAX .
    ' WHERE reference_next IS NOT NULL');
realCheck(\JetSubmissionStore::REFERENCE_MAX, $store->reserveReference(), 'real DECIMAL allocates largest signed 64-bit reference');
realCheck(\JetSubmissionStore::REFERENCE_EXHAUSTED, $store->nextReference(), 'real DECIMAL durably marks exhaustion');
$exhausted = finish(launch('normal', 'product', 'jet', 42, 200));
realCheck('unsuccess', $exhausted[0]['success'], 'real exhaustion rejects Product');
realCheck(array(), $exhausted[1], 'real exhaustion performs no bank or order effect');
printf("%d additional real InnoDB bank counter checks passed.\n", $GLOBALS['dbChecks']);

$GLOBALS['dbChecks'] = 0;
$adminSchema = 'jet_test_admin_' . bin2hex(random_bytes(8));
$admin->query('CREATE DATABASE `' . $adminSchema . '`');
register_shutdown_function(function () use ($admin, $adminSchema) { $admin->query('DROP DATABASE `' . $adminSchema . '`'); });
$db = new IsolatedMariaDb($socket, $adminSchema);
$db->query("CREATE TABLE fixture_setting (store_id INT, `key` VARCHAR(64), code VARCHAR(32), value TEXT, serialized TINYINT) ENGINE=MyISAM");
$db->query('CREATE TABLE fixture_unrelated (id INT PRIMARY KEY) ENGINE=InnoDB');
$db->query('INSERT INTO fixture_unrelated VALUES (17)');
$services = new RealLedgerAdminServices($db);
$services->allowed[] = 'extension/module/mt_jet_credit';

adminFixture($services)->install();
$store = new \JetSubmissionStore($db, DB_PREFIX);
realCheck('1', $store->nextReference(), 'fresh native installation initializes the next number');
realCheck('1', $services->getSetting('mt_jet_credit')['mt_jet_credit_status'], 'fresh native settings persisted');
$keys = array(hash('sha256', 'admin-lifecycle'));
$lease = $store->claim($keys, 'jet')['lease'];
realCheck('1', $lease->reserveReference(), 'first reference allocated in the installed database');
$lease->mark('accepted');
$lease->mark('complete');
adminFixture($services, 'test-admin-token', array('mt_jet_credit_status'=>'1', 'mt_jet_credit_count'=>'1',
    'mt_jet_credit_gap'=>'0', 'mt_jet_credit_id'=>'BANK', 'mt_jet_credit_price'=>'150'))->index();
realCheck('2', $store->nextReference(), 'ordinary settings save has no effect on the counter');
realCheck('BANK', $services->getSetting('mt_jet_credit')['mt_jet_credit_id'], 'ordinary settings save preserves business workflow');
adminFixture($services)->install();
realCheck('2', $store->nextReference(), 'repeat install does not reset prepared state');
realCheck('already_processed', $store->blocked($keys), 'repeat install preserves durable claims');
adminFixture($services)->uninstall();
realCheck(0, $db->query("SHOW TABLE STATUS WHERE Name = 'fixture_jet_submission'")->num_rows, 'uninstall physically drops the ledger');
realCheck(0, $db->query("SHOW TABLE STATUS WHERE Name = 'fixture_jet_kop'")->num_rows, 'old promotion table cleanup still works');
realCheck('17', (string)$db->query('SELECT id FROM fixture_unrelated')->rows[0]['id'], 'unrelated data stays intact');
adminFixture($services)->install();
$store = new \JetSubmissionStore($db, DB_PREFIX);
realCheck('1', $store->reserveReference(), 'native reinstall allocates the first number of a new lifecycle');
realCheck(null, $store->blocked($keys), 'uninstall removed old application identities');

// Simulate a running IMP-04B installation before upgrading the module files.
$db->query("DELETE FROM fixture_jet_submission WHERE reference_next IS NOT NULL");
$db->query('ALTER TABLE fixture_jet_submission DROP COLUMN reference_next');
$db->query("INSERT INTO fixture_jet_submission (identity_key, execution_id, channel, state, created_at, updated_at) VALUES ('" .
    $keys[0] . "','" . str_repeat('a', 48) . "','jet','complete',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
$services->editSetting('mt_jet_credit', array('mt_jet_credit_count' => '118', 'mt_jet_credit_id' => 'BANK'));
adminFixture($services)->index();
$store = new \JetSubmissionStore($db, DB_PREFIX);
realCheck('118', $services->output['mt_jet_credit_count'], 'ordinary settings view migrates the known legacy seed');
realCheck('BANK', $services->getSetting('mt_jet_credit')['mt_jet_credit_id'], 'in-place upgrade preserves settings');
realCheck('already_processed', $store->blocked($keys), 'in-place upgrade preserves IMP-04B application rows');
adminFixture($services)->index();
realCheck('118', $store->nextReference(), 'repeated ordinary settings view preserves the next reference');

$db->query("DELETE FROM fixture_jet_submission WHERE reference_next IS NOT NULL");
adminFixture($services)->index();
realCheck(true, $services->output['error_warning'] !== '', 'corrupt initialized ledger gives the ordinary installation warning');
realCheck('0', (string)$db->query('SELECT COUNT(*) AS n FROM fixture_jet_submission WHERE reference_next IS NOT NULL')->rows[0]['n'],
    'a populated current ledger is not reseeded from the legacy setting');
$db->query('DROP TABLE fixture_jet_submission');
adminFixture($services)->index();
realCheck(true, $services->output['error_warning'] !== '', 'loss of a recorded ledger reports the standard warning');
realCheck(0, $db->query("SHOW TABLE STATUS WHERE Name = 'fixture_jet_submission'")->num_rows,
    'recorded installation does not fall back to the old legacy seed');
// Remove the installation record to test a separate, pre-ledger data state.
$services->editSetting('mt_jet_credit', array('mt_jet_credit_count' => '118', 'mt_jet_credit_id' => 'BANK'));
$db->query("INSERT INTO fixture_setting VALUES (1,'mt_jet_credit_count','mt_jet_credit','4',0)");
adminFixture($services)->index();
realCheck(true, $services->output['error_warning'] !== '', 'ambiguous upgrade counter reports an ordinary installation error');
realCheck(0, $db->query("SHOW TABLE STATUS WHERE Name = 'fixture_jet_submission'")->num_rows, 'no ledger is initialized from ambiguous legacy state');
$services->editSetting('mt_jet_credit', array('mt_jet_credit_id'=>'BANK'));
$db->query("DELETE FROM fixture_setting WHERE store_id=1 AND `key`='mt_jet_credit_count'");
adminFixture($services)->index();
realCheck(true, $services->output['error_warning'] !== '', 'existing installation with missing seed cannot guess a number');
printf("%d supplementary administration checks passed with a real InnoDB ledger.\n", $GLOBALS['dbChecks']);
