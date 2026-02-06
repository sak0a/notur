<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notur_settings')) {
            return;
        }

        Schema::create('notur_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['extension_id', 'key']);
            $table->index('extension_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notur_settings');
    }
};
