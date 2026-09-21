<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_projection_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('participant_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('period_type');
            $table->unsignedInteger('period_value');
            $table->boolean('is_compounded')->default(false);
            $table->decimal('expected_net_profit', 15, 2);
            $table->decimal('expected_total_balance', 15, 2);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['period_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_projection_logs');
    }
};
