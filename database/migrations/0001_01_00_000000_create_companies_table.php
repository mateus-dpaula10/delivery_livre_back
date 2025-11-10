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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name')->nullable();
            $table->string('final_name')->nullable();
            $table->string('cnpj')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('number', 10)->nullable();
            $table->string('neighborhood', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('plan')->default('padrao');
            $table->boolean('active')->default(true);
            $table->string('email')->nullable();
            $table->string('category')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            
            $table->boolean('free_shipping')->default(false);
            $table->boolean('first_purchase_discount_store')->default(false);
            $table->tinyInteger('first_purchase_discount_store_value')->nullable();
            $table->boolean('first_purchase_discount_app')->default(false);
            $table->tinyInteger('first_purchase_discount_app_value')->nullable();

            $table->string('logo')->nullable();
            $table->decimal('delivery_fee', 8, 2)->nullable();
            $table->integer('delivery_radius')->nullable();
            $table->json('opening_hours')->nullable();

            $table->string('pix_key')->nullable();
            $table->enum('pix_key_type', ['cpf', 'cnpj', 'email', 'phone', 'random'])->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
