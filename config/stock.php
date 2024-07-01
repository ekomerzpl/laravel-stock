<?php

return [

    'tables' => [
        'mutations' => 'stock_mutations',
        'purchase_prices' => 'stock_purchase_prices',
    ],
    'models' => [
        'purchase_price' => \Appstract\Stock\Models\StockPurchasePrice::class,
        'stock_mutation' => \Appstract\Stock\Models\StockMutation::class,
        'product' => \Appstract\Stock\Models\StockProduct::class,
        'supplier' => \Appstract\Stock\Models\StockSupplier::class,
        'warehouse' => \Appstract\Stock\Models\StockWarehouse::class,
    ],
    'inventory_method' => 'FIFO', // Supported methods: 'FIFO', 'LIFO'

];
