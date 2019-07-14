<?php

namespace MSThomasXYZ\StockUpdate\Cron;

class Update
{
	private $_stockRegistry;
	private $_csv;
	private $_logger;
	private $_stockIndexer;
	private $_cacheManager;
	private $_modelFactory;
	private $_currentlyRunning;

	private const DIRECTORY = BP . '/var/import/';
	private const STOCKFILE = 'stock.csv';
	private const CSV_DELIMITER = '|';

	public function __construct(
		\Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
		\Psr\Log\LoggerInterface $logger,
		\Magento\Framework\File\Csv $csv,
		\Magento\CatalogInventory\Model\Indexer\Stock $stockIndexer,
		\Magento\PageCache\Model\Cache\Type $cacheManager,
		\MSThomasXYZ\StockUpdate\Model\DataFactory $modelFactory
	)
	{
		$this->_stockRegistry = $stockRegistry;
		$this->_csv = $csv;
		$this->_logger = $logger;
		$this->_stockIndexer = $stockIndexer;
		$this->_modelFactory = $modelFactory;
		$this->_cacheManager = $cacheManager;
		$this->_csv->setDelimiter(self::CSV_DELIMITER);
    }
	
	/**
	 * The method to execute. In a real example you'll want to make the file path configurable, but there we go,
	 */
	public function execute()
	{
		if( $this->isStockUpdateRunning() ) {
			$this->_logger->error(  __METHOD__ . ' An update is currently running. Please try again later.');
			return $this;
		}
		
		$this->markStockUpdateRunning();

		// keep track of any products we update: We only want to reindex where needed
		$updatedProducts = [];
		
		// try to load the file - fail if empty or non existent
		try {
			$csvData = $this->loadCSV( self::STOCKFILE );
		} catch (\Throwable $th) {
			$this->_logger->error(  __METHOD__ . ' ' . $th->getMessage() );
			return $this->markStockUpdateEnded();
		}

		foreach ($csvData as $row => $data) {
			try {
				$this->_logger->info( __METHOD__ . ' Checking SKU: ' . $data[0]);

				// Some basic syntax check:
				// a) Need two columns
				if( count($data) !== 2 ) {
					throw new \Magento\Framework\Exception\LocalizedException(__('Invalid format on line '. $row.': Has '. count($data). ' column(s).'));
				}

				// b) qty needs to be numeric
				if( !(is_numeric($data[1]) && $data[1] >= 0) ) {
					throw new \Magento\Framework\Exception\LocalizedException(__('Quantity is invalid for product with SKU '.$data[0].'. Current value: '.$data[1].'.'));
				}

				// update stock if needed
				$this->updateStock( $data[0], $data[1], $updatedProducts );

			} catch (\Throwable $th) {
				$this->_logger->warning( __METHOD__ . ' Error updating SKU ' .$data[0].': ' . $th->getMessage());
			}
		}

		$this->updateIndexAndCache( $updatedProducts );
		
		return $this->markStockUpdateEnded();

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
			// check that we are not providing a decimal value for qty, unless decimal quantities are allowed
			if( !$stock->getIsQtyDecimal() && (int)$qty != $qty ) {
				throw new \Magento\Framework\Exception\LocalizedException(__('Decimal quantity not allowed for product with SKU '.$sku.' Current value: '.$qty.'.'));
			}

			// update qty and in stock indicator
			$stock->setQty($qty);
			$stock->setIsInStock( $qty > $stock->getMinQty() );
			$this->_stockRegistry->updateStockItemBySku($sku, $stock);

			// keep track of updated product ids
			array_push($updatedProducts, $stock->getProductId());
			$this->_logger->info( __METHOD__ . ' Stock updated. New value: '.$qty);
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
		if( !file_exists( self::DIRECTORY .$fileName ) ) {
			throw new \Magento\Framework\Exception\LocalizedException(__('File '.self::DIRECTORY.$fileName.' doesn\'t exist.'));
		}

		// and not empty
		if( !filesize( self::DIRECTORY .$fileName ) ) {
			throw new \Magento\Framework\Exception\LocalizedException(__('File '.self::DIRECTORY.$fileName.' is empty.'));
		}

		return $this->_csv->getData(self::DIRECTORY .$fileName);
	}

	/**
	 * Update the stock index and full page cache if needed
	 * @param array $updatedProducts A list of updated products, so that we can reindex only where needed
	 */
	private function updateIndexAndCache( array $updatedProducts ) {
		// if we have updated anything: reindex and clean cache
		if( count($updatedProducts) ) {
			$this->_stockIndexer->execute($updatedProducts);
			$this->_cacheManager->clean(\Zend_Cache::CLEANING_MODE_ALL, array('FPC'));
			$this->_logger->info( __METHOD__ . ' Index and cache updated');
		} else {
			$this->_logger->info( __METHOD__ . ' no stock updated');
		}
	}

	/**
	 * Check if a version of this script is already running
	 * @return bool
	 */
	private function isStockUpdateRunning() 
	{
		$collection = $this->_modelFactory->create();
		$collection = $collection->getCollection();
		$collection->addFieldToFilter('end_time', array('null'=>true));
		return $collection->getSize() !== 0;
	}

	/**	
	 * Create a new row in the database to indicate we are currently running
	 */
	private function markStockUpdateRunning() 
	{
		$this->_currentlyRunning = $this->_modelFactory->create();
		$this->_currentlyRunning->setData(array(
			'start_time' => new \Zend_Db_Expr('NOW()')
		))
		->save();
	}

	/**
	 * When we are done running - whether with error or successfully - update the database entry
	 * to indicate the run is finished
	 */
	private function markStockUpdateEnded() {

		$this->_currentlyRunning->setEndTime( new \Zend_Db_Expr('NOW()') )->save();

		return $this;
	}
}
