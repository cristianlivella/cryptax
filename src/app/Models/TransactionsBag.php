<?php

namespace CrypTax\Models;

use CrypTax\Exceptions\InvalidFileException;
use CrypTax\Exceptions\CannotFindPurchasesException;
use CrypTax\Utils\AesUtils;

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
        $transactionsJsonKey = hash('sha256', $transactionsFileContent);
        $transactionsJsonName = hash('sha256', $transactionsJsonKey . $transactionsFileContent);
        $transactionsJsonFile = dirname(__FILE__) . '/../../tmp/' . $transactionsJsonName;

        // check if the JSON cache of the transactions file already exists
        if (file_exists($transactionsJsonFile)) {
            $jsonFileContent = AesUtils::decrypt(file_get_contents($transactionsJsonFile), $transactionsJsonKey);
            $rawTransactions = json_decode($jsonFileContent, true);
        } else {
            try {
                // create a temporary file, needed for PhpSpreadsheet
                $tmpFile = tmpfile();
                fwrite($tmpFile, $transactionsFileContent);
                $filePath = stream_get_meta_data($tmpFile)['uri'];

                // parse the spreadsheet file into an array
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $rawTransactions = $spreadsheet->getSheet(0)->toArray();

                // save the array in the JSON cache file
                file_put_contents($transactionsJsonFile, AesUtils::encrypt(json_encode($rawTransactions), $transactionsJsonKey));
            } catch (\Exception $e) {
                throw new InvalidFileException();
            } finally {
                unlink($filePath);
            }
        }

        $dateFormat1 = '/[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}/';    // dd-mm-YYYY
        $dateFormat2 = '/[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/';      // YYYY-mm-dd

        $firstTxDate = $rawTransactions[0][0] ?? false;
        if ($firstTxDate !== false && !preg_match($dateFormat1, $firstTxDate) && !preg_match($dateFormat2, $firstTxDate)) {
            // if the date of the first row is invalid, assume it's a header row and ignore it
            unset($rawTransactions[0]);
        }

        // parse the transactions
        $rawTransactions = array_values($rawTransactions);
        foreach ($rawTransactions AS $id => $rawTx) {
            $this->transactions[$id + 1] = new Transaction($id + 1, $rawTx);
        }

        // sort the transactions
        uasort($this->transactions, function ($a, $b) {
            if ($a->date === $b->date) {
                return $a->id - $b->id;
            }

            return $a->timestamp - $b->timestamp;
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
        $purchaseAmountRemaining = $transaction->amount;

        while ($purchaseAmountRemaining > 0 && $i-- > 0) {
            $purchaseTransaction = $this->cryptoPurchases[$transaction->ticker][$i];
            $partialUse = min($purchaseAmountRemaining, $purchaseTransaction->amount - $purchaseTransaction->used);

            if ($partialUse > 0) {
                $purchaseTransaction->incrementUsed($partialUse);
                $transaction->associatePurchase($purchaseTransaction, $partialUse);
                $purchaseAmountRemaining -= $partialUse;
            }

            if ($purchaseTransaction->amount === $purchaseTransaction->used) {
                unset($this->cryptoPurchases[$transaction->ticker][$i]);
            }
        }

        $this->cryptoPurchases[$transaction->ticker] = array_values($this->cryptoPurchases[$transaction->ticker]);

        if ($purchaseAmountRemaining > pow(10, -12)) {
            throw new CannotFindPurchasesException($transaction->id, $transaction->amount - $purchaseAmountRemaining, $transaction->amount);
        }
    }
}
