<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyStockMutationsTable extends Migration
{
    public function up()
    {
        Schema::table(config('stock.tables.mutations'), function (Blueprint $table) {
            $table->renameColumn('amount', 'quantity');
            $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();

            $table->unsignedBigInteger('product_id')->after('id');
            $table->string('type')->after('amount');
            $table->unsignedBigInteger('from_warehouse_id')->nullable()->after('warehouse_id');
            $table->unsignedBigInteger('to_warehouse_id')->nullable()->after('from_warehouse_id');
            $table->unsignedBigInteger('purchase_price_id')->nullable()->after('to_warehouse_id');

            $table->dropColumn(['warehouse_type']);

            $table->index(['product_id']);
            $table->index(['from_warehouse_id']);
            $table->index(['to_warehouse_id']);
            $table->index(['purchase_price_id']);
        });
    }

    public function down()
    {
        Schema::table(config('stock.tables.mutations'), function (Blueprint $table) {
            $table->renameColumn('quantity', 'amount');
            $table->string('warehouse_type')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable()->change();

            $table->dropColumn(['product_id', 'type', 'from_warehouse_id', 'to_warehouse_id', 'purchase_price_id']);

            $table->dropIndex(['product_id']);
            $table->dropIndex(['from_warehouse_id']);
            $table->dropIndex(['to_warehouse_id']);
            $table->dropIndex(['purchase_price_id']);
        });
    }
}
