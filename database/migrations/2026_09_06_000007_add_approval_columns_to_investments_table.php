<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete()->after('created_by_admin_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_admin_id');
            $table->dropColumn('approved_at');
        });
    }
};