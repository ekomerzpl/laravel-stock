<?php

namespace Appstract\Stock\Traits;

use Appstract\Stock\Models\PurchasePrice;
use Appstract\Stock\Models\StockMutation;

trait HasSupplierStock
{
    public function stockMutations()
    {
        return $this->hasManyThrough(StockMutation::class, PurchasePrice::class, 'supplier_id', 'purchase_price_id');
    }

    public function getSupplierStockHistory()
    {
        return $this->stockMutations()->get();
    }
}
