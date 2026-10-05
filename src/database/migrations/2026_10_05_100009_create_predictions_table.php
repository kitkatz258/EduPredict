<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('model_version', 64);
            $table->decimal('employability_score', 5, 2);
            $table->decimal('dropout_probability', 6, 4);
            $table->string('dropout_risk', 16);
            $table->string('confidence', 16);
            $table->string('program_shift_flag', 32)->default('none');
            $table->json('factors');
            $table->json('feature_snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'created_at']);
            $table->index('dropout_risk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
