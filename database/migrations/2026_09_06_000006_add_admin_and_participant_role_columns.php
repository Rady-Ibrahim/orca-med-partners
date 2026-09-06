<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('role')->default('employee')->after('status');
            $table->json('permissions')->nullable()->after('role');
            $table->boolean('is_super_admin')->default(false)->after('permissions');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->string('role')->default('participant')->after('status');
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions']);
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions', 'is_super_admin']);
        });
    }
};
