<?php

namespace JetFinancialBaseline;

// Exercise the stock administration hooks with controlled OpenCart dependencies.
class_alias(__NAMESPACE__ . '\\Services', 'Controller');
require dirname(__DIR__) . '/upload/admin/controller/extension/module/mt_jet_credit.php';
define('HTTP_CATALOG', 'http://example.test/');

class LedgerAdminServices extends SubmissionSqlFixture {
    private $ledger;
    public $settings = array();
    public $promotions = false;

    public function __construct(SubmissionMemory $memory) {
        parent::__construct($memory);
        $this->ledger = $memory;
    }
    public $allowed = array('extension/extension/module');
    public $output;
    public $redirected;
    public $headers = array();
    public function query($sql) {
        if (strpos($sql, 'INSERT INTO `fixture_setting` (store_id,code,`key`,value,serialized)') === 0) {
            $this->settings[\ControllerExtensionModuleMtJetCredit::SUBMISSION_INSTALLED] = '1';
            return true;
        }
        if ($sql === "SHOW TABLE STATUS WHERE Name = 'fixture_jet_kop'") {
            return (object)array('rows' => $this->promotions ? array(array('Engine' => 'InnoDB')) : array());
        }
        if (preg_match('/^(CREATE TABLE IF NOT EXISTS|DROP TABLE IF EXISTS) `fixture_jet_kop`/', $sql, $matches)) {
            $this->statements[] = $sql;
            $this->promotions = $matches[1] === 'CREATE TABLE IF NOT EXISTS';
            return true;
        }
        return parent::query($sql);
    }
    public function model($route) {}
    public function controller($route) { return ''; }
    public function language($route) {}
    public function setTitle($title) {}
    public function get($key) { return null; }
    public function getAllKops() { return array(); }
    public function hasPermission($type, $route) { return in_array($route, $this->allowed, true); }
    public function getSetting($key) { return $this->settings; }
    public function editSetting($key, $settings) {
        $this->deleteSetting($key);
        $this->settings = $settings;
        if (isset($settings['mt_jet_credit_count'])) {
            $this->ledger->legacyReferences[] = array('store_id' => '0', 'code' => $key,
                'value' => (string)$settings['mt_jet_credit_count'], 'serialized' => '0');
        }
    }
    public function deleteSetting($key) {
        $this->settings = array();
        $this->ledger->legacyReferences = array_values(array_filter($this->ledger->legacyReferences, function ($row) use ($key) {
            return $row['store_id'] !== '0' || $row['code'] !== $key;
        }));
    }
    public function delete($key) {}
    public function link($route, $query, $secure) { return $route . '?' . $query; }
    public function addHeader($header) { $this->headers[] = $header; }
    public function redirect($url) { $this->redirected = $url; }
    public function view($name, $data) { return $data; }
    public function setOutput($output) { $this->output = $output; }
}

function adminFixture($env, $token = 'test-admin-token', $posted = null, $session = null) {
    $services = array_fill_keys(array('db','load','language','document','user','config','url','response',
        'model_extension_module_jetcredit','model_setting_setting','cache'), $env);
    $services['request'] = (object)array('server' => array('REQUEST_METHOD' => $posted !== null ? 'POST' : 'GET'),
        'get' => array('token' => $token), 'post' => $posted ?: array());
    $services['session'] = $session ?: (object)array('data' => array('token' => 'test-admin-token'));
    return new \ControllerExtensionModuleMtJetCredit($services);
}
