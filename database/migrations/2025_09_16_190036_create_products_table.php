<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('name');
            $table->string('image_path')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('cost_price', 10, 2);
            $table->decimal('sell_price', 10, 2);
            $table->text('description');
            $table->integer('rating')->default(1)->comment('Rating from 1-5');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index(['is_active', 'brand']);
            $table->index(['is_active', 'rating']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}