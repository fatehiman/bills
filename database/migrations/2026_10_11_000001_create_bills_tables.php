<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which provider serves which bill type, in which order (fallback chain).
        Schema::create('provider_bill_types', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('bill_type', 40);
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['provider', 'bill_type']);
        });

        // Per-provider settings (for example the API token). Values are stored encrypted.
        Schema::create('provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('name', 60);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'name']);
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('bill_type', 40);
            $table->string('identifier', 60);
            $table->string('provider', 40);
            $table->unsignedSmallInteger('period_days')->default(30); // 0 = manual only
            $table->timestamp('next_fetch_at')->nullable()->index();
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_status', 20)->nullable(); // ok, no_bill, error
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'bill_type']);
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('bill_no', 40);
            $table->string('payment_no', 40);
            $table->unsignedBigInteger('amount')->default(0); // Rial
            $table->string('term', 20)->nullable(); // final, mid
            $table->string('provider', 40)->nullable();
            $table->json('extra')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['bill_id', 'bill_no', 'payment_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('provider_settings');
        Schema::dropIfExists('provider_bill_types');
    }
};
