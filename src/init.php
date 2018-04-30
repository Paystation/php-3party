<?php
require_once __DIR__ . '/API.php';
require_once __DIR__ . '/TransactionDBInterface.php';
require_once __DIR__ . '/Transaction.php';
require_once __DIR__ . '/SampleTransactionDB.php';

$configFilePath = __DIR__ . '/../config.json';
if (!file_exists($configFilePath)) {
	die('Missing config.json');
}

$config = json_decode(file_get_contents($configFilePath), true);

if (!isset($config['paystation_id'], $config['hmac_key'], $config['gateway_id'], $config['test_mode'])) {
	die('config.json is missing credentials.');
}

$paystation = new \Paystation\API(new SampleTransactionDB(), $config['paystation_id'], $config['hmac_key'], $config['gateway_id'], $config['test_mode']);

if (isset($config['api_url'])) {
	$paystation->setApiUrl($config['api_url']);
}

if (isset($config['lookup_url'])) {
	$paystation->setLookupUrl($config['lookup_url']);
}
