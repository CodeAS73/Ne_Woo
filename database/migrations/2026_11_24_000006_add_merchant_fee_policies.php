<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('can_manage_fee_tiers')->default(false));
        Schema::create('merchant_fee_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('base_rate_bps');
            $table->json('tiers');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('merchant_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_policy_id')->nullable()->constrained('merchant_fee_policies')->restrictOnDelete();
        });
        DB::table('merchant_fee_settings')->insert(['id'=>1]);
        Schema::table('merchant_order_finances', fn (Blueprint $table) => $table->string('fee_rule')->nullable());
    }

    public function down(): void
    {
        Schema::table('merchant_order_finances', fn (Blueprint $table) => $table->dropColumn('fee_rule'));
        Schema::dropIfExists('merchant_fee_settings');
        Schema::dropIfExists('merchant_fee_policies');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_manage_fee_tiers'));
    }
};
