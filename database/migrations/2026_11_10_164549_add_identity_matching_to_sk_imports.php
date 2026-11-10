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
        $columns = collect(Schema::getColumns('sk_participants'))->keyBy('name');
        $identifierCollation = $columns->get('official_participant_id')['collation'] ?? null;
        $skCollation = $columns->get('sk_number')['collation'] ?? null;
        Schema::table('sk_imports', function (Blueprint $table): void {
            $table->string('import_mode', 24)->default('upsert');
            $table->unsignedSmallInteger('matching_version')->nullable();
        });
        Schema::table('sk_import_rows', function (Blueprint $table) use ($identifierCollation, $skCollation): void {
            $identifier = $table->string('match_identifier', 100)->nullable();
            $sk = $table->string('match_sk_number', 150)->nullable();
            if (is_string($identifierCollation)) {
                $identifier->collation($identifierCollation);
            }
            if (is_string($skCollation)) {
                $sk->collation($skCollation);
            }
            $table->unsignedInteger('match_sk_order')->nullable();
            // Keep historical preview targets even when their participant is deleted.
            $table->unsignedBigInteger('target_participant_id')->nullable();
            $table->unsignedInteger('target_version')->nullable();
            $table->string('target_digest', 64)->nullable();
            $table->json('changes')->nullable();
            $table->index(['sk_import_id', 'match_identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sk_import_rows', function (Blueprint $table): void {
            $table->dropIndex(['sk_import_id', 'match_identifier']);
            $table->dropColumn(['match_identifier', 'match_sk_number', 'match_sk_order', 'target_participant_id', 'target_version', 'target_digest', 'changes']);
        });
        Schema::table('sk_imports', function (Blueprint $table): void {
            $table->dropColumn(['import_mode', 'matching_version']);
        });
    }
};
