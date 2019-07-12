<?php

namespace MSThomasXYZ\StockUpdate\Cron;

class Update
{
	private $_stockRegistry;
	private $_csv;
	private $_logger;
	private $_stockIndexer;

    public function __construct(
		\Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
		\Psr\Log\LoggerInterface $logger,
		\Magento\Framework\File\Csv $csv,
		\Magento\CatalogInventory\Model\Indexer\Stock $stockIndexer
	)
	{
		$this->_stockRegistry = $stockRegistry;
		$this->_csv = $csv;
		$this->_logger = $logger;
		$this->_stockIndexer = $stockIndexer;
		$this->_csv->setDelimiter('|');
    }
    
	public function execute()
	{

		$this->_logger->info(__METHOD__);

		$updatedProducts = [];
		
		try {
			$csvData = $this->loadCSV('stock.csv');
		} catch (\Throwable $th) {
			$this->_logger->error(  __METHOD__ . ' ' . $th->getMessage() );
			return $this;
		}

		foreach ($csvData as $row => $data) {
			try {
				$this->_logger->info( __METHOD__ . ' Checking SKU: ' . $data[0]);

				$productId = $this->updateStock( $data[0], $data[1] );
				
				if( $productId != '' ) {
					array_push($updatedProducts, $productId);
				}

			} catch (\Throwable $th) {
				$this->_logger->warning( __METHOD__ . ' Error updating SKU ' .$data[0].': ' . $th->getMessage());
			}
		}

		if( count($updatedProducts) ) {
			$this->_logger->info( __METHOD__ . ' stock updated');
			$this->_stockIndexer->execute($updatedProducts);
			$this->_logger->info( __METHOD__ . ' Indexer updated');
		} else {
			$this->_logger->info( __METHOD__ . ' no stock updated');
		}
		
		$this->_logger->info(__METHOD__ . ' done');
		return $this;

	}

    private function updateStock( $sku, $qty ) {
		$stock = $this->_stockRegistry->getStockItemBySku($sku);
		
		if( $stock->getQty() != $qty ) {
			$stock->setQty($qty);
			$stock->setIsInStock($qty>0);
			$this->_stockRegistry->updateStockItemBySku($sku, $stock);
			$this->_logger->info( __METHOD__ . ' Stock updated ('.$stock->getProductId().'). New value: '.$qty);
			return $stock->getProductId();
		} else {
			$this->_logger->info( __METHOD__ . ' Stock unchanged');
			return '';
		}
	}

	private function loadCSV(String $fileName)
	{
		// check file is there
		if( !file_exists( BP . '/var/import/' .$fileName ) ) {
			throw new \Magento\Framework\Exception\LocalizedException(__('File var/import/'.$fileName.' doesn\'t exist.'));
		}

		// and not empty
		if( !filesize( BP . '/var/import/' .$fileName ) ) {
			throw new \Magento\Framework\Exception\LocalizedException(__('File var/import/'.$fileName.' is empty.'));
		}

		return $this->_csv->getData(BP . '/var/import/' .$fileName);
	}
}
