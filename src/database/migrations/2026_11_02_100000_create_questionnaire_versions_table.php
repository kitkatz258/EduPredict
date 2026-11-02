<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds version records only. Question rows, answers, and submissions stay as they are.
 * definition_version on those tables remains the immutable pin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_versions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('state', 20);
            $table->unsignedTinyInteger('current_slot')->nullable()->unique();
            $table->string('source_code', 40)->nullable();
            $table->text('change_summary')->nullable();
            $table->boolean('panel_approved')->default(false);
            $table->text('approval_evidence')->nullable();
            $table->string('scoring', 40);
            $table->boolean('reverse_scoring')->default(false);
            $table->string('navigation', 20);
            $table->json('sections');
            $table->string('contract', 40);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('state');
        });

        $now = now();
        $sections = json_encode([
            'academic',
            'socioeconomic',
            'behavioral',
            'competence',
            'psychological_capital',
            'self_perceived_employability',
        ]);

        DB::table('questionnaire_versions')->insert([
            [
                'code' => 'draft-v1',
                'name' => 'Draft research instrument v1',
                'state' => 'draft',
                'current_slot' => null,
                'source_code' => null,
                'change_summary' => null,
                'panel_approved' => false,
                'approval_evidence' => null,
                'scoring' => 'legacy-0-100',
                'reverse_scoring' => true,
                'navigation' => 'construct',
                'sections' => json_encode(['academic_behavior']),
                'contract' => 'legacy-construct',
                'published_at' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ucc-panel-approved-v1',
                'name' => 'UCC panel-approved 36-item instrument v1 (historical)',
                'state' => 'published',
                'current_slot' => null,
                'source_code' => null,
                'change_summary' => null,
                'panel_approved' => true,
                'approval_evidence' => 'Historical panel approval for the 36-item instrument. Later versions do not inherit it.',
                'scoring' => 'section-mean-1-5',
                'reverse_scoring' => false,
                'navigation' => 'section',
                'sections' => $sections,
                'contract' => 'historical-36',
                'published_at' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ucc-panel-revised-60-v2',
                'name' => 'UCC panel-approved 60-item instrument v2',
                'state' => 'published',
                'current_slot' => 1,
                'source_code' => null,
                'change_summary' => null,
                'panel_approved' => true,
                'approval_evidence' => 'Panel approval confirmed for this 60-item instrument. It is not psychometric validation and is not copied onto later versions.',
                'scoring' => 'section-mean-1-5',
                'reverse_scoring' => false,
                'navigation' => 'section',
                'sections' => $sections,
                'contract' => 'six-by-ten',
                'published_at' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_versions');
    }
};
