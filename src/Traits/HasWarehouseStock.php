<?php

namespace Appstract\Stock\Traits;

use Appstract\Stock\Models\StockMutation;

trait HasWarehouseStock
{
    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }

    public function getProductStock($productId)
    {
        return $this->stockMutations()
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    public function getProductsInStock()
    {
        return $this->stockMutations()
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity) > 0')
            ->get();
    }

    public function getProductsOutOfStock()
    {
        return $this->stockMutations()
            ->select('product_id')
            ->groupBy('product_id')
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

    public function increaseStock($quantity, $purchasePriceId = null): void
    {
        $this->stock += $quantity;
        $this->save();

        $this->stockMutations()->create([
            'quantity' => $quantity,
            'type' => 'increase',
            'purchase_price_id' => $purchasePriceId,
        ]);
    }

    public function decreaseStock($quantity, $purchasePriceId = null): void
    {
        $this->stock -= $quantity;
        $this->save();

        $this->stockMutations()->create([
            'quantity' => $quantity,
            'type' => 'decrease',
            'purchase_price_id' => $purchasePriceId,
        ]);
    }

    public function transferStock($productId, $quantity, $toWarehouse)
    {
        $inventoryMethod = config('stock.inventory_method', 'FIFO');

        $query = $this->stockMutations()
            ->where('product_id', $productId)
            ->where('type', 'increase')
            ->where('quantity', '>', 0);

        if ($inventoryMethod === 'LIFO') {
            $query->orderBy('created_at', 'desc'); // LIFO (last-in, first-out)
        } else {
            $query->orderBy('created_at', 'asc'); // FIFO (first-in, first-out)
        }

        $stockMutations = $query->get();
        $remainingQuantity = $quantity;

        foreach ($stockMutations as $mutation) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $transferQuantity = min($mutation->quantity, $remainingQuantity);

            // Zmniejszenie stanu magazynowego w magazynie źródłowym
            $this->decreaseStock($transferQuantity, $mutation->purchase_price_id);

            // Zwiększenie stanu magazynowego w magazynie docelowym
            $toWarehouse->increaseStock($transferQuantity, $mutation->purchase_price_id);

            // Tworzenie rekordu dla zmniejszenia stanu magazynowego w magazynie źródłowym
            $this->stockMutations()->create([
                'product_id' => $productId,
                'warehouse_id' => $this->getId(),
                'quantity' => -$transferQuantity, // Ujemna ilość oznacza zmniejszenie
                'type' => 'transfer',
                'from_warehouse_id' => $this->getId(),
                'to_warehouse_id' => $toWarehouse->getId(),
                'purchase_price_id' => $mutation->purchase_price_id,
            ]);

            // Tworzenie rekordu dla zwiększenia stanu magazynowego w magazynie docelowym
            $toWarehouse->stockMutations()->create([
                'product_id' => $productId,
                'warehouse_id' => $toWarehouse->getId(),
                'quantity' => $transferQuantity, // Dodatnia ilość oznacza zwiększenie
                'type' => 'transfer',
                'from_warehouse_id' => $this->getId(),
                'to_warehouse_id' => $toWarehouse->getId(),
                'purchase_price_id' => $mutation->purchase_price_id,
            ]);

            $remainingQuantity -= $transferQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception('Not enough stock to transfer.');
        }
    }
}
