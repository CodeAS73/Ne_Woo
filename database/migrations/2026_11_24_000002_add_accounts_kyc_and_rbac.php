<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        foreach (['system_admin', 'supervisor', 'support', 'merchant', 'buyer'] as $name) {
            \Illuminate\Support\Facades\DB::table('roles')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('account_type')->default('buyer');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('session_version')->default(1);
        });
        Schema::table('product_widgets', fn (Blueprint $table) => $table->foreignId('merchant_user_id')->nullable()->constrained('users')->restrictOnDelete());
        Schema::table('checkout_quotes', fn (Blueprint $table) => $table->foreignId('buyer_user_id')->nullable()->constrained('users')->restrictOnDelete());
        Schema::table('orders', fn (Blueprint $table) => $table->foreignId('buyer_user_id')->nullable()->constrained('users')->restrictOnDelete());
        Schema::create('merchant_kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->text('details');
            $table->text('document_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('user_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('security_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audits');
        Schema::dropIfExists('user_activations');
        Schema::dropIfExists('merchant_kyc');
        foreach (['orders' => 'buyer_user_id', 'checkout_quotes' => 'buyer_user_id', 'product_widgets' => 'merchant_user_id'] as $table => $column) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId($column));
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['account_type', 'is_active', 'session_version']);
        });
        Schema::dropIfExists('roles');
    }
};
