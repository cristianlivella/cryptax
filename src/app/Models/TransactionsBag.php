<?php

namespace CrypTax\Models;

use CrypTax\Exceptions\CannotFindPurchasesException;

class TransactionsBag
{
    /**
     * All the transactions.
     *
     * @var Transaction[]
     */
    public $transactions = [];

    /**
     * Id of purchase transactions for each cryptocurrency
     * es. $cryptoPurchases['BTC'] contains the id of Bitcoin purchases
     *
     * @var integer[][]
     */
    private $cryptoPurchases = [];

    public function __construct($transactionsFileContent) {
        // explode CSV file content
        $rawTransactions = explode(PHP_EOL, $transactionsFileContent);
        unset($rawTransactions[count($rawTransactions) - 1]);

        // parse the transactions
        foreach ($rawTransactions AS $id => $rawTx) {
            $rawTx = explode(';', $rawTx);
            $this->transactions[$id + 1] = new Transaction($id + 1, $rawTx);
        }

        // sort the transactions
        uasort($this->transactions, function ($a, $b) {
            if ($a->date === $b->date) {
                return $a->id - $b->id;
            }
            return strtotime($a->date) - strtotime($b->date);
        });

        foreach ($this->transactions AS $transaction) {
            if ($transaction->type === Transaction::PURCHASE) {
                $this->addCryptoPurchase($transaction);
            } elseif ($transaction->type === Transaction::SALE || $transaction->type === Transaction::EXPENSE) {
                $this->findRelativePurchases($transaction);
            }
        }
    }

    public function getFirstTransaction() {
        if (count($this->transactions) === 0) {
            return null;
        }

        return $this->transactions[array_keys($this->transactions)[0]];
    }

    public function getLastTransaction() {
        if (count($this->transactions) === 0) {
            return null;
        }

        return $this->transactions[array_keys($this->transactions)[count(array_keys($this->transactions)) - 1]];
    }

    private function addCryptoPurchase($transaction) {
        $id = $transaction->id;
        $ticker = $transaction->ticker;

        if (!isset($this->cryptoPurchases[$ticker])) {
            $this->cryptoPurchases[$ticker] = [];
        }

        $this->cryptoPurchases[$ticker][] = $transaction;
    }

    private function findRelativePurchases($transaction) {
        if (!isset($this->cryptoPurchases[$transaction->ticker])) {
            throw new CannotFindPurchasesException($transaction->id, 0, $transaction->amount);
        }

        $i = count($this->cryptoPurchases[$transaction->ticker]);

        while ($transaction->getPurchaseAmountRemaining() > 0 && $i-- > 0) {
            $purchaseTransaction = $this->cryptoPurchases[$transaction->ticker][$i];
            $partialUse = min($transaction->getPurchaseAmountRemaining(), $purchaseTransaction->amount - $purchaseTransaction->used);

            if ($partialUse > 0) {
                $purchaseTransaction->incrementUsed($partialUse);
                $transaction->associatePurchase($purchaseTransaction, $partialUse);
            }
        }

        if ($transaction->getPurchaseAmountRemaining() > 0) {
            throw new CannotFindPurchasesException($transaction->id, $transaction->getPurchaseAmountRemaining(), $transaction->amount);
        }
    }
}
