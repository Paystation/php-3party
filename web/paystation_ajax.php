<?php
// This provides a JSON API for the client so that the payment can be done without having to redirect away from any page.
require_once __DIR__ . '/../src/init.php';

header('content-type: application/json');

$method = isset($_REQUEST['method']) && $_REQUEST['method'] ? $_REQUEST['method'] : false;
$jsonResponse = [
	'hasError' => true,
	'errorCode' => -1,
	'errorMessage' => 'Invalid method.',
	'method' => $method,
	'request_method' => $_REQUEST['method'],
	'timeout' => null
];

// This method is only used if you want to open up a payment frame without refreshing the page.
// If your frame will be loaded on a new page, see checkout.php as an example of that.
// example query: ?method=make_transaction&amount=10.50
if ($method == 'make_transaction') {
	$amount = $_REQUEST['amount'] * 100;

	$jsonResponse = $paystation->createTransaction($amount, 'sample_pay_here_transaction'); // Replace 'sample_pay_here_transaction' with your own merchant reference.
}
// This is the endpoint used by the browser for polling a transaction's status/errorCode.
// example: ?method=get_transaction&transaction_id=12345
elseif ($method == 'get_transaction') {
	// Remember to implement some access controls so users cannot access each other's information.
	$txn = $paystation->getTransaction($_REQUEST['transaction_id']);

	$jsonResponse = [
		'transactionId' => $txn->transactionId,
		'hasError' => $txn->hasError, // This is for all errors regardless if they come from paystation or us, which could happen before the transaction completes.
		'errorCode' => $txn->errorCode, // This is a paystation response which is set after a transaction is complete. A negative error code means no error code has been returned and they should continue polling this transaction.
		'errorMessage' => $txn->errorMessage,
		'timeout' => $txn->timeout // Whether or not the user will be seeing the timeout error on the payment page.
	];

	if (!$txn->hasError) {
		$jsonResponse['amount'] = $txn->amount / 100;
		$jsonResponse['transactionTime'] = $txn->transactionTime;
		$jsonResponse['merchantSession'] = $txn->merchantSession;
		$jsonResponse['merchantReference'] = $txn->merchantReference;
	}
}

die(json_encode($jsonResponse));
