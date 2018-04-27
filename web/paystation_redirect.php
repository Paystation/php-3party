<?php
// This page is where the user is redirected after making a payment. In this implementation, it is quite likely that this will never be seen since polling and postbacks will often give a quicker response.

require_once '../src/init.php';

// Warning: This is untrusted data. If you choose to use this remember to implement access controls, or users will be able look up any transaction they like.
$transactionId = isset($_GET['ti']) ? $_GET['ti'] : '';

// Check that transaction ID against our database to make sure it's valid.
$txn = $paystation->getTransaction($transactionId);

$transactionResult = 'Transaction is incomplete.';
if ($txn->hasError) {
	$transactionResult = $txn->errorMessage;
}
elseif (!$txn->errorCode) {
	$amount = number_format((float) $txn->amount / 100, 2, '.', '');
	$transactionResult = "A transaction of \$$amount was successfully completed at $txn->transactionTime.";
}
?>
<!doctype html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>3 Party Paystation iFrame Sample Code</title>
	<link rel="stylesheet" type="text/css" href="css/paystation.css?1">
</head>
<body>
<div class="paystation-fold"></div>
<h2 class="header">Checkout</h2>
<div class="content">
	<div class="box">
		<a href="/">back</a>
		<br>
		<br>
		<p><?= $transactionResult; ?></p>
	</div>
</div>
</body>
</html>