<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Interfaces\SupplierInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class StockSupplier extends Model implements SupplierInterface
{
    protected $fillable = [
        'name',
    ];

    public function getId(): int
    {
        return 1;
    }

    public function stockMutations(): HasManyThrough
    {
        return $this->hasManyThrough(StockMutation::class, StockPurchasePrice::class, 'supplier_id', 'purchase_price_id');
    }

    public function getSupplierStockHistory()
    {
        return $this->stockMutations()->get();
    }

}
