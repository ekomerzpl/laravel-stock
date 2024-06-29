<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Traits\HasProductStock;
use Illuminate\Database\Eloquent\Model;
use Appstract\Stock\Interfaces\Product as ProductInterface;

class Product extends Model implements ProductInterface
{
    use HasProductStock;

    public function getId(): int
    {
        return 1;
    }
}
