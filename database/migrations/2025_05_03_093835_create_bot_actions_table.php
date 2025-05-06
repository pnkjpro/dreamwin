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
        Schema::create('bot_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained('quizzes');
            $table->foreignId('quiz_variant_id')->constrained('quiz_variants');
            $table->integer('question_attempts')->nullable();
            $table->integer('rank')->nullable();
            $table->unsignedBigInteger('duration')->nullable()->comment("duration the bot will take to complete the quiz");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_actions');
    }
};
