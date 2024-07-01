<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Enums\StockOperationType;
use \Appstract\Stock\Interfaces\WarehouseInterface as WarehouseInterface;
use Illuminate\Database\Eloquent\Model;

class StockOperationData
{
    public ?WarehouseInterface $warehouseFrom = null;
    public ?WarehouseInterface $warehouseTo;
    public StockOperationType $operation = StockOperationType::increase;
    public ?Model $reference = null;
    public ?StockSupplier $supplier = null;
    public ?string $description = null;
    public ?int $purchasePriceId = null;
    public float $price = 0;
    public int $quantity = 0;

    public function validate(): void
    {
        switch ($this->operation) {
            case StockOperationType::purchase:
                if ($this->quantity <= 0) {
                    throw new \InvalidArgumentException("Quantity must be greater than 0");
                }
                if (!$this->warehouseTo) {
                    throw new \InvalidArgumentException("Warehouse to is required");
                }
                if (!$this->supplier) {
                    throw new \InvalidArgumentException("Supplier is required");
                }
                if ($this->price <= 0) {
                    throw new \InvalidArgumentException("Price must be greater than 0");
                }
                break;
            case StockOperationType::increase:
            case StockOperationType::decrease:
                if ($this->quantity <= 0) {
                    throw new \InvalidArgumentException("Quantity must be greater than 0");
                }
                if (!$this->warehouseTo) {
                    throw new \InvalidArgumentException("Warehouse to is required");
                }
                break;
            case StockOperationType::transfer:
                if ($this->quantity <= 0) {
                    throw new \InvalidArgumentException("Quantity must be greater than 0");
                }
                if (!$this->warehouseTo) {
                    throw new \InvalidArgumentException("Warehouse to is required");
                }
                if (!$this->warehouseFrom) {
                    throw new \InvalidArgumentException("Warehouse from is required");
                }
                break;
            default:
                throw new \InvalidArgumentException("Invalid operation type");
        }
    }
}
