<?php
namespace Paystation;

interface TransactionDBInterface {
	/**
	 * @param String $transactionId
	 * @return Transaction|null null if not found.
	 */
	public function get($transactionId);

	/**
	 * Update any field that's not null.
	 * @param Transaction $transaction
	 */
	public function save(Transaction $transaction);

	/**
	 * @param Transaction $transaction The transaction that needs a new ID.
	 * @return string The new ID for the transaction. Max length 64.
	 */
	public function createMerchantSession(Transaction $transaction);
}
