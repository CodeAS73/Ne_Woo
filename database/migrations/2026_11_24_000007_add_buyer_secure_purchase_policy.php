<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('can_manage_buyer_fees')->default(false));
        Schema::create('buyer_fee_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rate_bps');
            $table->unsignedInteger('minimum_minor')->default(3000);
            $table->unsignedInteger('maximum_minor')->default(250000);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('buyer_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_policy_id')->constrained('buyer_fee_policies')->restrictOnDelete();
        });
        $id=DB::table('buyer_fee_policies')->insertGetId(['rate_bps'=>50,'minimum_minor'=>3000,'maximum_minor'=>250000,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('buyer_fee_settings')->insert(['id'=>1,'active_policy_id'=>$id]);
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('buyer_fee_policy_id')->nullable()->constrained('buyer_fee_policies')->restrictOnDelete();
            $table->unsignedInteger('buyer_fee_rate_bps')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_fee_policy_id');
            $table->dropColumn('buyer_fee_rate_bps');
        });
        Schema::dropIfExists('buyer_fee_settings');
        Schema::dropIfExists('buyer_fee_policies');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_manage_buyer_fees'));
    }
};
