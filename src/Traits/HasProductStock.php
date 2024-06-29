<?php

namespace Appstract\Stock\Traits;

use Appstract\Stock\Models\StockMutation;

trait HasProductStock
{
    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'stockable');
    }

    public function getTotalStock()
    {
        return $this->stockMutations()->sum('quantity');
    }

    public function getStockByWarehouse($warehouseId)
    {
        return $this->stockMutations()
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    public function transferStock($quantity, $toWarehouse)
    {
        $inventoryMethod = config('stock.inventory_method', 'FIFO');

        $query = $this->stockMutations()
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
                'product_id' => $this->id,
                'warehouse_id' => $this->getId(),
                'quantity' => -$transferQuantity, // Ujemna ilość oznacza zmniejszenie
                'type' => 'transfer',
                'from_warehouse_id' => $this->getId(),
                'to_warehouse_id' => $toWarehouse->getId(),
                'purchase_price_id' => $mutation->purchase_price_id,
            ]);

            // Tworzenie rekordu dla zwiększenia stanu magazynowego w magazynie docelowym
            $toWarehouse->stockMutations()->create([
                'product_id' => $this->id,
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
