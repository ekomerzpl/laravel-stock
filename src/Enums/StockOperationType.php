<?php

namespace Appstract\Stock\Enums;

enum StockOperationType: string
{
    case purchase = 'purchase';
    case increase = 'increase';
    case decrease = 'decrease';
    case transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::purchase => 'Purchase',
            self::increase => 'Increase',
            self::decrease => 'Decrease',
            self::transfer => 'Transfer',
        };
    }
}
