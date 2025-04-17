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
        Schema::create('user_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->integer('node_id');
            $table->foreignId('quiz_variant_id')->constrained('quiz_variants')->onDelete('cascade')->nullable();
            $table->integer('score')->default(0);
            $table->unsignedBigInteger('started_at')->default(0);
            $table->unsignedBigInteger('ended_at')->default(0);
            $table->json('responseContents')->nullable();
            $table->enum('status', ['pending', 'joined', 'initiated', 'completed'])->default('pending');
            $table->timestamps();

            $table->index('quiz_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_responses');
    }
};
