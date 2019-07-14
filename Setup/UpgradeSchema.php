<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MSThomasXYZ\StockUpdate\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Upgrade the Catalog module DB scheme
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '0.0.2', '<=')) {
            if ( !$setup->tableExists('msthomas_stockupdate') ) {
                $table = $setup->getConnection()
                    ->newTable($setup->getTable('msthomas_stockupdate'))
                    ->addColumn(
                        'entity_id',
                        \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        null,
                        ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                        'Entity ID'
                    )
                    ->addColumn(
                        'start_time',
                        \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        null,
                        ['nullable' => false],
                        'Start Time'
                    )
                    ->addColumn(
                        'end_time',
                        \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                        null,
                        ['nullable' => true],
                        'End Time'
                    );
                
                $setup->getConnection()->createTable($table);
            }
        }
        $setup->endSetup();
    }
}