<?php

namespace Appstract\Stock\Models;

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

    public function calculateInventoryValue(): float
    {
        $mutations = $this->stockMutations()->with('purchasePrice')->get();

        $totalValue = 0;

        foreach ($mutations as $mutation) {
            if ($mutation->purchasePrice) {
                $totalValue += $mutation->quantity * $mutation->purchasePrice->price;
            }
        }

        return (float)$totalValue;
    }

    public function getProductStockPrices(ProductInterface $product): array
    {
        $order = config('stock.inventory_method', 'FIFO');

        // Pobierz wszystkie mutacje dla danego produktu
        $mutations = $this->stockMutations()
            ->where('stockable_id', $product->getId())
            ->where('stockable_type', $product->getMorphClass())
            ->orderBy('created_at', $order === 'FIFO' ? 'asc' : 'desc')
            ->get();

        $currentStock = [];

        foreach ($mutations as $mutation) {
            if ($mutation->quantity > 0) {
                $currentStock[] = [
                    'quantity' => $mutation->quantity,
                    'purchase_price_id' => $mutation->purchase_price_id,
                    'price' => $mutation->purchasePrice->price
                ];
            } else {
                $remainingQuantity = abs($mutation->quantity);
                while ($remainingQuantity > 0 && !empty($currentStock)) {
                    $firstIndex = 0;
                    $availableQuantity = $currentStock[$firstIndex]['quantity'];

                    if ($availableQuantity <= $remainingQuantity) {
                        $remainingQuantity -= $availableQuantity;
                        array_shift($currentStock);
                    } else {
                        $currentStock[$firstIndex]['quantity'] -= $remainingQuantity;
                        $remainingQuantity = 0;
                    }
                }
            }
        }
        return $currentStock;
    }
}
