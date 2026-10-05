<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title');
            $table->text('description');
            $table->json('targets_factor');
            $table->string('min_risk_level', 16);
            $table->timestamps();
        });

        Schema::create('recommended_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intervention_id')->constrained()->restrictOnDelete();
            $table->text('phrased_text');
            $table->string('phrasing_source', 16);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestamps();

            $table->unique(['prediction_id', 'intervention_id']);
            $table->index('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommended_actions');
        Schema::dropIfExists('interventions');
    }
};
