<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notur_extensions')) {
            return;
        }

        Schema::table('notur_extensions', function (Blueprint $table): void {
            if (!Schema::hasColumn('notur_extensions', 'source')) {
                $table->string('source', 20)->default('unknown')->after('manifest');
            }
            if (!Schema::hasColumn('notur_extensions', 'pushed_via_key_id')) {
                $table->unsignedBigInteger('pushed_via_key_id')->nullable()->after('source');
            }
            if (!Schema::hasColumn('notur_extensions', 'last_pushed_at')) {
                $table->timestamp('last_pushed_at')->nullable()->after('pushed_via_key_id');
            }
            if (!Schema::hasColumn('notur_extensions', 'last_push_error')) {
                $table->text('last_push_error')->nullable()->after('last_pushed_at');
            }
            if (!Schema::hasColumn('notur_extensions', 'package_checksum')) {
                $table->string('package_checksum', 64)->nullable()->after('last_push_error');
            }
            if (!Schema::hasColumn('notur_extensions', 'package_size')) {
                $table->unsignedBigInteger('package_size')->nullable()->after('package_checksum');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notur_extensions')) {
            return;
        }

        Schema::table('notur_extensions', function (Blueprint $table): void {
            foreach (['source', 'pushed_via_key_id', 'last_pushed_at', 'last_push_error', 'package_checksum', 'package_size'] as $col) {
                if (Schema::hasColumn('notur_extensions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
