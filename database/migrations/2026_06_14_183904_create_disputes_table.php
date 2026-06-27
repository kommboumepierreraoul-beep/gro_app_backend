// database/migrations/xxxx_xx_xx_create_disputes_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->enum('reason', ['not_received', 'damaged', 'wrong_product', 'other']);
            $table->text('description');
            $table->json('attachments')->nullable();
            $table->enum('status', ['pending', 'investigating', 'resolved', 'refunded', 'replaced', 'dismissed'])->default('pending');
            $table->text('seller_response')->nullable();
            $table->json('seller_attachments')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->text('admin_notes')->nullable();
            $table->enum('resolution', ['refund', 'partial_refund', 'replace', 'dismissed'])->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('disputes');
    }
};