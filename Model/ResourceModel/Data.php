<?php

namespace MSThomasXYZ\StockUpdate\Model\ResourceModel;


use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Data extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('msthomas_stockupdate', 'entity_id'); 
    }
}