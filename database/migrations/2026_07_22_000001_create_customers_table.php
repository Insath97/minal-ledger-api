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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->enum('id_type', ['nic', 'driving', 'passport', 'other'])->nullable();
            $table->string('id_number', 50)->nullable();
            $table->string('phone', 20);
            $table->string('phone_secondary', 20)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('profile_image')->nullable();
            $table->string('nic_image')->nullable();
            $table->decimal('outstanding_balance', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique index for the combination of id_type and id_number when they are not null
            $table->unique(['id_type', 'id_number'], 'customers_id_type_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
