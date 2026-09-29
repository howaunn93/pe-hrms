<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_entitlement_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('leave_entitlement_id');
            $table->decimal('assigned_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->date('assigned_at')->nullable();
            $table->date('available_at')->nullable();
            $table->date('expired_at')->nullable();
            $table->boolean('is_carry_forward')->default(0);
            $table->boolean('is_prorated')->default(0);
            $table->boolean('is_manual')->default(0);
            $table->boolean('is_active')->default(1);
            $table->string('created_by', 350);
            $table->dateTime('created_at', 6);
            $table->string('updated_by', 350);
            $table->dateTime('updated_at', 6);

            // index
            $table->index('leave_entitlement_id');
            $table->index('available_at');
            $table->index('expired_at');

            // foreign key
            $table->foreign('leave_entitlement_id')->references('id')->on('leave_entitlements')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_logs');
    }
};
