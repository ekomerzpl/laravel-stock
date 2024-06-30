<?php

namespace Appstract\Stock\Models;

use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    protected $fillable = [
        'stockable_type',
        'stockable_id',
        'reference_type',
        'reference_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'purchase_price_id',
        'quantity',
        'type',
        'description',
    ];

    /**
     * StockMutation constructor.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('stock.tables.mutations', 'stock_mutations'));
    }

    /**
     * Relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function stockable()
    {
        return $this->morphTo();
    }

    /**
     * Relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function warehouse()
    {
        return $this->morphTo();
    }
}
