<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->restrictOnDelete();
            $table->foreignId('settlement_payment_id')->nullable()->constrained('settlement_payments')->nullOnDelete();
            $table->string('type', 20);
            $table->string('direction', 20);
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('reference')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['settlement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_adjustments');
    }
};
