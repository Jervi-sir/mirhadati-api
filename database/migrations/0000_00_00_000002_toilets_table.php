<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* 2) Categories */
        Schema::create('toilet_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('icon')->nullable();
            $table->string('en', 100)->nullable();
            $table->string('fr', 100)->nullable();
            $table->string('ar', 100)->nullable();

            $table->timestamps();
        });

        /* 3) Toilets */
        Schema::create('toilets', function (Blueprint $table) {
            $table->id();

            // Ownership (host)
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            // Category
            $table->foreignId('toilet_category_id')->constrained('toilet_categories')->restrictOnDelete();

            // Identity
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('phone_numbers')->nullable();  // [""]

            // Geo (WGS84 / SRID 4326)
            $table->decimal('lat', 9, 6);  // e.g. 36.753769
            $table->decimal('lng', 9, 6);  // e.g. 3.058756

            $table->string('address_line', 180);
            // REPLACED city with wilaya_id
            $table->foreignId('wilaya_id')->nullable()->constrained('wilayas')->restrictOnDelete();
            $table->string('place_hint', 120)->nullable();

            // Access / capacity
            $table->enum('access_method', ['public', 'code', 'staff', 'key', 'app'])->default('public');
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('is_unisex')->default(true);

            // Amenities & Rules (simple JSON for MVP)
            $table->json('amenities')->nullable();  // ["paper","soap","bidet"]
            $table->json('rules')->nullable();      // ["no_smoking","for_customers_only"]

            // Pricing (offline only)
            $table->boolean('is_free')->default(true);
            $table->unsignedInteger('price_cents')->nullable(); // null when free
            $table->enum('pricing_model', ['flat', 'per-visit', 'per-30-min', 'per-60-min'])->nullable();

            // Moderation / aggregates
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('photos_count')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['status', 'wilaya_id']);
            $table->index(['status', 'toilet_category_id']);
            $table->index(['wilaya_id', 'lat', 'lng']);
        });

        /* 4) Photos */
        Schema::create('toilet_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toilet_id')->constrained('toilets')->cascadeOnDelete();
            $table->string('url', 255);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index(['toilet_id', 'is_cover']);
        });

        /* 5) Opening hours (weekly ranges) */
        Schema::create('toilet_open_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toilet_id')->constrained('toilets')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0..6 (Mon..Sun)
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedTinyInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['toilet_id', 'day_of_week', 'sequence'], 'oh_toilet_dow_seq_idx');
        });

        /* 7) Favorites */
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('toilet_id')->constrained('toilets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'toilet_id'], 'favorites_unique');
            $table->index(['user_id', 'created_at']);
        });

        /* 8) Sessions */
        Schema::create('toilet_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toilet_id')->constrained('toilets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('charge_cents')->nullable();
            $table->string('start_method', 20)->nullable(); // 'tap','qr','code'
            $table->string('end_method', 20)->nullable();   // 'tap','auto','qr'
            $table->timestamps();

            $table->index(['toilet_id', 'started_at'], 'sessions_toilet_time_idx');
            $table->index(['user_id', 'started_at'], 'sessions_user_time_idx');
        });

        /* 9) Reviews */
        Schema::create('toilet_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toilet_id')->constrained('toilets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating'); // 1..5 (validate in Request)
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('cleanliness')->nullable();
            $table->unsignedTinyInteger('smell')->nullable();
            $table->unsignedTinyInteger('stock')->nullable();

            $table->timestamps();
            $table->unique(['toilet_id', 'user_id'], 'reviews_once_per_user');
            $table->index(['toilet_id', 'created_at'], 'reviews_toilet_created_idx');
        });

        /* 10) Reports */
        Schema::create('toilet_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toilet_id')->constrained('toilets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('reason', ['closed', 'fake', 'unsafe', 'harassment', 'other']);
            $table->text('details')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['toilet_id', 'resolved_at'], 'reports_toilet_resolved_idx');
        });
    }

    public function down(): void
    {
        // Drop in reverse dependency order
        Schema::dropIfExists('toilet_reports');
        Schema::dropIfExists('toilet_reviews');
        Schema::dropIfExists('toilet_sessions');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('toilet_open_hours');
        Schema::dropIfExists('toilet_photos');
        Schema::dropIfExists('toilets');
        Schema::dropIfExists('toilet_categories');
        Schema::dropIfExists('wilayas');
    }
};
