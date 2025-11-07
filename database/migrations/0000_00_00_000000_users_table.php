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
        /* 1) Wilayas (Algeria provinces) */
        Schema::create('wilayas', function (Blueprint $table) {
            $table->id(); // big integer PK for easy FK usage
            $table->string('code', 100)->unique();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('en', 100)->nullable();
            $table->string('fr', 100)->nullable();
            $table->string('ar', 100)->nullable();

            $table->decimal('center_lat', 9, 6)->nullable();
            $table->decimal('center_lng', 9, 6)->nullable();
            $table->unsignedSmallInteger('default_radius_km')->nullable(); // e.g., 25
            // Optional quick bbox for pre-filter (speeds up)
            $table->decimal('min_lat', 9, 6)->nullable();
            $table->decimal('max_lat', 9, 6)->nullable();
            $table->decimal('min_lng', 9, 6)->nullable();
            $table->decimal('max_lng', 9, 6)->nullable();

            $table->timestamps();
            $table->index(['code', 'number']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->string('phone', 40)->unique(); // required, unique
            $table->string('email')->nullable()->unique(); // nullable+unique (MySQL allows multiple NULLs)

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('password_plain_text')->nullable(); // ⚠️ dev-only; remove in prod

            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('wilaya_id')->nullable()->constrained('wilayas')->nullOnDelete();

            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
