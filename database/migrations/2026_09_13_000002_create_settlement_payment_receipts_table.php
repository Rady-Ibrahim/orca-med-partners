<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('settlement_payment_id')->unique()->constrained('settlement_payments')->restrictOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_payment_receipts');
    }
};