<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('funds')->where('code', 'management_fund')->doesntExist()) {
            DB::table('funds')->insert([
                'code' => 'management_fund',
                'name' => 'حساب الإدارة',
                'description' => 'حصة الإدارة الشهرية (25%) تُغذى تلقائياً عند اعتماد الأرباح',
                'current_balance' => '0.00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('funds')->where('code', 'management_fund')->delete();
    }
};