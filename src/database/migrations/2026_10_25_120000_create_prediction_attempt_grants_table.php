<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_attempt_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('manila_date');
            $table->unsignedTinyInteger('extra_attempts');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->timestamps();

            $table->index(['student_id', 'manila_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_attempt_grants');
    }
};
