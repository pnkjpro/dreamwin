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
        Schema::create('lifelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->text('description')->nullable();
            $table->integer('cost');
            $table->string('icon')->nullable();
            $table->string('icon_color')->nullable();
            $table->enum('is_active', [0,1])->default(1);
            $table->integer('cooldown_period')->nullable();
            $table->text('effect_description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lifelines');
    }
};
