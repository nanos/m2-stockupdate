<?php

namespace MSThomasXYZ\StockUpdate\Cron;

class Update
{
	private $_stockRegistry;
	private $_csv;
	private $_logger;
	private $_stockIndexer;
	private $_cacheManager;

	public function __construct(
		\Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
		\Psr\Log\LoggerInterface $logger,
		\Magento\Framework\File\Csv $csv,
		\Magento\CatalogInventory\Model\Indexer\Stock $stockIndexer,
		\Magento\PageCache\Model\Cache\Type $cacheManager
	)
	{
		$this->_stockRegistry = $stockRegistry;
		$this->_csv = $csv;
		$this->_logger = $logger;
		$this->_stockIndexer = $stockIndexer;
		$this->_cacheManager = $cacheManager;
		$this->_csv->setDelimiter('|');
    }
	
	/**
	 * The method to execute. In a real example you'll want to make the file path configurable, but there we go,
	 */
	public function execute()
	{

		$this->_logger->info(__METHOD__);

		// keep track of any products we update: We only want to reindex where needed
		$updatedProducts = [];
		
		// try to load the file - fail if empty
		try {
			$csvData = $this->loadCSV('stock.csv');
		} catch (\Throwable $th) {
			$this->_logger->error(  __METHOD__ . ' ' . $th->getMessage() );
			return $this;
		}

		foreach ($csvData as $row => $data) {
			try {
				$this->_logger->info( __METHOD__ . ' Checking SKU: ' . $data[0]);

				// check we have a numeric quantity
				if( !is_numeric($data[1]) && $data[1] >= 0 ) {
					throw new \Magento\Framework\Exception\LocalizedException(__('Quantity is invalid for product with SKU '.$data[0].'. Current value: '.$data[1].'.'));
				}

				// update stock
				$productId = $this->updateStock( $data[0], $data[1], $updatedProducts );

			} catch (\Throwable $th) {
				$this->_logger->warning( __METHOD__ . ' Error updating SKU ' .$data[0].': ' . $th->getMessage());
			}
		}

		// if we have updated anything: reindex and clean cache
		if( count($updatedProducts) ) {
			$this->_stockIndexer->execute($updatedProducts);
			$this->_cacheManager->clean(\Zend_Cache::CLEANING_MODE_ALL, array('FPC'));
			$this->_logger->info( __METHOD__ . ' Index and cache updated');
		} else {
			$this->_logger->info( __METHOD__ . ' no stock updated');
		}
		
		$this->_logger->info(__METHOD__ . ' done');
		return $this;

	}

	/**	
	 * Update the stock properties of a given product, after doing some sanity checking
	 * 
	 * @param string $sku The SKU of the product
	 * @param float $qty The quantity of the product
	 * @param array $updatedProducts An array of updated product ids to keep track
	 * 
	 * @return bool Indicator of whether we have updated or not
	 */
    private function updateStock( string $sku, float $qty, array &$updatedProducts ) {
		$stock = $this->_stockRegistry->getStockItemBySku($sku);
		
		//check if update needed
		if( $stock->getQty() != $qty ) {
			// check that we are not providing a float for qty, unless decimal quantities are allowed
			if( !$stock->getIsQtyDecimal() && (int)$qty != $qty ) {
				throw new \Magento\Framework\Exception\LocalizedException(__('Decimal quantity not allowed for product with SKU '.$sku.' Current value: '.$qty.'.'));
			}
			$stock->setQty($qty);
			$stock->setIsInStock( $qty > $stock->getMinQty() );
			$this->_stockRegistry->updateStockItemBySku($sku, $stock);

			// keep track of product id
			array_push($updatedProducts, $stock->getProductId());
			$this->_logger->info( __METHOD__ . ' Stock updated ('.$stock->getProductId().'). New value: '.$qty);
			return true;
		} else {
			$this->_logger->info( __METHOD__ . ' Stock unchanged');
			return false;
		}
	}

	/**	
	 * Load a file as CSV. Fails if file not present or empty
	 * 
	 * @param string $fileName The filename
	 * @return array An array representation of the filename
	 */
	private function loadCSV(string $fileName)
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
