<?php

namespace Appstract\Stock\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Appstract\Stock\Exceptions\StockException;
use Appstract\Stock\Interfaces\Product as ProductInterface;
use Appstract\Stock\Interfaces\Warehouse as WarehouseInterface;
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

    public function increaseStock($quantity, WarehouseInterface $warehouse, $purchasePriceId = null)
    {
        return $this->createStockMutation($quantity, $warehouse, $purchasePriceId);
    }

    public function decreaseStock($quantity, WarehouseInterface $warehouse_to): void
    {
        if ($this->stock(null, ['warehouse' => $warehouse_to]) < $quantity) {
            throw new StockException('Not enough stock to decrease.');
        }

        $order = config('stock.inventory_method', 'FIFO');

        $remainingQuantity = $quantity;

        // Pobierz aktualny stan magazynowy
        $currentStock = $warehouse_to->getProductStockPrices($this);

        // Sortuj zgodnie z metodą FIFO/LIFO
        if ($order === 'LIFO') {
            $currentStock = array_reverse($currentStock);
        }

        foreach ($currentStock as $stock) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $stock['quantity'];
            $decreaseQuantity = min($availableQuantity, $remainingQuantity);

            $this->createStockMutation(-$decreaseQuantity, $warehouse_to, $stock['purchase_price_id']);

            $remainingQuantity -= $decreaseQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new StockException('Not enough stock to decrease.');
        }
    }

    public function transferStock(WarehouseInterface $warehouse_from, WarehouseInterface $warehouse_to, $quantity): void
    {

        if ($this->stock(null, ['warehouse' => $warehouse_from]) < $quantity) {
            throw new StockException('Not enough stock to transfer.');
        }

        $order = config('stock.inventory_method', 'FIFO');
        $remainingQuantity = $quantity;

        $mutations = $this->stockMutations()
            ->where('quantity', '>', 0)
            ->where('to_warehouse_id', $warehouse_from->getId())
            ->orderBy('created_at', $order === 'FIFO' ? 'asc' : 'desc')
            ->get();

        foreach ($mutations as $mutation) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $availableQuantity = $mutation->quantity;
            $transferQuantity = min($availableQuantity, $remainingQuantity);

            // Twórz nową mutację dla magazynu docelowego
            $this->createStockMutation($transferQuantity, $warehouse_to, $mutation->purchase_price_id);

            // Twórz nową mutację dla zmniejszenia w magazynie źródłowym
            $this->createStockMutation(-$transferQuantity, $warehouse_from, $mutation->purchase_price_id);

            $remainingQuantity -= $transferQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new StockException('Not enough stock to transfer.');
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

        $this->increaseStock($attributes['quantity'], $attributes['to_warehouse'], $purchasePrice->id);

        return $purchasePrice;
    }

    protected function createStockMutation($quantity, WarehouseInterface $warehouse_to, $purchasePriceId = null)
    {
        return $this->stockMutations()->create([
            'quantity' => $quantity,
            'type' => $quantity > 0 ? 'add' : 'subtract',
            'to_warehouse_id' => $warehouse_to->getId(),
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
     * @throws StockException
     */
    public function batchDecreaseStock($items): void
    {
        $order = config('stock.inventory_method', 'FIFO');

        foreach ($items as $item) {
            $this->decreaseStock($item['quantity'], $item['to_warehouse_id'], $order);
        }
    }

    /**
     * @throws StockException
     */
    public function batchTransferStock($items, WarehouseInterface $warehouse_to): void
    {
        $order = config('stock.inventory_method', 'FIFO');

        foreach ($items as $item) {
            $this->transferStock($warehouse_to, $item['quantity'], $order);
        }
    }

    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'stockable');
    }
}
