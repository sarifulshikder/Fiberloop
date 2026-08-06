<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('churn_score', 5, 4)->nullable();
            $table->boolean('is_high_risk')->default(false);
            $table->boolean('has_anomaly')->default(false);
            $table->decimal('anomaly_score', 10, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['churn_score', 'is_high_risk', 'has_anomaly', 'anomaly_score']);
        });
    }
};
