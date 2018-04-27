<?php
require_once __DIR__ . '/Paystation.php';
require_once __DIR__ . '/PaystationDBInterface.php';
require_once __DIR__ . '/PaystationTransaction.php';
require_once __DIR__ . '/SamplePaystationDB.php';

$config = [
	'paystation_id' => 'PAYSTATIONID', // enter your Paystation ID
	'gateway_id' => 'GATEWAYID', // enter your gateway ID
	'hmac_key' => 'YOUR_SECURITY_CODE', // each gateway has its own HMAC key, enter your one here
	'test_mode' => true // set to 'false' for production transactions
];

$db = new SamplePaystationDB();
$paystation = new \Paystation\Paystation($db, $config['paystation_id'], $config['hmac_key'], $config['gateway_id'], $config['test_mode']);
