<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Exceptions\StockException;
use Appstract\Stock\Interfaces\Product as ProductInterface;
use Appstract\Stock\Interfaces\Warehouse as WarehouseInterface;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model implements WarehouseInterface
{
    protected $fillable = [
        'name',
    ];

    public function getId(): int
    {
        return 1;
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class, 'to_warehouse_id');
    }

    public function getProductStock($productId)
    {
        return $this->stockMutations()
            ->where('stockable_id', $productId)
            ->sum('quantity');
    }

    public function getProductsInStock()
    {
        return $this->stockMutations()
            ->select('stockable_id')
            ->groupBy('stockable_id')
            ->havingRaw('SUM(quantity) > 0')
            ->get();
    }

    public function getProductsOutOfStock()
    {
        return $this->stockMutations()
            ->select('stockable_id')
            ->groupBy('stockable_id')
            ->havingRaw('SUM(quantity) <= 0')
            ->get();
    }

    public function getHistoryBySupplier($supplierId)
    {
        return $this->stockMutations()
            ->whereHas('purchasePrice', function ($query) use ($supplierId) {
                $query->where('supplier_id', $supplierId);
            })
            ->get();
    }

    public function increaseStock($productId, $quantity, $purchasePriceId = null)
    {
        return $this->createStockMutation($productId, $quantity, $purchasePriceId);
    }

    public function decreaseStock($productId, $quantity): void
    {
        $order = config('stock.inventory_method', 'FIFO');
        $remainingQuantity = $quantity;

        $mutations = $this->stockMutations()
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->orderBy('created_at', $order === 'FIFO' ? 'asc' : 'desc')
            ->get();

        foreach ($mutations as $mutation) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $mutation->quantity;
            $decreaseQuantity = min($availableQuantity, $remainingQuantity);

            // Twórz nową mutację ze zmniejszoną ilością
            $this->createStockMutation($productId, -$decreaseQuantity, $mutation->purchase_price_id);

            $remainingQuantity -= $decreaseQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new StockException('Not enough stock to decrease.');
        }
    }

    public function transferStock(ProductInterface $stockable, $toWarehouse, $quantity)
    {
        $order = config('stock.inventory_method', 'FIFO');
        $remainingQuantity = $quantity;

        $mutations = $this->stockMutations()
            ->where('stockable_id', $stockable->getId())
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('quantity', '>', 0)
            ->orderBy('created_at', $order === 'FIFO' ? 'asc' : 'desc')
            ->get();

        foreach ($mutations as $mutation) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $mutation->quantity;
            $transferQuantity = min($availableQuantity, $remainingQuantity);

            // Zmniejsz ilość w obecnej mutacji
            $mutation->quantity -= $transferQuantity;
            $mutation->save();

            // Twórz nową mutację dla magazynu docelowego
            $toWarehouse->createStockMutation($productId, $transferQuantity, $mutation->purchase_price_id);

            // Twórz nową mutację dla zmniejszenia w magazynie źródłowym
            $this->createStockMutation($productId, -$transferQuantity, $mutation->purchase_price_id);

            $remainingQuantity -= $transferQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new StockException('Not enough stock to transfer.');
        }
    }

    protected function createStockMutation($productId, $quantity, $purchasePriceId = null)
    {
        return $this->stockMutations()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'purchase_price_id' => $purchasePriceId,
        ]);
    }

    public function calculateInventoryValue(): float
    {
        $mutations = $this->stockMutations()->with('purchasePrice')->get();

        $totalValue = 0;

        foreach ($mutations as $mutation) {
            if ($mutation->quantity > 0 && $mutation->purchasePrice) {
                $totalValue += $mutation->quantity * $mutation->purchasePrice->price;
            }
        }

        return (float)$totalValue;
    }

}
