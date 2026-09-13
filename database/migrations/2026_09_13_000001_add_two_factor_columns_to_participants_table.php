<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->boolean('two_factor_enabled')->default(false)->after('status');
            $table->timestamp('two_factor_enabled_at')->nullable()->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_enabled', 'two_factor_enabled_at']);
        });
    }
};