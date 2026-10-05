<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_report_id')->constrained()->cascadeOnDelete();
            $table->string('subject_code', 32);
            $table->string('subject_name');
            $table->decimal('units', 4, 2);
            $table->string('midterm_grade', 16)->nullable();
            $table->string('final_exam_grade', 16)->nullable();
            $table->string('final_grade', 16);
            $table->string('remarks', 32);
            $table->boolean('is_failed')->default(false);
            $table->boolean('is_major_subject')->default(false);
            $table->boolean('needs_review')->default(false);
            $table->timestamps();

            $table->index('grade_report_id');
            $table->index('subject_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_grades');
    }
};
