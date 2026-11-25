<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_widgets', fn (Blueprint $table) => $table->boolean('show_stock_level')->default(true));
        Schema::table('merchant_order_finances', fn (Blueprint $table) => $table->timestamp('release_eligible_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('product_widgets', fn (Blueprint $table) => $table->dropColumn('show_stock_level'));
        Schema::table('merchant_order_finances', fn (Blueprint $table) => $table->dropColumn('release_eligible_at'));
    }
};
