<?php

class JetSubmission {
	const TTL = 900;
	const MAX_QUOTES = 24;

	public static function remember($session, $scope, array $keys, $binding, $channel) {
		$quotes = isset($session->data['jet_quotes']) ? $session->data['jet_quotes'] : array();
		$now = time();
		foreach ($quotes as $reference => $quote) {
			if ($quote['expires'] <= $now) {
				unset($quotes[$reference]);
			} elseif ($quote['binding'] === $binding && isset($quote['keys']) && $quote['keys'] === $keys) {
				return $reference;
			}
		}
		$reference = self::randomId();
		if ($reference === null) return null;
		$quotes[$reference] = array('scope' => $scope, 'keys' => $keys, 'binding' => $binding,
			'channel' => $channel, 'expires' => $now + self::TTL);
		$session->data['jet_quotes'] = array_slice($quotes, -self::MAX_QUOTES, null, true);
		return $reference;
	}

	public static function lookup($session, $reference, $scope, $channel) {
		if (!is_string($reference) || !preg_match('/^[a-f0-9]{48}$/D', $reference)
			|| !isset($session->data['jet_quotes'][$reference])) {
			return null;
		}
		$quote = $session->data['jet_quotes'][$reference];
		return isset($quote['keys']) && $quote['expires'] > time() && $quote['scope'] === $scope && $quote['channel'] === $channel ? $quote : null;
	}

	public static function randomId() {
		if (function_exists('random_bytes')) {
			$bytes = random_bytes(24);
		} elseif (function_exists('openssl_random_pseudo_bytes')) {
			$bytes = openssl_random_pseudo_bytes(24, $strong);
			if (!$strong) return null;
		} else {
			return null;
		}
		return is_string($bytes) && strlen($bytes) === 24 ? bin2hex($bytes) : null;
	}
}

class JetSubmissionStore {
	const STATES = "enum('sending','accepted','complete','uncertain','failed')";
	const REFERENCE_MAX = '9223372036854775807';
	const REFERENCE_EXHAUSTED = '9223372036854775808';
	const CHANNELS = "enum('jet','jet_card')";
	private $db;
	private $table;
	private $settings;
	private $counterKey;
	private $seedKey;
	private $verified = false;
	private $createdTable = false;
	private $upgradingLegacySchema = false;
	private $poisoned = false;
	private $timeoutSet = false;

	public function __construct($db, $prefix) {
		if (!is_string($prefix) || !preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) {
			throw new \RuntimeException('Invalid JET table prefix.');
		}
		$this->db = $db;
		$this->table = '`' . $prefix . 'jet_submission`';
		$this->settings = '`' . $prefix . 'setting`';
		$this->counterKey = hash('sha256', 'jet-reference-counter:' . $prefix);
		$this->seedKey = hash('sha256', 'jet-reference-seeded:' . $prefix);
	}

