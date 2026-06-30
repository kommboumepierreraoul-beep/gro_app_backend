<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->decimal('weight', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->json('images')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft','active','out_of_stock'])->default('active');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('products');
    }
};
/*return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->decimal('weight', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->json('images')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft','active','out_of_stock'])->default('active');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};*/
