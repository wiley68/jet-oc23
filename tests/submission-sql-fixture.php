<?php

namespace JetFinancialBaseline;

class SubmissionMemory {
    public $rows = array();
    public $schema = true;
    public $engine = 'InnoDB';
    public $primary = true;
    public $extraUnique = false;
    public $missingColumn = null;
    public $referenceColumn = true;
    public $legacyReferences = array(array('store_id' => '0', 'code' => 'mt_jet_credit', 'value' => '42', 'serialized' => '0'));
}

class SubmissionSqlFixture {
    private $memory;
    private $transaction = null;
    public $fault;
    public $commitUnknown = false;
    public $statements = array();

    public function __construct(SubmissionMemory $memory) { $this->memory = $memory; }

    public function query($sql) {
        $this->statements[] = $sql;
        if ($this->fault && call_user_func($this->fault, $sql)) {
            throw new \RuntimeException('Fixture database failure');
        }
        if ($sql === 'SET SESSION innodb_lock_wait_timeout = 5') return true;
        if ($sql === 'START TRANSACTION') {
            if ($this->transaction !== null) throw new \RuntimeException('Unexpected nested transaction.');
            $this->transaction = $this->memory->rows;
            return true;
        }
        if ($sql === 'COMMIT') {
            $this->memory->rows = $this->transaction;
            $this->transaction = null;
            return !$this->commitUnknown;
        }
        if ($sql === 'ROLLBACK') {
            $this->transaction = null;
            return true;
        }
        if (strpos($sql, 'CREATE TABLE IF NOT EXISTS ') === 0) {
            $this->memory->schema = true;
            $this->memory->referenceColumn = true;
            return true;
        }
        if ($sql === 'DROP TABLE IF EXISTS `fixture_jet_submission`') {
            $this->memory->schema = false;
            $this->memory->rows = array();
            return true;
        }
        if (strpos($sql, 'ALTER TABLE ') === 0 && strpos($sql, ' ADD COLUMN reference_next ') !== false) {
            if ($this->memory->referenceColumn) throw new \RuntimeException('Column already exists.');
            $this->memory->referenceColumn = true;
            return true;
        }
        if (strpos($sql, 'SELECT store_id, code, value, serialized FROM ') === 0) {
            return (object)array('rows' => $this->memory->legacyReferences);
        }
        if (strpos($sql, 'SHOW TABLE STATUS WHERE Name = ') === 0) {
            return (object)array('rows' => $this->memory->schema
                ? array(array('Engine' => $this->memory->engine)) : array());
        }
        if (!$this->memory->schema) throw new \RuntimeException('Fixture table missing.');
        if (strpos($sql, 'SHOW FULL COLUMNS FROM ') === 0) {
            $fields = array('identity_key' => 'char(64)', 'execution_id' => 'char(48)',
                'channel' => \JetSubmissionStore::CHANNELS, 'state' => \JetSubmissionStore::STATES,
                'created_at' => 'datetime', 'updated_at' => 'datetime');
            unset($fields[$this->memory->missingColumn]);
            $rows = array();
            foreach ($fields as $field => $type) {
                $rows[] = array('Field' => $field, 'Type' => $type, 'Null' => 'NO', 'Collation' => 'ascii_bin');
            }
            if ($this->memory->referenceColumn) {
                $rows[] = array('Field'=>'reference_next', 'Type'=>'decimal(19,0) unsigned', 'Null'=>'YES');
            }
            return (object)array('rows' => $rows);
        }
        if (strpos($sql, 'SHOW INDEX FROM ') === 0) {
            $rows = $this->memory->primary ? array(array('Key_name' => 'PRIMARY', 'Column_name' => 'identity_key',
                'Seq_in_index' => 1, 'Non_unique' => 0, 'Sub_part' => null)) : array();
            if ($this->memory->extraUnique) $rows[] = array('Key_name' => 'unexpected', 'Column_name' => 'channel', 'Non_unique' => 0);
            return (object)array('rows' => $rows);
        }
        if (preg_match("~^SELECT execution_id, channel, state(?:, reference_next)? FROM [^ ]+ WHERE identity_key='([a-f0-9]{64})'( FOR UPDATE)?$~D", $sql, $match)) {
            if (isset($match[2]) && $this->transaction === null) throw new \RuntimeException('Locking read outside transaction');
            $rows = $this->transaction === null ? $this->memory->rows : $this->transaction;
            return (object)array('rows' => isset($rows[$match[1]]) ? array($rows[$match[1]]) : array());
        }
        if (preg_match("~^SELECT identity_key FROM [^ ]+ WHERE identity_key<>'([a-f0-9]{64})' LIMIT 1$~D", $sql, $match)) {
            return (object)array('rows' => array_diff_key($this->transaction, array($match[1] => true)));
        }
        if ($this->transaction === null) throw new \RuntimeException('Fixture mutation outside transaction');
        if (preg_match("~^INSERT INTO [^ ]+ \\(identity_key,execution_id,channel,state,created_at,updated_at\\) VALUES \\('([a-f0-9]{64})','([a-f0-9]{48})','(jet|jet_card)','sending',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP\\) ON DUPLICATE KEY UPDATE identity_key=identity_key$~D", $sql, $match)) {
            if (!isset($this->transaction[$match[1]])) {
                $this->transaction[$match[1]] = array('execution_id' => $match[2], 'channel' => $match[3], 'state' => 'sending');
            }
            return true;
        }
        if (preg_match("~^INSERT INTO [^ ]+ \(identity_key,execution_id,channel,state,created_at,updated_at,reference_next\) VALUES \('([a-f0-9]{64})','([a-f0-9]{48})','jet','complete',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,(NULL|[1-9][0-9]*)\) ON DUPLICATE KEY UPDATE identity_key=identity_key$~D", $sql, $match)) {
            if (!isset($this->transaction[$match[1]])) {
                $this->transaction[$match[1]] = array('execution_id' => $match[2], 'channel' => 'jet',
                    'state' => 'complete', 'reference_next' => $match[3] === 'NULL' ? null : $match[3]);
            }
            return true;
        }
        if (preg_match("~^UPDATE [^ ]+ SET reference_next=(reference_next\+1|[1-9][0-9]*),updated_at=CURRENT_TIMESTAMP WHERE identity_key='([a-f0-9]{64})'$~D", $sql, $match)) {
            $next = $match[1];
            if ($next === 'reference_next+1') {
                $previous = $this->transaction[$match[2]]['reference_next'];
                $next = $previous === \JetSubmissionStore::REFERENCE_MAX
                    ? \JetSubmissionStore::REFERENCE_EXHAUSTED : (string)((int)$previous + 1);
                if (isset($GLOBALS['jet_fixture_effects']) && $match[2] === hash('sha256','jet-reference-counter:' . DB_PREFIX)) {
                    $GLOBALS['jet_fixture_effects'][] = 'counter';
                }
            }
            $this->transaction[$match[2]]['reference_next'] = $next;
            return true;
        }
        if (preg_match("~^UPDATE [^ ]+ SET (?:execution_id='([a-f0-9]{48})',channel='(jet|jet_card)',)?state='([a-z]+)',updated_at=CURRENT_TIMESTAMP WHERE identity_key='([a-f0-9]{64})'$~D", $sql, $match)) {
            if (!isset($this->transaction[$match[4]])) throw new \RuntimeException('Fixture update target missing.');
            $this->transaction[$match[4]]['state'] = $match[3];
            if ($match[1] !== '') {
                $this->transaction[$match[4]]['execution_id'] = $match[1];
                $this->transaction[$match[4]]['channel'] = $match[2];
            }
            return true;
        }
        throw new \RuntimeException('Unexpected fixture SQL: ' . $sql);
    }
}

function fixtureSubmissionStore($memory, $connection = null) {
    return new \JetSubmissionStore($connection ?: new SubmissionSqlFixture($memory), DB_PREFIX);
}

function fixtureInstalledLedger($nextReference = '42') {
    $memory = new SubmissionMemory();
    $memory->schema = false;
    $memory->legacyReferences[0]['value'] = $nextReference;
    $store = fixtureSubmissionStore($memory);
    $store->prepare();
    $store->prepareReference();
    return $memory;
}
