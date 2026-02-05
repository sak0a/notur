<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notur_migrations')) {
            return;
        }

        Schema::create('notur_migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id');
            $table->string('migration');
            $table->integer('batch');

            $table->index('extension_id');
            $table->unique(['extension_id', 'migration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notur_migrations');
    }
};
