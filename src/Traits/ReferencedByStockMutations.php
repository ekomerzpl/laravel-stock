<?php

namespace Appstract\Stock\Traits;

use Appstract\Stock\Models\StockMutation;

trait ReferencedByStockMutations
{
    /**
     * Relation with StockMutation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\morphMany
     */
    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'reference');
    }
}
