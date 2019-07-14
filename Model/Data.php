<?php
namespace MSThomasXYZ\StockUpdate\Model;

use Magento\Framework\Model\AbstractModel;

    class Data extends AbstractModel
    {   
        protected function _construct()
        {
            $this->_init('MSThomasXYZ\StockUpdate\Model\ResourceModel\Data');
        }
    }