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
            match ($data->operation) {
                StockOperationType::purchase => $this->createPurchase($data),
                StockOperationType::increase => $this->increaseStock($data),
                StockOperationType::decrease => $this->decreaseStock($data),
                StockOperationType::transfer => $this->transferStock($data),
            };
        } catch (StockException $e) {
            fail($e->getMessage());
        }
    }

    protected function increaseStock(StockOperationData $data): void
    {
        $this->createStockMutation($data);
    }

    /**
     * @throws StockException
     */
    protected function decreaseStock(StockOperationData $data): void
    {

        if ($this->stock(null, ['warehouse' => $data->warehouseTo]) < $data->quantity) {
            throw new StockException('Not enough stock to decrease.');
        }

        $remainingQuantity = $data->quantity;
        $currentStock = $this->getCurrentStock($data->warehouseTo);

        foreach ($currentStock as $stock) {
            if ($remainingQuantity <= 0) break;

            $decreaseQuantity = min($stock['quantity'], $remainingQuantity);
            $data->quantity = -$decreaseQuantity;
            $data->purchasePriceId = $stock['purchase_price_id'];
            $this->createStockMutation($data);
            $remainingQuantity -= $decreaseQuantity;
        }

        if ($remainingQuantity > 0) {
            throw new StockException('Not enough stock to decrease.');
        }
    }

    protected function transferStock(StockOperationData $data): void
    {
        if ($this->stock(null, ['warehouse' => $data->warehouseFrom]) < $data->quantity) {
            throw new StockException('Not enough stock to transfer.');
        }

        $remainingQuantity = $data->quantity;
        $mutations = $this->getTransferMutations($data->warehouseFrom);

        foreach ($mutations as $mutation) {
            if ($remainingQuantity <= 0) break;

            $transferQuantity = min($mutation->quantity, $remainingQuantity);

            $increaseData = clone($data);
            $increaseData->quantity = $transferQuantity;
            $increaseData->purchasePriceId = $mutation->purchase_price_id;
            $increaseData->warehouseTo = $data->warehouseTo;
            $increaseData->warehouseFrom = $data->warehouseFrom;
            $this->createStockMutation($increaseData);

            $decreaseData = clone($data);
            $decreaseData->quantity = -$transferQuantity;
            $decreaseData->purchasePriceId = $mutation->purchase_price_id;
            $decreaseData->warehouseTo = $data->warehouseFrom;
            $decreaseData->warehouseFrom = $data->warehouseTo;
            $this->createStockMutation($decreaseData);

            $remainingQuantity -= $transferQuantity;
        }
    }

    protected function createPurchase(StockOperationData $data): void
    {
        $purchasePriceClass = config('stock.models.purchase_price');
        $purchasePrice = $purchasePriceClass::create([
            'product_id' => $this->id,
            'supplier_id' => $data->supplier->id,
            'price' => $data->price,
        ]);
        $data->purchasePriceId = $purchasePrice->id;
        $this->increaseStock($data);
        $this->updateAveragePurchasePrice();
    }

    protected function createStockMutation(StockOperationData $data): void
    {
        $insertArray = [
            'quantity' => $data->quantity,
            'type' => $this->determineMutationType($data->quantity, $data->warehouseFrom),
            'to_warehouse_id' => $data->warehouseTo->getId(),
            'stockable_id' => $this->id,
            'stockable_type' => self::class,
        ];

        if($data->purchasePriceId) {
            $insertArray['purchase_price_id'] = $data->purchasePriceId;
        }

        if ($data->warehouseFrom) {
            $insertArray['from_warehouse_id'] = $data->warehouseFrom->getId();
        }

        $this->stockMutations()->create($insertArray);
    }

    public function getStockAttribute(): int
    {
        return $this->stock();
    }

    public function stock($date = null, $arguments = []): int
    {
        $date = $this->normalizeDate($date);
        $mutations = $this->filterMutations($date, $arguments);

        return (int)$mutations->sum('quantity');
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

    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'stockable');
    }

    private function normalizeDate($date): Carbon
    {
        if ($date instanceof \DateTimeInterface) {
            return Carbon::instance($date);
        }
        return Carbon::parse($date ?: Carbon::now());
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

    private function determineMutationType($quantity, ?WarehouseInterface $warehouse_from): string
    {
        if ($warehouse_from) {
            return 'transfer';
        }

        return $quantity > 0 ? 'add' : 'subtract';
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeWhereInStock($query, WarehouseInterface $warehouse = null)
    {
        return $query->where(function ($query) use ($warehouse) {
            return $query->whereHas('stockMutations', function ($query) use ($warehouse) {
                return $query
                    ->select('stockable_id')
                    ->when($warehouse, function ($query) use ($warehouse) {
                        return $query
                            ->where('warehouse_type', $warehouse->getMorphClass())
                            ->where('warehouse_id', $warehouse->getKey());
                    })
                    ->groupBy('stockable_id')
                    ->havingRaw('SUM(quantity) > 0');
            });
        });
    }

    public function scopeWhereOutOfStock($query, WarehouseInterface $warehouse = null)
    {
        return $query->where(function ($query) use ($warehouse) {
            return $query->whereHas('stockMutations', function ($query) use ($warehouse) {
                return $query->select('stockable_id')
                    ->when($warehouse, function ($query) use ($warehouse) {
                        return $query
                            ->where('warehouse_type', $warehouse->getMorphClass())
                            ->where('warehouse_id', $warehouse->getKey());
                    })
                    ->groupBy('stockable_id')
                    ->havingRaw('SUM(quantity) <= 0');
            })->orWhereDoesntHave('stockMutations');
        });
    }

}
