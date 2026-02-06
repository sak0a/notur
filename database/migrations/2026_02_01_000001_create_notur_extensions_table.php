<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notur_extensions')) {
            return;
        }

        Schema::create('notur_extensions', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id')->unique();
            $table->string('name');
            $table->string('version');
            $table->boolean('enabled')->default(true);
            $table->json('manifest')->nullable();
            $table->timestamps();

            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notur_extensions');
    }
};
