<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Interfaces\Supplier as SupplierInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Supplier extends Model implements SupplierInterface
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
        return $this->hasManyThrough(StockMutation::class, PurchasePrice::class, 'supplier_id', 'purchase_price_id');
    }

    public function getSupplierStockHistory()
    {
        return $this->stockMutations()->get();
    }

}
