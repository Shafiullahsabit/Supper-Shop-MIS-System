<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Client-generated id for offline sales. Unique so a queued sale that is
            // re-sent (retry / double flush) is recognised and never inserted twice.
            $table->uuid('client_uuid')->nullable()->unique()->after('invoice_no');
            // Whether the sale was rung up while the terminal was offline.
            $table->boolean('is_offline')->default(false)->after('status');
            // When the sale actually happened on the device (may differ from sync time).
            $table->timestamp('offline_created_at')->nullable()->after('is_offline');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn(['client_uuid', 'is_offline', 'offline_created_at']);
        });
    }
};
