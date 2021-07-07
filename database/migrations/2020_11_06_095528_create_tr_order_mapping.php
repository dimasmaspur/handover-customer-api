<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrOrderMapping extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tr_order_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('member_id');
            $table->foreignId('address_id');
            $table->foreignId('category_id')->default(1); // 1 = Kedai Sayur, 2 = Kedai Bunga, 3 = Kedai Kopi
            $table->string('product_ids');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tr_order_mapping');
    }
}
