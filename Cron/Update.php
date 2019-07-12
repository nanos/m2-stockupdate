<?php

namespace MSThomasXYZ\StockUpdate\Cron;

class Update
{
	private $_productRepository;
	private $_stockRegistry;
	private $_csv;
	private $_logger;

    public function __construct(
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
		\Psr\Log\LoggerInterface $logger,
    	\Magento\Framework\File\Csv $csv
	)
	{
		$this->_productRepository = $productRepository;
		$this->_stockRegistry = $stockRegistry;
		$this->_csv = $csv;
		$this->_logger = $logger;
		$this->_csv->setDelimiter('|');
    }
    
	public function execute()
	{

		$this->_logger->info(__METHOD__);
		
		try {
			$csvData = $this->loadCSV('stock.csv');
		} catch (\Throwable $th) {
			$this->_logger->error(  __METHOD__ . ' ' . $th->getMessage() );
			return $this;
		}

		foreach ($csvData as $row => $data) {
			try {
				$this->_logger->info( __METHOD__ . ' Checking SKU: ' . $data[0]);
				$product = $this->getProductBySku($data[0]);
				
				$this->_logger->info( __METHOD__ . ' Product: ' . $product->getName());
				
				$this->updateStock($product, $data[1]);

				$this->_logger->info( __METHOD__ . ' stock updated');

			} catch (\Throwable $th) {
				$this->_logger->warn( __METHOD__ . ' Error updating SKU ' .$data[0].': ' . $th->getMessage());
			}
		}
		
		$this->_logger->info(__METHOD__ . ' done');
		return $this;

	}

    private function getProductBySku($sku)
	{
		return $this->_productRepository->get($sku);
	}

	private function updateStock( $product, $qty ) {
		$stockItem = $this->_stockRegistry->getStockItem($product->getId());
		$stockItem->setData('qty', $qty);
		$stockItem->setData('is_in_stock', ($qty > 0 ) ? 1 : 0);
		$stockItem->save();
	}
	
	private function loadCSV(String $fileName)
	{
		if( !file_exists( BP . '/var/import/' .$fileName ) ) {
			throw new \Magento\Framework\Exception\LocalizedException(__('File var/import/'.$fileName.' doesn\'t exist.'));
		}

		if( !filesize( BP . '/var/import/' .$fileName ) ) {
			throw new \Magento\Framework\Exception\LocalizedException(__('File var/import/'.$fileName.' is empty.'));
		}

		return $this->_csv->getData(BP . '/var/import/' .$fileName);
	}
}
