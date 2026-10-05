<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('student_number', 32)->unique();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('enrollment_year')->nullable();
            $table->unsignedTinyInteger('semesters_completed')->default(0);
            $table->string('consent_version', 32)->nullable();
            $table->timestamps();

            $table->index('program_id');
            $table->index('adviser_id');
            $table->index('year_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
