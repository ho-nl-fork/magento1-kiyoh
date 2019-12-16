<?php

$installer = $this;

/* @var $installer Mage_Eav_Model_Entity_Setup */
$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('kiyoh/reviews'),'review_string_id', array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => false,
        'comment'   => 'Review ID'
    ));

$installer->endSetup();
