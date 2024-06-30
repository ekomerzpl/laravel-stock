<?php

namespace Appstract\Stock\Interfaces;

interface Supplier
{
    public function getId(): int;

    public function stockMutations();

}
