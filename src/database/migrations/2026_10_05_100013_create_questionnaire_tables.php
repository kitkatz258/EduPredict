<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_items', function (Blueprint $table) {
            $table->id();
            $table->string('construct', 40);
            $table->text('text');
            $table->boolean('reverse_scored')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_draft')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['construct', 'sort_order']);
        });

        Schema::create('questionnaire_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->json('construct_scores')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'submitted_at']);
        });

        Schema::create('questionnaire_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('questionnaire_item_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('value');
            $table->timestamps();

            $table->unique(['questionnaire_response_id', 'questionnaire_item_id'], 'questionnaire_answers_response_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_answers');
        Schema::dropIfExists('questionnaire_responses');
        Schema::dropIfExists('questionnaire_items');
    }
};
