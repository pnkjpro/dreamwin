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
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->json('answerContents');
            $table->timestamps();
        });
    }

    /**
     * answerContents contains answers corresponding to quizzes questions's options
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
