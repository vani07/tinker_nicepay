<?php

$installer = $this;
$installer->startSetup();
$installer->getConnection()->addColumn($this->getTable('sales_flat_order'), 'nicepay_transaction_id', 'VARCHAR(255) NULL');
$installer->getConnection()->addColumn($this->getTable('sales_flat_order'), 'nicepay_va', 'VARCHAR(255) NULL');
$installer->endSetup();
?>