<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 15, 2)->after('user_id');
            $table->enum('status', ['pending','paid','shipped','delivered','cancelled'])->default('pending')->after('total_amount');
            $table->string('payment_method')->nullable()->after('status');
            $table->text('shipping_address')->after('payment_method');
        });
    }
    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['user_id','total_amount','status','payment_method','shipping_address']);
        });
    }
};*/
return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['pending','paid','shipped','delivered','cancelled'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->text('shipping_address');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
