<?php

namespace Appstract\Stock\Interfaces;

interface Warehouse
{

    public function getId(): int;

    public function increaseStock($productId, $quantity, $purchasePriceId = null);

    public function decreaseStock($productId, $quantity);

    public function transferStock(Product $stockable, $toWarehouse, $quantity);

    public function calculateInventoryValue(): float;

}
