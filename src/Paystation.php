<?php

namespace Paystation;

use SimpleXMLElement;

class Paystation {
	private $lookupURL = 'https://payments.paystation.co.nz/lookup/';
	private $paymentURL = 'https://www.paystation.co.nz/direct/paystation.dll';

	/** @var PaystationDBInterface $db */
	private $db;
	private $paystationId;
	private $hmacKey;
	private $gatewayId;
	private $testMode;

	public function __construct(PaystationDBInterface $db, $paystationId = '', $hmacKey = '', $gatewayId = '', $testMode = false) {
		$this->db = $db;
		$this->paystationId = $paystationId;
		$this->hmacKey = $hmacKey;
		$this->gatewayId = $gatewayId;
		$this->testMode = $testMode;
	}

	public function setLookupUrl($url) {
		$this->lookupURL = $url;
	}

	public function setPaymentUrl($url) {
		$this->paymentURL = $url;
	}

	/**
	 * @param bool $testMode
	 */
	public function setTestMode($testMode) {
		$this->testMode = $testMode;
	}

	private function post($url, $content) {
		$content = is_array($content) ? http_build_query($content) : $content;
		$time = time();
		$url .= '?' . http_build_query(['pstn_HMACTimestamp' => $time, 'pstn_HMAC' => hash_hmac('sha512', "{$time}paystation$content", $this->hmacKey)]);

		$options = [
			'http' => [
				'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
				'method' => 'POST'
			]
		];

		if ($content) {
			$options['http']['content'] = $content;
			$options['http']['header'] .= "Content-Length: " . strlen($content) . "\r\n";
		}

		return file_get_contents($url, false, stream_context_create($options));
	}

	/**
	 * @param int $amount amount in cents
	 * @param string $merchantReference
	 * @return PaystationTransaction
	 */
	function createTransaction($amount, $merchantReference = '') {
		$txn = new PaystationTransaction();
		$txn->amount = $amount;
		$txn->merchantReference = $merchantReference;
		$txn->testMode = $this->testMode;
		$txn->paystationId = $this->paystationId;
		$txn->gatewayId = $this->gatewayId;
		$txn->merchantSession = $this->db->createMerchantSession($txn);

		$params = [
			'paystation' => '_empty',
			'pstn_pi' => $txn->paystationId,
			'pstn_gi' => $txn->gatewayId,
			'pstn_ms' => $txn->merchantSession,
			'pstn_am' => $txn->amount,
			'pstn_nr' => 't',
			'pstn_rf' => 'JSON'
		];

		if ($txn->merchantReference) {
			$params['pstn_mr'] = $txn->merchantReference;
		}

		if ($txn->testMode) {
			$params['pstn_tm'] = 't';
		}

		$result = $this->post($this->paymentURL, $params);
		$json = json_decode($result, true);

		$json = isset($json['InitiationRequestResponse']) ? $json['InitiationRequestResponse'] : (isset($json['response']) ? $json['response'] : null);

		if (isset($json['DigitalOrder'])) {
			$txn->transactionId = "$json[PaystationTransactionID]";
			$txn->digitalOrderUrl = "$json[DigitalOrder]&rebrand=rebrand"; // The URL that we re-direct the customer too.
			$txn->paymentRequestTime = "$json[PaymentRequestTime]"; // The time that the transaction was initiated.
			$txn->digitalOrderTime = "$json[DigitalOrderTime]"; // The time Paystation responds.
			$txn->hasError = false;
			$txn->errorCode = -1;
		}
		elseif (isset($json['PaystationErrorCode'])) {
			$txn->hasError = true;
			$txn->errorCode = "$json[PaystationErrorCode]";
			$txn->errorMessage = "(Paystation error code: $json[PaystationErrorCode]) Failed to create new transaction.";

			if ($this->testMode) {
				$txn->errorMessage .= " Please check that the correct Paystation ID is set in your config. Paystation error message: $json[PaystationErrorMessage]";
			}
		}
		else {
			$txn->errorMessage = "Failed to create new transaction. Unexpected response from Paystation.";
		}

		$this->db->save($txn);

		return $txn;
	}

