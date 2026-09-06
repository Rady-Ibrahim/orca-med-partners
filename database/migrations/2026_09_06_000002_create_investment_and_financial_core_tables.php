<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('invested_at');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['participant_id', 'status']);
            $table->index('invested_at');
        });

        Schema::create('capital_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_capital', 15, 2)->default(0);
            $table->string('status')->default('final');
            $table->json('snapshot_metadata')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'month']);
            $table->index('snapshot_date');
        });

        Schema::create('capital_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_snapshot_id')->constrained('capital_snapshots')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->decimal('participant_capital_snapshot', 15, 2);
            $table->decimal('participant_ratio_snapshot', 8, 4)->default(0.0000);
            $table->json('calculation_metadata')->nullable();
            $table->timestamps();

            $table->unique(['capital_snapshot_id', 'participant_id'], 'capital_snapshot_participant_unique');
            $table->index(['participant_id', 'capital_snapshot_id']);
        });

        Schema::create('distribution_rules', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('management_fee_rate', 8, 4);
            $table->decimal('depreciation_fund_rate', 8, 4);
            $table->decimal('growth_fund_rate', 8, 4);
            $table->decimal('incentive_fund_rate', 8, 4);
            $table->decimal('distributed_share_rate', 8, 4);
            $table->string('status')->default('active');
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['effective_from', 'effective_to']);
            $table->index('status');
        });

        Schema::create('monthly_profits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_snapshot_id')->constrained('capital_snapshots')->cascadeOnDelete();
            $table->foreignId('distribution_rule_id')->constrained('distribution_rules')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('monthly_profits')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('management_amount', 15, 2)->default(0);
            $table->decimal('depreciation_amount', 15, 2)->default(0);
            $table->decimal('growth_amount', 15, 2)->default(0);
            $table->decimal('incentive_amount', 15, 2)->default(0);
            $table->decimal('distributed_amount', 15, 2)->default(0);
            $table->decimal('rounding_delta_adjustment', 15, 2)->default(0);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'version'], 'monthly_profit_version_unique');
            $table->index(['year', 'month', 'status']);
            $table->index(['capital_snapshot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_profits');
        Schema::dropIfExists('distribution_rules');
        Schema::dropIfExists('capital_snapshot_items');
        Schema::dropIfExists('capital_snapshots');
        Schema::dropIfExists('investments');
    }
};
