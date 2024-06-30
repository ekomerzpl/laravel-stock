<?php

namespace Appstract\Stock\Interfaces;

interface Product
{
    public function getId(): int;

    public function stock($date = null, $arguments = []);
}
