<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_completion_links', function (Blueprint $table) {
            $table->string('purpose', 24);
            $table->unsignedBigInteger('subject_id');
            $table->char('token_hash', 64)->unique();
            $table->char('email_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->primary(['purpose', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_completion_links');
    }
};
