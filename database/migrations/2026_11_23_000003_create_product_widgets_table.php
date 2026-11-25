<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_widgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('slug')->unique();
            $table->string('name', 120);
            $table->text('description');
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('offer_minor')->nullable();
            $table->timestamp('offer_ends_at')->nullable();
            $table->json('photos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_widgets');
    }
};
