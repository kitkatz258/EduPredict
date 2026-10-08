<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_reports', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->foreignId('supersedes_id')->nullable()->after('version')
                ->constrained('grade_reports')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('confirmed_at');

            $table->index(['student_id', 'status', 'superseded_at']);
        });

        Schema::table('subject_grades', function (Blueprint $table) {
            $table->boolean('is_incomplete')->default(false)->after('is_failed');
        });

        DB::table('subject_grades')
            ->where(function ($query): void {
                $query->whereIn('final_grade', ['INC', 'inc', 'INCOMPLETE'])
                    ->orWhere('remarks', 'INCOMPLETE');
            })
            ->update(['is_incomplete' => true, 'is_failed' => false]);
    }

    public function down(): void
    {
        Schema::table('subject_grades', function (Blueprint $table) {
            $table->dropColumn('is_incomplete');
        });

        Schema::table('grade_reports', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'status', 'superseded_at']);
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropColumn(['version', 'superseded_at']);
        });
    }
};