	public static function connect() {
		// Own this connection. Order models and transport must not inherit our transaction.
		return new self(new \DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE,
			defined('DB_PORT') ? DB_PORT : null), DB_PREFIX);
	}

	private function tableStatus() {
		return $this->rows("SHOW TABLE STATUS WHERE Name = '" . trim($this->table, '`') . "'");
	}

	public function prepare($requireLegacy = false) {
		$this->verified = false;
		if (!$this->tableStatus()) {
			if ($requireLegacy && $this->legacyReference() === null) {
				throw new \RuntimeException('Missing legacy JET bank counter.');
			}
			$this->write("CREATE TABLE IF NOT EXISTS " . $this->table . " (
				identity_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				execution_id CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
				channel " . self::CHANNELS . " NOT NULL,
				state " . self::STATES . " NOT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				reference_next DECIMAL(19,0) UNSIGNED NULL,
				PRIMARY KEY (identity_key)
			) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin");
			$this->createdTable = true;
		}
		if (!$this->verifySchema(true)) {
			// Only the exact IMP-04B schema is eligible for this additive upgrade.
			if ($this->legacyReference() === null) throw new \RuntimeException('Missing legacy JET bank counter.');
			try {
				$this->write('ALTER TABLE ' . $this->table . ' ADD COLUMN reference_next DECIMAL(19,0) UNSIGNED NULL');
				$this->upgradingLegacySchema = true;
			} catch (\Exception $error) {
				$this->verifySchema(false); // Another admin request may have upgraded it.
			} catch (\Throwable $error) {
				$this->verifySchema(false);
			}
		}
		$this->verify();
	}

	public function drop() {
		$this->write('DROP TABLE IF EXISTS ' . $this->table);
		$this->verified = false;
		$this->createdTable = false;
		$this->upgradingLegacySchema = false;
	}

	public function verify() {
		if (!$this->verified) $this->verified = $this->verifySchema(false);
	}

	private function verifySchema($allowLegacy) {
		$rows = $this->tableStatus();
		if (count($rows) !== 1 || !isset($rows[0]['Engine']) || strcasecmp($rows[0]['Engine'], 'InnoDB') !== 0) {
			throw new \RuntimeException('JET submission table requires InnoDB.');
		}
		$expected = array('identity_key' => 'char(64)', 'execution_id' => 'char(48)',
			'channel' => self::CHANNELS, 'state' => self::STATES,
			'created_at' => 'datetime', 'updated_at' => 'datetime');
		$columns = $this->rows('SHOW FULL COLUMNS FROM ' . $this->table);
		$referenceColumn = false;
		foreach ($columns as $column) {
			$name = $column['Field'];
			if ($name === 'reference_next' && !$referenceColumn
				&& strtolower($column['Type']) === 'decimal(19,0) unsigned' && $column['Null'] === 'YES') {
				$referenceColumn = true;
				continue;
			}
			if (!isset($expected[$name]) || strtolower($column['Type']) !== $expected[$name] || $column['Null'] !== 'NO'
				|| (($name === 'identity_key' || $name === 'execution_id') && $column['Collation'] !== 'ascii_bin')) {
				throw new \RuntimeException('Incompatible JET submission columns.');
			}
			unset($expected[$name]);
		}
		$unique = array();
		foreach ($this->rows('SHOW INDEX FROM ' . $this->table) as $index) {
			if ((int)$index['Non_unique'] === 0) $unique[] = $index;
		}
		if ((!$allowLegacy && !$referenceColumn) || $expected || count($unique) !== 1 || $unique[0]['Key_name'] !== 'PRIMARY'
			|| $unique[0]['Column_name'] !== 'identity_key' || !empty($unique[0]['Sub_part'])
			|| (int)$unique[0]['Seq_in_index'] !== 1) {
			throw new \RuntimeException('Missing or incompatible JET submission identity constraint.');
		}
		return $referenceColumn;
	}

	// The legacy store-0 setting is a one-time seed. Runtime never reads cached config.
	public function legacyReference() {
		$rows = $this->rows("SELECT store_id, code, value, serialized FROM " . $this->settings .
			" WHERE `key`='mt_jet_credit_count'");
		if (!$rows) return null;
		if (count($rows) !== 1 || (string)$rows[0]['store_id'] !== '0' || $rows[0]['code'] !== 'mt_jet_credit'
			|| (string)$rows[0]['serialized'] !== '0') {
			throw new \RuntimeException('Ambiguous legacy JET bank counter.');
		}
		return self::referenceNumber($rows[0]['value'], self::REFERENCE_EXHAUSTED);
	}

	private static function compareReference($left, $right) {
		return strlen($left) === strlen($right) ? strcmp($left, $right) : strlen($left) - strlen($right);
	}

	private static function referenceNumber($value, $limit) {
		if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,18}$/D', (string)$value)
			|| self::compareReference((string)$value, $limit) > 0) {
			throw new \RuntimeException('Invalid JET bank reference.');
		}
		return (string)$value;
	}

	private function referenceRow($key, $lock) {
		$rows = $this->rows("SELECT execution_id, channel, state, reference_next FROM " . $this->table .
			" WHERE identity_key='" . $key . "'" . ($lock ? ' FOR UPDATE' : ''));
		return $rows ? $rows[0] : null;
	}

	private function checkReferenceRow($row) {
		if (!$row || $row['state'] !== 'complete' || $row['channel'] !== 'jet'
			|| $row['execution_id'] !== str_repeat('0', 48)) {
			throw new \RuntimeException('JET bank counter is not prepared.');
		}
	}

	private function readReference($lock) {
		$marker = $this->referenceRow($this->seedKey, $lock);
		$this->checkReferenceRow($marker);
		$floor = self::referenceNumber($marker['reference_next'], self::REFERENCE_EXHAUSTED);
		$counter = $this->referenceRow($this->counterKey, $lock);
		$this->checkReferenceRow($counter);
		$next = self::referenceNumber($counter['reference_next'], self::REFERENCE_EXHAUSTED);
		if ($floor !== $next) throw new \RuntimeException('JET bank counter history is inconsistent.');
		return $next;
	}

	// Initialize once per installation. The marker prevents a missing counter from being silently reseeded.
	public function prepareReference($freshInstall = false) {
		$this->verify();
		$this->transaction(function () use ($freshInstall) {
			// Concurrent preparation serializes on the marker without mutating an existing value.
			$this->insertReferenceRow($this->seedKey, 'NULL');
			$marker = $this->referenceRow($this->seedKey, true);
			$this->checkReferenceRow($marker);
			$counter = $this->referenceRow($this->counterKey, true);
			if ($marker['reference_next'] !== null) {
				$this->readReference(true);
				return;
			}
			if ($counter !== null) throw new \RuntimeException('JET bank counter history is incomplete.');
			if (!$this->upgradingLegacySchema && ((!$this->createdTable && !$freshInstall)
				|| $this->rows("SELECT identity_key FROM " . $this->table .
					" WHERE identity_key<>'" . $this->seedKey . "' LIMIT 1"))) {
				throw new \RuntimeException('JET bank counter is missing from an existing ledger.');
			}
			$seed = $freshInstall && !$this->upgradingLegacySchema ? '1' : $this->legacyReference();
			if ($seed === null) throw new \RuntimeException('Missing legacy JET bank counter.');
			$this->insertReferenceRow($this->counterKey, $seed);
			$this->writeReference($this->seedKey, $seed);
		});
		$this->createdTable = false;
		$this->upgradingLegacySchema = false;
	}

	private function insertReferenceRow($key, $value) {
		$this->write("INSERT INTO " . $this->table .
			" (identity_key,execution_id,channel,state,created_at,updated_at,reference_next) VALUES ('" .
			$key . "','" . str_repeat('0', 48) . "','jet','complete',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP," .
			$value . ") ON DUPLICATE KEY UPDATE identity_key=identity_key");
	}

	private function writeReference($key, $value) {
		$this->write("UPDATE " . $this->table . " SET reference_next=" . $value .
			",updated_at=CURRENT_TIMESTAMP WHERE identity_key='" . $key . "'");
	}

	public function nextReference() {
		$this->verify();
		return $this->transaction(function () { return $this->readReference(true); });
	}

	public function reserveReference() {
		$this->verify();
		return $this->transaction(function () {
			$next = $this->readReference(true);
			if ($next === self::REFERENCE_EXHAUSTED) throw new \RuntimeException('JET bank reference exhausted.');
			$this->writeReference($this->counterKey, 'reference_next+1');
			$this->writeReference($this->seedKey, 'reference_next+1');
			return $next;
		});
	}

	private function query($sql) {
		if ($this->poisoned) throw new \RuntimeException('JET submission connection unavailable.');
		return $this->db->query($sql);
	}

	private function write($sql) {
		if ($this->query($sql) !== true) throw new \RuntimeException('JET submission write was not acknowledged.');
	}

	private function rows($sql) {
		$result = $this->query($sql);
		if (!is_object($result) || !isset($result->rows) || !is_array($result->rows)) {
			throw new \RuntimeException('JET submission read failed.');
		}
		return $result->rows;
	}

	private function transaction($callback) {
		if (!$this->timeoutSet) {
			$this->write('SET SESSION innodb_lock_wait_timeout = 5');
			$this->timeoutSet = true;
		}
		try {
			$this->write('START TRANSACTION');
			$result = call_user_func($callback);
		} catch (\Exception $error) {
			$this->rollback();
			throw $error;
		} catch (\Throwable $error) {
			$this->rollback();
			throw $error;
		}
		// Treat any failed or lost COMMIT acknowledgement as unknown. Never execute mail.
		$this->poisoned = true;
		if ($this->db->query('COMMIT') !== true) throw new \RuntimeException('JET submission commit was not acknowledged.');
		$this->poisoned = false;
		return $result;
	}

	private function rollback() {
		try {
			$this->write('ROLLBACK');
		} catch (\Exception $error) {
			$this->poisoned = true;
		} catch (\Throwable $error) {
			$this->poisoned = true;
		}
	}

	private function keys(array $keys) {
		if (count($keys) < 1 || count($keys) > 2 || count(array_unique($keys)) !== count($keys)) {
			throw new \RuntimeException('Invalid JET submission identities.');
		}
		foreach ($keys as $key) {
			if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)
				|| $key === $this->counterKey || $key === $this->seedKey) {
				throw new \RuntimeException('Invalid JET submission identity.');
			}
		}
		sort($keys, SORT_STRING);
		return $keys;
	}

	private function read($key, $lock = false) {
		$rows = $this->rows("SELECT execution_id, channel, state FROM " . $this->table .
			" WHERE identity_key='" . $key . "'" . ($lock ? ' FOR UPDATE' : ''));
		return $rows ? $rows[0] : null;
	}

	private function reason($row) {
		if ($row === null) return null;
		if (!isset($row['execution_id'], $row['channel'], $row['state'])
			|| !preg_match('/^[a-f0-9]{48}$/D', $row['execution_id']) || !in_array($row['channel'], array('jet','jet_card'), true)) {
			return 'submission_uncertain';
		}
		if ($row['state'] === 'failed') return null;
		if ($row['state'] === 'complete') return 'already_processed';
		return 'submission_uncertain';
	}

	// A read can give a better replay response even after Cart clear. Only claim() authorizes work.
	public function blocked(array $keys) {
		$this->verify();
		foreach ($this->keys($keys) as $key) {
			$reason = $this->reason($this->read($key));
			if ($reason !== null) return $reason;
		}
		return null;
	}

	public function claim(array $keys, $channel) {
		$this->verify();
		$keys = $this->keys($keys);
		if (!in_array($channel, array('jet','jet_card'), true) || !($id = JetSubmission::randomId())) {
			throw new \RuntimeException('JET submission execution unavailable.');
		}
		try {
			$this->transaction(function () use ($keys, $channel, $id) {
				// One PK row per alias. All aliases enter sending in the same transaction.
				foreach ($keys as $key) {
					$this->write("INSERT INTO " . $this->table . " (identity_key,execution_id,channel,state,created_at,updated_at) VALUES " .
						"('" . $key . "','" . $id . "','" . $channel . "','sending',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE identity_key=identity_key");
					$row = $this->read($key, true);
					if ($row === null) throw new \RuntimeException('JET submission row missing after insert.');
					if ($row['execution_id'] === $id && $row['channel'] === $channel && $row['state'] === 'sending') continue;
					$reason = $this->reason($row);
					if ($reason !== null) throw new JetSubmissionBlocked($reason);
					$this->write("UPDATE " . $this->table . " SET execution_id='" . $id . "',channel='" . $channel .
						"',state='sending',updated_at=CURRENT_TIMESTAMP WHERE identity_key='" . $key . "'");
				}
			});
		} catch (JetSubmissionBlocked $error) {
			return array('reason' => $error->getMessage());
		}
		return array('lease' => new JetSubmissionLease($this, $keys, $id));
	}

	public function transition(array $keys, $execution, $from, $to) {
		$allowed = array('sending' => array('accepted','failed','uncertain'), 'accepted' => array('complete','uncertain'));
		if (!isset($allowed[$from]) || !in_array($to, $allowed[$from], true)
			|| !preg_match('/^[a-f0-9]{48}$/D', $execution)) {
			throw new \RuntimeException('Invalid JET submission transition.');
		}
		$keys = $this->keys($keys);
		$this->transaction(function () use ($keys, $execution, $from, $to) {
			foreach ($keys as $key) {
				$row = $this->read($key, true);
				if ($row === null || $row['execution_id'] !== $execution || $row['state'] !== $from) {
					throw new \RuntimeException('JET submission execution no longer owns all identities.');
				}
				$this->write("UPDATE " . $this->table . " SET state='" . $to .
					"',updated_at=CURRENT_TIMESTAMP WHERE identity_key='" . $key . "'");
			}
		});
	}
}

