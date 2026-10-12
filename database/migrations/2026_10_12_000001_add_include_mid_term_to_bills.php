<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default false: Mid term bills are not wanted unless a bill asks for them.
        Schema::table('bills', function (Blueprint $table) {
            $table->boolean('include_mid_term')->default(false)->after('period_days');
        });

        // Remove Mid term bills that were fetched before this option existed (only unpaid ones).
        DB::table('bill_items')->where('term', 'mid')->whereNull('paid_at')->delete();
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('include_mid_term');
        });
    }
};
