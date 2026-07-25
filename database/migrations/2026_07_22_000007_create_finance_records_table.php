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
        Schema::create('finance_records', function (Blueprint $table) {
            $table->id();
            $table->enum('record_type', ['income', 'expense']);
            $table->string('reference_type', 100); // Payment or Expense
            $table->unsignedBigInteger('reference_id');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('record_date');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'finance_ref_index');
            $table->index(['record_type', 'record_date'], 'finance_type_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_records');
    }
};
