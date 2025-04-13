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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->integer('node_id');
            $table->string('title');
            $table->text('description');
            $table->string('banner_image')->nullable();
            $table->json('quizContents');
            $table->integer('spot_limit');
            $table->integer('entry_fees');
            $table->integer('prize_money');
            $table->enum('is_active', [0, 1])->default(1);
            $table->unsignedBigInteger('start_time');
            $table->unsignedBigInteger('end_time');
            $table->integer('quiz_timer')->default(30);
            $table->integer('winners')->default(1);
            $table->integer('totalQuestion')->default(0);
            $table->unsignedBigInteger('quiz_over_at')->default(0);
            $table->boolean('is_prize_distributed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * quizContents contains questions with corresponding options
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
