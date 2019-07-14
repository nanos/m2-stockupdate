<?PHP

namespace MSThomasXYZ\StockUpdate\Model\ResourceModel\Data;


use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;


class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
        'MSThomasXYZ\StockUpdate\Model\Data',
        'MSThomasXYZ\StockUpdate\Model\ResourceModel\Data'
    );
    }
}