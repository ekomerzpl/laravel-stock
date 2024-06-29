<?php

namespace Appstract\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePrice extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('stock.tables.purchase_prices', 'stock_purchase_prices'));
    }
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
