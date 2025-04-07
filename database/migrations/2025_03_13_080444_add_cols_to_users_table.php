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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('name');
            $table->string('mobile')->nullable()->after('email_verified_at');
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            $table->integer('funds')->default(0)->after('mobile_verified_at');
            $table->string('upi_id')->nullable()->after('funds');
            $table->boolean('is_admin')->default(0);
            $table->string('refer_code', 20)->nullable()->after('funds');
            $table->foreignId('refer_by')->nullable()->constrained('users', 'id')->onDelete('set null')->after('refer_code');
            $table->boolean('is_reward_given')->default(0)->after('refer_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
