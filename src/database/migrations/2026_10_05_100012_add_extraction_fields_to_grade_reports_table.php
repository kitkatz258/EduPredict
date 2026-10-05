<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_reports', function (Blueprint $table) {
            $table->decimal('detected_gpa', 8, 4)->nullable()->after('original_file_path');
            $table->json('warnings')->nullable()->after('detected_gpa');
        });
    }

    public function down(): void
    {
        Schema::table('grade_reports', function (Blueprint $table) {
            $table->dropColumn(['detected_gpa', 'warnings']);
        });
    }
};
