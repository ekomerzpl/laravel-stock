<?php

namespace Appstract\Stock\Interfaces;

interface Warehouse
{

    public function getId(): int;

    public function calculateInventoryValue(): float;

}
