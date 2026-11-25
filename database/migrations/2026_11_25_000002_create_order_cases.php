<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('status')->default('open');
            $table->unsignedBigInteger('refund_minor')->default(0);
            $table->unsignedInteger('restock_quantity')->default(0);
            $table->string('refund_reference')->nullable()->unique();
            $table->index(['order_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cases');
    }
};
