<?php

namespace Appstract\Stock\Interfaces;

interface ProductInterface
{
    public function getId(): int;

    public function stock($date = null, $arguments = []);
}
