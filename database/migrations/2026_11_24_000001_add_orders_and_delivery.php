<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_widgets', function (Blueprint $table) {
            $table->string('creator_hash', 64)->nullable()->index();
            $table->boolean('delivery_enabled')->default(false);
            $table->boolean('pickup_enabled')->default(true);
            $table->unsignedBigInteger('delivery_fee_minor')->default(0);
            $table->text('delivery_areas')->nullable();
            $table->string('delivery_estimate')->nullable();
            $table->text('pickup_details')->nullable();
        });
        Schema::create('checkout_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('product_widget_id')->constrained()->restrictOnDelete();
            $table->string('buyer_hash', 64);
            $table->text('details');
            $table->json('pricing');
            $table->string('pricing_fingerprint', 64);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->uuid('checkout_quote_id')->unique();
            $table->foreign('checkout_quote_id')->references('id')->on('checkout_quotes')->restrictOnDelete();
            $table->foreignId('product_widget_id')->constrained()->restrictOnDelete();
            $table->string('buyer_hash', 64)->index();
            $table->string('product_name', 120);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('delivery_minor');
            $table->unsignedBigInteger('buyer_fee_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('currency', 3)->default('KES');
            $table->string('fulfillment_method');
            $table->text('buyer_details');
            $table->text('fulfillment_details');
            $table->string('notification_channel');
            $table->string('status')->default('awaiting_payment');
            $table->string('payment_status')->default('unpaid');
            $table->timestamp('consented_at');
            $table->timestamps();
        });
        Schema::create('merchant_order_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('lead_fee_basis_points');
            $table->unsignedBigInteger('lead_fee_minor');
            $table->unsignedBigInteger('merchant_receivable_minor');
            $table->string('fee_policy_version');
            $table->string('settlement_status')->default('not_funded');
            $table->timestamps();
        });
        Schema::create('payment_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('provider')->default('kcb_buni');
            $table->string('status')->default('not_initiated');
            $table->unsignedBigInteger('amount_minor');
            $table->text('phone');
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_collections');
        Schema::dropIfExists('merchant_order_finances');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('checkout_quotes');
        Schema::table('product_widgets', fn (Blueprint $table) => $table->dropColumn(['creator_hash', 'delivery_enabled', 'pickup_enabled', 'delivery_fee_minor', 'delivery_areas', 'delivery_estimate', 'pickup_details']));
    }
};
