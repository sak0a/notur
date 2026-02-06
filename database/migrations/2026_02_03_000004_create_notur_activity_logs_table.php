<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notur_activity_logs')) {
            return;
        }

        Schema::create('notur_activity_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('extension_id')->index();
            $table->string('action');
            $table->string('summary')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notur_activity_logs');
    }
};
