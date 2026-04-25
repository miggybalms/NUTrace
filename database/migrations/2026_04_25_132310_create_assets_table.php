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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('Asset_code', 50)->unique();
            $table->string('Asset_name', 150);
            $table->enum('Category', [
                'Furnitures and Fixtures',
                'General and Office Equipment',
                'Info and Equipment',
                'laboratory Apparatus and equipment',
                'library books',
                'Motor vehicles',
                'P.E Equipment',
                'Low value Asset'
            ]);
            $table->enum('Condition', ['New', 'Excellent', 'Good', 'Fair', 'Poor']);
            $table->enum('Lifecycle_Status', [
                'Acquired', 'Active', 'For Repair', 'Pullout', 'Disposal'
            ])->default('Acquired');
            $table->date('accusion_date')->nullable();
            $table->decimal('accusion_cost', 12, 2)->nullable();
            $table->decimal('purchase_Price', 10, 2)->nullable();
            $table->integer('warranty_months')->nullable();
            $table->string('supplier')->nullable();
            $table->string('model')->nullable();
            $table->string('manufacture')->nullable();
            $table->string('serial_Number')->nullable();
            $table->string('asset_location')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
