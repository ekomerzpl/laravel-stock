<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Interfaces\Warehouse as WarehouseInterface;
use Appstract\Stock\Traits\HasWarehouseStock;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model implements WarehouseInterface
{
    use HasWarehouseStock;

    public function getId(): int
    {
        return 1;
    }

}
