<?php

namespace Appstract\Stock\Tests;

use Appstract\Stock\Traits\ReferencedByStockMutations;
use Illuminate\Database\Eloquent\Model;

class ReferenceModel extends Model
{
    use ReferencedByStockMutations;

    protected $table = 'reference_models';

    protected $guarded = [];

    public $timestamps = false;
}
