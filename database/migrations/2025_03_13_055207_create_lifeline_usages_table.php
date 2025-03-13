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
        Schema::create('lifeline_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lifeline_id')->constrained('lifelines')->onDelete('cascade');
            $table->foreignId('user_response_id')->constrained('user_responses')->onDelete('cascade');
            $table->integer('question_id');
            $table->timestamp('used_at');
            $table->json('result_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lifeline_usages');
    }
};
