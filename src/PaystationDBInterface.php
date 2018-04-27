<?php
namespace Paystation;

interface PaystationDBInterface {
	/**
	 * @param String $transactionId
	 * @return PaystationTransaction|null null if not found.
	 */
	public function get($transactionId);

	/**
	 * Update any field that's not null.
	 * @param PaystationTransaction $transaction
	 */
	public function save(PaystationTransaction $transaction);

	/**
	 * @param PaystationTransaction $transaction The transaction that needs a new ID.
	 * @return string The new ID for the transaction. Max length 64.
	 */
	public function createMerchantSession(PaystationTransaction $transaction);
}
