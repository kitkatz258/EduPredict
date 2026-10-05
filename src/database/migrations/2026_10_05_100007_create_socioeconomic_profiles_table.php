<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socioeconomic_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('household_income_bracket')->nullable();
            $table->text('household_size')->nullable();
            $table->text('scholarship_status')->nullable();
            $table->text('employment_status')->nullable();
            $table->text('living_arrangement')->nullable();
            $table->text('has_internet')->nullable();
            $table->text('has_device')->nullable();
            $table->text('has_study_space')->nullable();
            $table->boolean('is_draft')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('socioeconomic_profiles');
    }
};
