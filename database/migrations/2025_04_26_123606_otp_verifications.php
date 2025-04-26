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
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->integer('otp')->comment('its a 6 digit otp');
            $table->unsignedBigInteger('valid_on')->nullable();
            $table->unsignedBigInteger('validated_at')->nullable();
            $table->boolean('is_verified')->default(0);
            $table->boolean('is_registered')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
