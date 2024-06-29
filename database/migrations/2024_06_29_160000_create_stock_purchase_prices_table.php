<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockPurchasePricesTable extends Migration
{
    public function up()
    {
        Schema::create(config('stock.tables.purchase_prices'), function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
                $table->decimal('price', 8, 2);
                $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_prices');
    }
}
