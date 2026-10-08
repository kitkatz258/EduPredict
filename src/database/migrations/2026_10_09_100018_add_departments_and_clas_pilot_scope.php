<?php

use App\Services\Academic\ClasStructureSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the College -> Department -> Program level and a pilot-scope flag, then
 * aligns existing rows with CLAS (fresh databases get CLAS from the seeder).
 * Additive only: no rows or columns are removed.
 * Legacy `students.adviser_id` and faculty users are kept as historical data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('college_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('college_id');
        });

        Schema::table('colleges', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('code');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('code');

            $table->index('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
        });

        if (DB::table('programs')->exists()) {
            app(ClasStructureSynchronizer::class)->sync();
        }
    }

    /**
     * Drops the new columns and table. Renamed or re-parented colleges and
     * programs are not reverted; restore from a backup if that is needed.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('is_active');
        });

        Schema::table('colleges', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('departments');
    }
};
