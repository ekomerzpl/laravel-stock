<?php

namespace Appstract\Stock\Interfaces;

interface WarehouseInterface
{

    public function getId(): int;

    public function calculateInventoryValue(): float;

}
