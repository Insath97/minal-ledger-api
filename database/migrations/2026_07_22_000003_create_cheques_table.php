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
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->string('cheque_number', 50);
            $table->string('bank_name', 100);
            $table->date('cheque_date');
            $table->decimal('amount', 15, 2);
            $table->string('cheque_image', 255)->nullable();
            $table->enum('status', ['pending', 'cleared', 'bounced', 'cancelled'])->default('pending');
            $table->date('clearance_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Performance indexes
            $table->index(['status', 'cheque_date'], 'cheques_status_date_index');
            $table->index('cheque_date', 'cheques_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
