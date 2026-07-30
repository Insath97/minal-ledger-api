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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('cheque_id')->nullable()->constrained('cheques')->onDelete('set null');
            $table->decimal('total_amount', 15, 2);
            $table->enum('payment_method', ['cash', 'credit_card', 'bank_transfer', 'cheque'])->default('cash');
            $table->date('payment_date');
            $table->string('proof_image_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Performance indexes
            $table->index(['payment_method', 'payment_date'], 'payments_method_date_index');
            $table->index('payment_date', 'payments_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
