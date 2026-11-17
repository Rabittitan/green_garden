<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bảng người dùng
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // 2. Bảng danh mục
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // 3. Bảng cây cảnh
        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('categories');
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->string('light');
            $table->string('element');
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 4. Bảng đơn hàng
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users');
            $table->string('customer_name');
            $table->string('phone');
            $table->string('address');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('total');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // 5. Bảng chi tiết đơn hàng
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();
            $table->foreignId('plant_id')
                  ->constrained('plants');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('price');
            $table->timestamps();
        });

        // 6. Bảng cache
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // 7. Bảng jobs
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('plants');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('jobs');
    }
};
