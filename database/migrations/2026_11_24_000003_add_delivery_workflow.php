<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('merchant_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('fulfillment_status')->default('unstarted');
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->boolean('delivery_issue_open')->default(false);
        });
        DB::table('orders')->orderBy('id')->each(function ($order) {
            $owner = DB::table('product_widgets')->where('id', $order->product_widget_id)->value('merchant_user_id');
            DB::table('orders')->where('id', $order->id)->update(['merchant_user_id' => $owner]);
        });
        Schema::table('merchant_order_finances', function (Blueprint $table) {
            $table->string('review_status')->default('not_ready');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });
        Schema::create('order_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_type');
            $table->string('action');
            $table->string('visibility')->default('shared');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_workflow_events');
        Schema::table('merchant_order_finances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_note']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_user_id');
            $table->dropColumn(['fulfillment_status', 'buyer_confirmed_at', 'delivery_issue_open']);
        });
    }
};
