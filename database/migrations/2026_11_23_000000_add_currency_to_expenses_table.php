<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $symbol = DB::table('settings')->where('key', 'currency_symbol')->value('value');
        $legacyCurrency = $symbol === '؋' ? 'AFN' : 'USD';

        Schema::table('expenses', function (Blueprint $table) use ($legacyCurrency): void {
            $table->string('currency', 3)->default($legacyCurrency);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
