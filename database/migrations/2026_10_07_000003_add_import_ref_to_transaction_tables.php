<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fingerprint of the Excel row a transaction was imported from, so re-running
 * `import:tracker` without --fresh never creates duplicates.
 */
return new class extends Migration
{
    private const TABLES = ['purchases', 'distributions', 'remittances', 'expenses'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('import_ref', 64)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique("{$table}_import_ref_unique");
                $blueprint->dropColumn('import_ref');
            });
        }
    }
};
