<?php

return [

    'tables' => [
        'mutations' => 'stock_mutations',
    ],
    'models' => [
        'purchase_price' => \Appstract\Stock\Models\PurchasePrice::class,
        'stock_mutation' => \Appstract\Stock\Models\StockMutation::class,
        'product' => \Appstract\Stock\Models\Product::class,
        'supplier' => \Appstract\Stock\Models\Supplier::class,
        'warehouse' => \Appstract\Stock\Models\Warehouse::class,
    ],
    'inventory_method' => 'FIFO', // Supported methods: 'FIFO', 'LIFO'

];