	public function getTransaction($transactionId) {
		$txn = $this->db->get($transactionId);

		if (!$txn || $txn->errorCode < 0) {
			$txn = $this->lookupTransaction($transactionId);
		}

		if (!$txn) {
			$txn = new PaystationTransaction();
			$txn->errorMessage = 'No transaction details found.';
		}

		return $txn;
	}

	private function lookupTransaction($transactionId) {
		$result = $this->post($this->lookupURL, ['pi' => $this->paystationId, 'ti' => $transactionId]);
		$xml = new SimpleXMLElement($result);
		$txn = new PaystationTransaction();
		if (isset($xml->LookupResponse->PaystationTransactionID) && $xml->LookupResponse->PaystationTransactionID == $transactionId) {
			$txn->transactionId = "{$xml->LookupResponse->PaystationTransactionID}";
			$txn->amount = "{$xml->LookupResponse->PurchaseAmount}";
			$txn->transactionTime = "{$xml->LookupResponse->TransactionTime}";
			$txn->hasError = false;
			$txn->errorCode = "{$xml->LookupResponse->PaystationErrorCode}";
			$txn->errorMessage = "{$xml->LookupResponse->PaystationErrorMessage}";
			$txn->cardType = "{$xml->LookupResponse->CardType}";
			$txn->merchantSession = "{$xml->LookupResponse->MerchantSession}";
			$txn->merchantReference = "{$xml->LookupResponse->MerchantReference}";
			$txn->requestIp = "{$xml->LookupStatus->RemoteHostAddress}";
			$txn->timeout = isset($xml->LookupResponse->Timeout) ? "{$xml->LookupResponse->Timeout}" == 'Y' : null;

			if ($txn->errorCode == '') {
				$txn->errorCode = -1;
			}

			if ($txn->timeout) {
				$txn->hasError = true;
				$txn->errorMessage = 'Payment link has expired.';
			}

			$this->db->save($txn);
			return $txn;
		}
		elseif (isset($xml->LookupStatus)) { // There was an error calling the API.
			$txn->transactionId = $transactionId;
			$txn->hasError = true;
			$txn->errorCode = -1;
			$txn->lookupErrorCode = "{$xml->LookupStatus->LookupCode}";
			$txn->errorMessage = "{$xml->LookupStatus->LookupMessage}";

			$this->db->save($txn); // Save the failed lookup with the original error message.

			// The lookup message can contain your account ID. Hide that from the user.
			$txn->errorMessage = "(Lookup Error Code: {$xml->LookupStatus->LookupCode}) An error occurred while looking up transaction details.";

			// Show a more useful message for debugging purposes.
			if ($this->testMode) {
				$txn->errorMessage .= " Please check that the correct HMAC key is set in your config. Paystation Error Message: {$xml->LookupStatus->LookupMessage}";
			}

			return $txn;
		}

		return null;
	}

	/**
	 * $paystation->savePostResponse(file_get_contents("php://input"));
	 * @param String $rawXML
	 */
	public function savePostResponse($postBody) {
		$xml = new SimpleXMLElement($postBody);
		$txn = new PaystationTransaction();

		$txn->transactionId = "$xml->ti";
		$txn->amount = "$xml->PurchaseAmount";
		$txn->transactionTime = "$xml->TransactionTime";
		$txn->hasError = false;
		$txn->errorCode = "$xml->ec";
		$txn->errorMessage = "$xml->em";
		$txn->cardType = "$xml->ct";
		$txn->merchantSession = "$xml->MerchantSession";
		$txn->requestIp = "$xml->RequestIP";
		$txn->merchantReference = "$xml->merchant_ref";

		if ($txn->transactionId) {
			$this->db->save($txn);
		}
	}
}
