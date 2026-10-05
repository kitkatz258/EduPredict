<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psoc_occupations', function (Blueprint $table) {
            $table->id();
            $table->string('psoc_code', 16)->unique();
            $table->string('title');
            $table->string('major_group', 120);
            $table->text('description');
            $table->json('skill_tags');
            $table->json('related_program_codes');
            $table->timestamps();

            $table->index('major_group');
            $table->index('title');
        });

        Schema::create('career_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('psoc_occupation_id')->constrained()->cascadeOnDelete();
            $table->decimal('compatibility_score', 5, 2);
            $table->text('explanation');
            $table->string('explanation_source', 16);
            $table->json('matched_skills');
            $table->json('missing_skills');
            $table->timestamps();

            $table->unique(['prediction_id', 'psoc_occupation_id']);
            $table->index(['prediction_id', 'compatibility_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_matches');
        Schema::dropIfExists('psoc_occupations');
    }
};
