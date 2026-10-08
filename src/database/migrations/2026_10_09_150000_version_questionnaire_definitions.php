<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_items', function (Blueprint $table) {
            $table->string('section', 40)->default('academic_behavior')->after('id');
            $table->string('definition_version', 40)->default('draft-v1')->after('section');
            $table->index(['definition_version', 'section', 'is_active'], 'questionnaire_items_version_section_active');
        });

        Schema::table('questionnaire_responses', function (Blueprint $table) {
            $table->string('definition_version', 40)->nullable()->after('student_id');
            $table->index(['student_id', 'definition_version'], 'questionnaire_responses_student_version');
        });

        // The only definitions before this migration are the existing draft-v1 set.
        DB::table('questionnaire_responses')
            ->whereNull('definition_version')
            ->update(['definition_version' => 'draft-v1']);
    }

    public function down(): void
    {
        Schema::table('questionnaire_responses', function (Blueprint $table) {
            $table->dropIndex('questionnaire_responses_student_version');
            $table->dropColumn('definition_version');
        });

        Schema::table('questionnaire_items', function (Blueprint $table) {
            $table->dropIndex('questionnaire_items_version_section_active');
            $table->dropColumn(['section', 'definition_version']);
        });
    }
};
