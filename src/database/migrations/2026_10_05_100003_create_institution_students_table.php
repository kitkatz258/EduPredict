<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 32)->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->date('birthdate')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_registered')->default(false);
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('program_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_students');
    }
};
