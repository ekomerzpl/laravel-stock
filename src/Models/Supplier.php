<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Traits\HasSupplierStock;
use Appstract\Stock\Interfaces\Supplier as SupplierInterface;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model implements SupplierInterface
{
    use HasSupplierStock;

    public function getId(): int
    {
        return 1;
    }
}
