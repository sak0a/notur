<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notur_remote_push_keys')) {
            return;
        }

        Schema::create('notur_remote_push_keys', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('prefix', 16)->index();
            $table->string('token_hash', 64);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notur_remote_push_keys');
    }
};
