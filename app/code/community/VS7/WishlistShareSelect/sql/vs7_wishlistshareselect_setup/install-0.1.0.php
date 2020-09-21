<?php
$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn(
    $this->getTable('wishlist/item'),
    'show_in_share',
    "BOOLEAN NOT NULL DEFAULT TRUE"
);

$installer->endSetup();