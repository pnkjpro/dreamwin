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
        Schema::create('leaderboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quiz_id');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('quiz_variant_id')->nullable();
            $table->unsignedBigInteger('user_response_id');
            $table->integer('score');
            $table->integer('reward')->default(0);
            $table->integer('rank');
            $table->unsignedBigInteger('duration');
            $table->timestamps();
        
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboards');
    }
};