class JetSubmissionBlocked extends \RuntimeException {}

class JetSubmissionLease {
	private $store;
	private $keys;
	private $execution;
	private $state = 'sending';
	private $referenceAttempted = false;

	public function __construct(JetSubmissionStore $store, array $keys, $execution) {
		$this->store = $store;
		$this->keys = $keys;
		$this->execution = $execution;
	}

	public function reserveReference() {
		if (!$this->store || $this->state !== 'sending' || $this->referenceAttempted) {
			throw new \RuntimeException('JET submission lease is not ready.');
		}
		$this->referenceAttempted = true;
		return $this->store->reserveReference();
	}

	public function mark($state) {
		if (!$this->store) throw new \RuntimeException('JET submission lease is closed.');
		$this->store->transition($this->keys, $this->execution, $this->state, $state);
		$this->state = $state;
	}

	public function execute($send) {
		if (!$this->store || $this->state !== 'sending') {
			return array('success' => 'unsuccess', 'reason' => 'submission_unavailable');
		}
		try {
			$result = call_user_func($send);
		} catch (\Exception $error) {
			$result = $this->failedResult();
		} catch (\Throwable $error) {
			$result = $this->failedResult();
		}
		$this->store = null;
		return $result;
	}

	private function failedResult() {
		if ($this->state === 'sending' || $this->state === 'accepted') {
			try {
				$this->mark('uncertain');
			} catch (\Exception $ignored) {
			} catch (\Throwable $ignored) {
				// The last confirmed state already blocks another transport attempt.
			}
		}
		return array('success' => 'unsuccess', 'reason' => 'submission_uncertain');
	}
}
