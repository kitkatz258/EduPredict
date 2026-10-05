<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('student')->after('password');
            $table->foreignId('college_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('program_id');
            $table->timestamp('consented_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('consented_at');
            $table->boolean('must_change_password')->default(false)->after('last_login_at');

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('college_id');
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn([
                'role',
                'is_active',
                'consented_at',
                'last_login_at',
                'must_change_password',
            ]);
        });
    }
};
