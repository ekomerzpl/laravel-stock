<?php

namespace Appstract\Stock\Interfaces;

interface SupplierInterface
{
    public function getId(): int;

    public function stockMutations();

}
