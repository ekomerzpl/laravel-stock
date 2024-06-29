<?php

namespace Appstract\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePrice extends Model
{

    protected $fillable = [
        'product_id',
        'supplier_id',
        'price',
    ];

    public function product()
    {
        return $this->belongsTo(config('stock.models.product'));
    }

    public function supplier()
    {
        return $this->belongsTo(config('stock.models.supplier'));
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }
}
