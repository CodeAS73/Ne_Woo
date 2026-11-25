<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_widgets', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedBigInteger('stock_version')->default(0);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('stock_status')->default('legacy');
            $table->timestamp('stock_reserved_until')->nullable();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_widget_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->unsignedInteger('before_quantity');
            $table->unsignedInteger('after_quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['stock_status', 'stock_reserved_until']));
        Schema::table('product_widgets', fn (Blueprint $table) => $table->dropColumn(['stock_quantity', 'stock_version']));
    }
};
