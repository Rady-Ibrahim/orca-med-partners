<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['code', 'status']);
        });

        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
            $table->foreignId('monthly_profit_id')->nullable()->constrained('monthly_profits')->nullOnDelete();
            $table->string('transaction_type')->default('adjustment');
            $table->decimal('amount', 15, 2);
            $table->decimal('resulting_balance', 15, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['fund_id', 'created_at']);
            $table->index('transaction_type');
        });

        Schema::create('depreciation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('monthly_profit_id')->nullable()->constrained('monthly_profits')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('rate', 8, 4)->nullable();
            $table->date('transaction_date');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('description');
            $table->text('admin_note')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'month']);
            $table->index(['fund_id', 'transaction_date']);
        });

        Schema::create('participant_profit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_profit_id')->constrained('monthly_profits')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('share_ratio', 8, 4)->default(0.0000);
            $table->string('status')->default('approved');
            $table->timestamps();

            $table->unique(['monthly_profit_id', 'participant_id'], 'monthly_profit_participant_allocation_unique');
            $table->index(['participant_id', 'status']);
        });

        Schema::create('participant_fund_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('funds')->cascadeOnDelete();
            $table->foreignId('monthly_profit_id')->constrained('monthly_profits')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('allocation_type')->default('growth');
            $table->timestamps();

            $table->unique(['fund_id', 'monthly_profit_id', 'participant_id', 'allocation_type'], 'participant_fund_allocation_unique');
            $table->index(['participant_id', 'fund_id']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('settlements')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->decimal('total_distributed_amount', 15, 2)->default(0);
            $table->decimal('participant_profit_share', 15, 2)->default(0);
            $table->decimal('participant_fund_share', 15, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('paid_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('payout_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'version'], 'settlement_version_unique');
            $table->index(['year', 'status']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->decimal('profit_share', 15, 2)->default(0);
            $table->decimal('fund_share', 15, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['settlement_id', 'participant_id'], 'settlement_participant_unique');
            $table->index(['participant_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('participant_fund_allocations');
        Schema::dropIfExists('participant_profit_allocations');
        Schema::dropIfExists('depreciation_notes');
        Schema::dropIfExists('fund_transactions');
        Schema::dropIfExists('funds');
    }
};
