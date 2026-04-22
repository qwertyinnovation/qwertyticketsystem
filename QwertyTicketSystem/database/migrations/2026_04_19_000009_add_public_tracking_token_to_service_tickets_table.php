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
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->string('public_tracking_token')->nullable()->unique()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropUnique(['public_tracking_token']);
            $table->dropColumn('public_tracking_token');
        });
    }
};
