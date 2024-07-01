<?php

namespace Appstract\Stock\Models;

use Appstract\Stock\Enums\StockOperationType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Appstract\Stock\Exceptions\StockException;
use Appstract\Stock\Interfaces\ProductInterface;
use Appstract\Stock\Interfaces\WarehouseInterface as WarehouseInterface;
use Illuminate\Support\Arr;

class StockProduct extends Model implements ProductInterface
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

    public function manageStock(StockOperationData $data): void
    {
        try {
            $data->validate();
            switch ($data->operation) {
                case StockOperationType::purchase:
                    $data->purchasePriceId = $this->createPurchase($data->supplier, $data->price);
                    $this->increaseStock($data);
                    break;
                case StockOperationType::increase:
                    $this->increaseStock($data);
                    break;
                case StockOperationType::decrease:
                    $this->decreaseStock($data);
                    break;
                case StockOperationType::transfer:
                    $this->transferStock($data);
                    break;
                default:
                    throw new \InvalidArgumentException("Invalid operation type");
            }
        } catch (StockException $e) {
            fail($e->getMessage());
        }
    }

    public function getStockAttribute()
    {
        return $this->stock();
    }

    public function stock($date = null, $arguments = []): int
    {
        $date = $this->normalizeDate($date);
        $mutations = $this->filterMutations($date, $arguments);

        return (int)$mutations->sum('quantity');
    }

    public function increaseStock(StockOperationData $data)
    {
        return $this->createStockMutation($data->quantity, $data->warehouseTo, $data->purchasePriceId);
    }

    /**
     * @throws StockException
     */
    public function decreaseStock(StockOperationData $data): void
    {

        if ($this->stock(null, ['warehouse' => $data->warehouseTo]) < $data->quantity) {
            throw new StockException('Not enough stock to decrease.');
        }

        $remainingQuantity = $data->quantity;
        $currentStock = $this->getCurrentStock($data->warehouseTo);

        foreach ($currentStock as $stock) {
            if ($remainingQuantity <= 0) break;

            $decreaseQuantity = min($stock['quantity'], $remainingQuantity);
            $this->createStockMutation(-$decreaseQuantity, $data->warehouseTo, $stock['purchase_price_id'], $data->warehouseFrom);
            $remainingQuantity -= $decreaseQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new StockException('Not enough stock to decrease.');
        }
    }

    public function transferStock(StockOperationData $data): void
    {
        if ($this->stock(null, ['warehouse' => $data->warehouseFrom]) < $data->quantity) {
            throw new StockException('Not enough stock to transfer.');
        }

        $remainingQuantity = $data->quantity;
        $mutations = $this->getTransferMutations($data->warehouseFrom);

        foreach ($mutations as $mutation) {
            if ($remainingQuantity <= 0) break;

            $transferQuantity = min($mutation->quantity, $remainingQuantity);
            $this->createStockMutation($transferQuantity, $data->warehouseTo, $mutation->purchase_price_id, $data->warehouseFrom);
            $this->createStockMutation(-$transferQuantity, $data->warehouseFrom, $mutation->purchase_price_id);
            $remainingQuantity -= $transferQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new StockException('Not enough stock to transfer.');
        }
    }

    public function createPurchase(StockSupplier $supplier, $price): int
    {
        $purchasePriceClass = config('stock.models.purchase_price');
        $purchasePrice = $purchasePriceClass::create([
            'product_id' => $this->id,
            'supplier_id' => $supplier->id,
            'price' => $price,
        ]);
        return $purchasePrice->id;
    }

    protected function createStockMutation($quantity, WarehouseInterface $warehouse_to, $purchasePriceId = null, ?WarehouseInterface $warehouse_from = null)
    {
        $insertArray = [
            'quantity' => $quantity,
            'type' => $this->determineMutationType($quantity, $warehouse_from),
            'to_warehouse_id' => $warehouse_to->getId(),
            'purchase_price_id' => $purchasePriceId,
            'stockable_id' => $this->id,
            'stockable_type' => self::class,
        ];

        if ($warehouse_from) {
            $insertArray['from_warehouse_id'] = $warehouse_from->getId();
        }

        return $this->stockMutations()->create($insertArray);
    }

    public function getLowStockThresholdAttribute(): int
    {
        return 0;
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
            //@todo implement this method
            //$this->increaseStock($item['quantity'], $item['to_warehouse_id'], $item['purchase_price_id'] ?? null);
        }
    }

    /**
     * @throws StockException
     */
    public function batchDecreaseStock($items): void
    {
        foreach ($items as $item) {
            //@todo implement this method
            //$this->decreaseStock($item['quantity'], $item['to_warehouse_id']);
        }
    }

    /**
     * @throws StockException
     */
    public function batchTransferStock($items, WarehouseInterface $warehouse_to): void
    {
        foreach ($items as $item) {
            $this->transferStock($item['quantity'], $warehouse_to, $item['from_warehouse_id']);
        }
    }

    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'stockable');
    }

    private function normalizeDate($date)
    {
        $date = $date ?: Carbon::now();

        if (!$date instanceof \DateTimeInterface) {
            $date = Carbon::create($date);
        }

        return $date;
    }

    private function filterMutations($date, $arguments)
    {
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

        return $mutations;
    }

    private function getCurrentStock(WarehouseInterface $warehouse)
    {
        $order = config('stock.inventory_method', 'FIFO');
        $currentStock = $warehouse->getProductStockPrices($this);

        if ($order === 'LIFO') {
            $currentStock = array_reverse($currentStock);
        }

        return $currentStock;
    }

    private function getTransferMutations(WarehouseInterface $warehouse_from)
    {
        $order = config('stock.inventory_method', 'FIFO');

        return $this->stockMutations()
            ->where('quantity', '>', 0)
            ->where('to_warehouse_id', $warehouse_from->getId())
            ->orderBy('created_at', $order === 'FIFO' ? 'asc' : 'desc')
            ->get();
    }

    private function determineMutationType($quantity, ?WarehouseInterface $warehouse_from)
    {
        if ($warehouse_from) {
            return 'transfer';
        }

        return $quantity > 0 ? 'add' : 'subtract';
    }
}
