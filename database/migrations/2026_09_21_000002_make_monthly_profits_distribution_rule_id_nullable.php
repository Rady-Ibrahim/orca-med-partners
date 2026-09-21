<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_profits', function (Blueprint $table): void {
            $table->foreignId('distribution_rule_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('monthly_profits', function (Blueprint $table): void {
            $table->foreignId('distribution_rule_id')->nullable(false)->change();
        });
    }
};
