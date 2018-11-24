<?php

class reportTransaction {
	const INPUT_TYPE_CSV = 'csv';
	const ERROR_NO_INPUT_FILES = 'No Input File(s) Given';
	const ERROR_INVALID_INPUT_FILES_TYPE = 'Input file should be type csv';
	const ERROR_INVALID_OUTPUT_FILE = 'Output file must be type csv';

	const INPUT_COLUMN_USER_ID = 0;
	const INPUT_COLUMN_AMOUNT = 2;
	const INPUT_COLUMN_DATE = 4;
	const INPUT_COLUMN_TYPE = 5;

	const INPUT_TRANSCTION_TYPE_DEBIT = 'debit';
	const INPUT_TRANSCTION_TYPE_CREDIT = 'credit';

	const NUMBER_OF_TRANSACTIONS = 'numberOfTransactions';
	const SUM_OF_TRANSACTIONS = 'sumOfTransactions';
	const MINIMUM_BALANCE = 'minimumBalance';
	const MAXIMUM_BALANCE = 'maximumBalance';


	private $inputFiles = [];
	private $inputFileType = '';
	private $delimiter = '';
	private $reportData = [];

	public function __construct(
		$fileInputPaths = [],
		$delimiter = '|'
	) {

		if (empty($fileInputPaths)) {
			throw new Exception(self::ERROR_NO_INPUT_FILES);
		}

		foreach($fileInputPaths as $inputPath) {
			$extension = pathinfo($inputPath, PATHINFO_EXTENSION);

			if (strtolower($extension) !== self::INPUT_TYPE_CSV) {
				throw new Exception(self::ERROR_INVALID_INPUT_FILES_TYPE);
			}
		}

		$this->inputFiles = $fileInputPaths;
		$this->delimiter = $delimiter;
	}

	public function generateCSVReport($outputFile){
		$this->calculateReportData();
		$this->exportResult($outputFile);
	}

	private function calculateReportData() {
		/*
			outputResult ----->
			'userId' => [
				'numberOfTransactions'
				'minimumBalance'
				'maximumBalance'
				'sumOfTransactions'
			]
		*/

		$outputResult = [];

		/*
			tempResult ----->
			'userId' => [
				'transactionDate' => [
					'balance'
				]

			]
		*/

		$tempResult = [];
		$isHeader = true; // used to skip header

		foreach($this->inputFiles as $file) {
			if (($handle = fopen($file, "r")) !== FALSE) {
			  while (($data = fgetcsv($handle, null, $this->delimiter)) !== false) {

			  	if ($isHeader || 
			  		$this->is_valid_date($data[self::INPUT_COLUMN_DATE]) === false
			  	) {
			  		$isHeader = false;
			  		continue;
			  	}

					if (isset($tempResult[$data[self::INPUT_COLUMN_USER_ID]])) {
						if ($data[self::INPUT_COLUMN_TYPE] === self::INPUT_TRANSCTION_TYPE_CREDIT) {
							$amountModifier = 1;
						} elseif ($data[self::INPUT_COLUMN_TYPE] === self::INPUT_TRANSCTION_TYPE_DEBIT) {
							$amountModifier = -1;
						} else {
							$amountModifier = 0;
						}

						$amount = $amountModifier * $data[self::INPUT_COLUMN_AMOUNT];

						$tempResult[$data[self::INPUT_COLUMN_USER_ID]][$data[self::INPUT_COLUMN_DATE]][] = $amount;

					} else {
						//	Next user id info

						//---> process old one
						if (!empty($tempResult)) {
							$output = $this->processTransaction($tempResult);

							$userId = array_keys($output);
							$userId = $userId[0];

							$outputResult[$userId] = $output[$userId];
						}
						
						$tempResult = [];

						//---> start new one

						if ($data[self::INPUT_COLUMN_TYPE] === self::INPUT_TRANSCTION_TYPE_CREDIT) {
							$amountModifier = 1;
						} elseif ($data[self::INPUT_COLUMN_TYPE] === self::INPUT_TRANSCTION_TYPE_DEBIT) {
							$amountModifier = -1;
						} else {
							$amountModifier = 0;
						}

						$amount = $amountModifier * $data[self::INPUT_COLUMN_AMOUNT];

						$tempResult[$data[self::INPUT_COLUMN_USER_ID]] = [
							$data[self::INPUT_COLUMN_DATE] => [
								$amount
							]
						];
					}

			  } 
			}
			fclose($handle);
		}

		//	handle the last record
		if (isset($tempResult)) {
			$output = $this->processTransaction($tempResult);

			$userId = array_keys($output);
			$userId = $userId[0];

			$outputResult[$userId] = $output[$userId];
		}

		$this->reportData = $outputResult;

		return true;
	}

	private function exportResult($outputFile) {
		$extension = pathinfo($outputFile, PATHINFO_EXTENSION);

		if (strtolower($extension) !== self::INPUT_TYPE_CSV) {
			throw new Exception(self::ERROR_INVALID_OUTPUT_FILE);
		}

		$file = fopen($outputFile,"w");
		fwrite($file, "user_id,n,sum,min,max \n");
		foreach($this->reportData as $userId=>$record) {

			$sumOfTransactions = number_format($record['sumOfTransactions'], 2, '.', '');
			$minimumBalance = number_format($record['minimumBalance'], 2, '.', '');
			$maximumBalance = number_format($record['maximumBalance'], 2, '.', '');

			fwrite($file, "{$userId},{$record['numberOfTransactions']},{$sumOfTransactions},{$minimumBalance},{$maximumBalance}\n");
		}

		fclose($file);
	}

	private function processTransaction($tempResult) {
		$userId = array_keys($tempResult);
		$userId = $userId[0];

		$data = $tempResult[$userId];

		//	Sort but keep the index (dont change the index)
		ksort($data);

		$maximumBalance = 0;
		$minimumBalance = 0;
		$totalTransaction = 0;
		$totalBalance = 0;

		foreach($data as $dailyRecord) {
			foreach($dailyRecord as $singleTransaction) {
				$totalTransaction++;
				$totalBalance = $totalBalance + $singleTransaction;
			}

			if ($minimumBalance > $totalBalance) {
				$minimumBalance = $totalBalance;
			}

			if ($maximumBalance < $totalBalance) {
				$maximumBalance = $totalBalance;
			}
		}

		/*
			'userId' => [
				'numberOfTransactions'
				'minimumBalance'
				'maximumBalance'
				'sumOfTransactions'
			]
		*/

		if ($minimumBalance > 0) {
			$minimumBalance = 0;
		}

		return [
			$userId => [
				self::NUMBER_OF_TRANSACTIONS => $totalTransaction,
				self::MINIMUM_BALANCE => $minimumBalance,
				self::MAXIMUM_BALANCE => $maximumBalance,
				self::SUM_OF_TRANSACTIONS => $totalBalance
			]
		];
	}

	private function is_valid_date($date) {
		return (bool) preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $date);
	}
}

?>