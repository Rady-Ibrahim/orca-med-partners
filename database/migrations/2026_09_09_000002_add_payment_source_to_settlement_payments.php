<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_payments', function (Blueprint $table): void {
            $table->string('payment_source', 20)->default('other')->after('payment_method');
            $table->index('payment_source');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_payments', function (Blueprint $table): void {
            $table->dropIndex(['payment_source']);
            $table->dropColumn('payment_source');
        });
    }
};
