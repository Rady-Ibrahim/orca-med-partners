<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_profits', function (Blueprint $table) {
            $table->json('distribution_rule_snapshot')->nullable()->after('distribution_rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_profits', function (Blueprint $table) {
            $table->dropColumn('distribution_rule_snapshot');
        });
    }
};
