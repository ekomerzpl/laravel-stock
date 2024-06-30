<?php

namespace Appstract\Stock\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Appstract\Stock\Interfaces\Product as ProductInterface;
use Illuminate\Support\Arr;

class Product extends Model implements ProductInterface
{

    protected $fillable = [
        'catalog_number',
        'name',
        'manufacturer',
        'status',
        'average_purchase_price',
        'catalog_price_net',
    ];

    public function getId(): int
    {
        return 1;
    }

    public function getStockAttribute()
    {
        return $this->stock();
    }

    public function stock($date = null, $arguments = [])
    {
        $date = $date ?: Carbon::now();

        if (!$date instanceof \DateTimeInterface) {
            $date = Carbon::create($date);
        }

        $mutations = $this->stockMutations()->where('created_at', '<=', $date->format('Y-m-d H:i:s'));
        $reference = Arr::get($arguments, 'reference');
        $warehouse = Arr::get($arguments, 'warehouse');

        if ($reference) {
            $mutations->where([
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => $reference->getKey(),
            ]);
        }

        if ($warehouse) {
            $mutations->where([
                'to_warehouse_id' => $warehouse->getKey(),
            ]);
        }

        return (int)$mutations->sum('quantity');
    }

    public function increaseStock($quantity, $warehouseId, $purchasePriceId = null)
    {
        return $this->createStockMutation($quantity, $warehouseId, $purchasePriceId);
    }

    public function decreaseStock($quantity, $warehouseId = 1): void
    {
        $order = config('stock.inventory_method', 'FIFO');

        $remainingQuantity = $quantity;

        $mutations = $this->stockMutations()
            ->where('to_warehouse_id', $warehouseId)
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
            $this->createStockMutation(-$decreaseQuantity, $warehouseId, $mutation->purchase_price_id);

            $remainingQuantity -= $decreaseQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception('Not enough stock to decrease.');
        }
    }

    public function transferStock($toWarehouseId, $quantity): void
    {

        $order = config('stock.inventory_method', 'FIFO');
        $remainingQuantity = $quantity;

        $mutations = $this->stockMutations()
            ->where('quantity', '>', 0)
            ->orderBy('created_at', $order === 'FIFO' ? 'asc' : 'desc')
            ->get();

        foreach ($mutations as $mutation) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $mutation->quantity;
            $transferQuantity = min($availableQuantity, $remainingQuantity);

            // Twórz nową mutację dla magazynu docelowego
            $this->createStockMutation($transferQuantity, $toWarehouseId, $mutation->purchase_price_id);

            // Twórz nową mutację dla zmniejszenia w magazynie źródłowym
            $this->createStockMutation(-$transferQuantity, $mutation->to_warehouse_id, $mutation->purchase_price_id);

            $remainingQuantity -= $transferQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new \Exception('Not enough stock to transfer.');
        }
    }

    public function createPurchase($attributes)
    {
        $puchasePriceClass = config('stock.models.purchase_price');
        $purchasePrice = $puchasePriceClass::create([
            'product_id' => $this->id,
            'supplier_id' => $attributes['supplier_id'] ?? null,
            'price' => $attributes['price'],
        ]);

        $this->increaseStock($attributes['quantity'], $attributes['to_warehouse_id'], $purchasePrice->id);

        return $purchasePrice;
    }

    protected function createStockMutation($quantity, $warehouseId, $purchasePriceId = null)
    {
        return $this->stockMutations()->create([
            'quantity' => $quantity,
            'type' => $quantity > 0 ? 'add': 'subtract',
            'to_warehouse_id' => $warehouseId,
            'purchase_price_id' => $purchasePriceId,
            'stockable_id' => $this->id,
            'stockable_type' => self::class,
        ]);
    }

    public function getLowStockThresholdAttribute(): int
    {
        return 10; // Próg niskiego stanu magazynowego
    }

    public function isLowStock(): bool
    {
        return $this->stock() < $this->low_stock_threshold;
    }

    public function notifyLowStock(): void
    {
        if ($this->isLowStock()) {
            // Wyślij powiadomienie o niskim stanie magazynowym
            // Można użyć powiadomień Laravel (Notifications) do wysyłania powiadomień email, SMS itp.
        }
    }

    public function updateAveragePurchasePrice(): void
    {
        $purchasePricesTable = config('stock.tables.purchase_prices');
        $mutationsTable = config('stock.tables.mutations');

        $averagePrice = $this->stockMutations()
            ->join($purchasePricesTable, "$mutationsTable.purchase_price_id", '=', "$purchasePricesTable.id")
            ->where("$mutationsTable.stockable_type", $this->getMorphClass())
            ->where("$mutationsTable.stockable_id", $this->id)
            ->avg("$purchasePricesTable.price");

        $this->average_purchase_price = $averagePrice;
        $this->save();
    }

    public function batchIncreaseStock($items): void
    {
        foreach ($items as $item) {
            $this->increaseStock($item['quantity'], $item['to_warehouse_id'], $item['purchase_price_id'] ?? null);
        }
    }

    /**
     * @throws \Exception
     */
    public function batchDecreaseStock($items): void
    {
        $order = config('stock.inventory_method', 'FIFO');

        foreach ($items as $item) {
            $this->decreaseStock($item['quantity'], $item['to_warehouse_id'], $order);
        }
    }

    /**
     * @throws \Exception
     */
    public function batchTransferStock($items, $toWarehouseId): void
    {
        $order = config('stock.inventory_method', 'FIFO');

        foreach ($items as $item) {
            $this->transferStock($toWarehouseId, $item['quantity'], $order);
        }
    }

    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'stockable');
    }
}
