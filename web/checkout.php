<?php
require_once '../src/init.php';

$amount = isset($_REQUEST['amount']) ? $_REQUEST['amount'] * 100 : false;

if ($amount) {
	$transaction = $paystation->createTransaction($amount, 'sample_checkout_transaction'); // Replace 'sample_checkout_transaction' with your own merchant reference.
}
else {
	$transaction = new \Paystation\Transaction();
	$transaction->transactionId = -1;
	$transaction->hasError = true;
	$transaction->errorMessage = "No amount specified.";
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
				<a href="./">back</a>
				<br>
				<br>
				<div id="payment_wrapper" class="payment-wrapper">
					<?= $transaction->hasError ? "<h1 style=\"color:red\">$transaction->errorMessage</h1>" : "<iframe class=\"paystation-payment-frame\" src=\"$transaction[digitalOrderUrl]\"></iframe>" ?>
				</div>
			</div>
		</div>
<script src="js/paystation.js?1"></script>
<script>
	let _paymentFrameWrapper = document.getElementById('payment_wrapper');
	let _paymentFrame = _paymentFrameWrapper.firstElementChild;
	let _transactionId = '<?= $transaction->transactionId ?>';

	// make sure it isn't an error message
	if (_paymentFrame.nodeName === 'IFRAME' && _transactionId) {
		Paystation.pollTransactionDetails(_transactionId, onTransactionResponse);
	}

	// This function will get a response every time we poll the website.
	// Most of these responses will get transaction details for an incomplete transaction while the user is still entering their details in the iframe.
	function onTransactionResponse(err, transaction) {
		if (err) {
			// have some error handling if you want
		}

		// hasError is for all errors regardless if they come from paystation or us, which could happen before the transaction completes.
		// errorCode is a paystation response which is set after a transaction is complete. A negative error code means no error code has been returned.
		if (transaction && (transaction.errorCode > -1 || transaction.hasError)) {
			onTransactionComplete(transaction);
		}
	}

	// Remove the iframe and stop polling the transaction details. Show a response to the user.
	function onTransactionComplete(transaction) {
		Paystation.closePaymentFrame(_paymentFrame);
		Paystation.stopPolling();

		// Display the outcome to the user i.e. "Transaction successful" or "Insufficient funds"
		// You might want to handle these differently depending on the errorCode (transaction.errorCode)
		_paymentFrameWrapper.innerHTML = '<h1>' + transaction.errorMessage + '</h1>';
	}
</script>
</body>
</html>
