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
        Schema::create('fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', ['deposit', 'withdraw']);
            $table->integer('amount')->default(0);
            $table->string('razorpay_order_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('reference_id')->nullable(); //eg. $lifeline->id
            $table->string('reference_type')->nullable(); //eg. Lifeline::class
            $table->enum('approved_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_transactions');
    }
};
