<?php

namespace Appstract\Stock\Interfaces;

interface Warehouse
{

    public function getId(): int;

    public function increaseStock($quantity, $purchasePriceId = null);

    public function decreaseStock($quantity, $purchasePriceId = null);

}
