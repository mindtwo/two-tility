<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('external_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('api_name');
            $table->text('token_data');
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->index(['authenticatable_type', 'authenticatable_id', 'api_name'], 'external_api_tokens_auth_api_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_api_tokens');
    }
};
