<?php

require "classes/reportTransaction.php";

$transactionFiles = ['transactions1.csv', 'transactions2.csv', 'transactions3.csv'];

$reportTransaction = new reportTransaction($transactionFiles, '|');
$reportTransaction->generateCSVReport('outputFile.csv');

?>