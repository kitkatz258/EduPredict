<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('legacy_source', 60)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'archived_at']);
        });

        Schema::create('student_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('issuer', 160)->nullable();
            $table->unsignedSmallInteger('issued_year')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('credential_reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('legacy_source', 60)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'archived_at']);
        });

        Schema::create('student_work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('experience_type', 32);
            $table->string('organization', 160)->nullable();
            $table->string('role_title', 160)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_ongoing')->default(false);
            $table->text('description')->nullable();
            $table->string('legacy_source', 60)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'archived_at']);
            $table->index(['student_id', 'experience_type']);
        });

        Schema::table('predictions', function (Blueprint $table) {
            $table->json('assessment_snapshot')->nullable()->after('feature_snapshot');
        });

        $this->copyLegacyEntries();
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn('assessment_snapshot');
        });
        Schema::dropIfExists('student_work_experiences');
        Schema::dropIfExists('student_certifications');
        Schema::dropIfExists('student_skills');
    }

    /**
     * Copies the free-form JSON lists into structured rows. The JSON columns are
     * left untouched (including projects, which are no longer collected).
     */
    private function copyLegacyEntries(): void
    {
        $now = now();

        DB::table('skills_experiences')->orderBy('id')->each(function (object $row) use ($now): void {
            foreach ($this->decode($row->technical_skills) as $skill) {
                $name = $this->label($skill, ['name', 'skill', 'title']);
                if ($name !== null) {
                    DB::table('student_skills')->insert([
                        'student_id' => $row->student_id,
                        'name' => mb_substr($name, 0, 120),
                        'legacy_source' => 'skills_experiences.technical_skills',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            foreach ($this->decode($row->certifications) as $certification) {
                $title = $this->label($certification, ['name', 'title']);
                if ($title === null) {
                    continue;
                }
                $year = is_array($certification) ? $this->label($certification, ['year']) : null;
                DB::table('student_certifications')->insert([
                    'student_id' => $row->student_id,
                    'title' => mb_substr($title, 0, 160),
                    'issuer' => is_array($certification) ? $this->label($certification, ['issuer', 'provider']) : null,
                    'issued_year' => $year !== null && preg_match('/^\d{4}$/', $year) ? (int) $year : null,
                    'description' => $year !== null && ! preg_match('/^\d{4}$/', $year) ? $year : null,
                    'legacy_source' => 'skills_experiences.certifications',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $experiences = [
                'ojt_internship' => ['internships', $this->decode($row->internships)],
                'other' => ['work_experience', $this->decode($row->work_experience)],
            ];
            foreach ($experiences as $type => [$column, $entries]) {
                foreach ($entries as $entry) {
                    $organization = is_array($entry) ? $this->label($entry, ['organization', 'employer', 'company']) : null;
                    $role = is_array($entry) ? $this->label($entry, ['role', 'title']) : $this->label($entry, []);
                    $hours = is_array($entry) ? $this->label($entry, ['hours']) : null;
                    $description = is_array($entry) ? $this->label($entry, ['description', 'detail']) : null;
                    if ($organization === null && $role === null) {
                        continue;
                    }
                    DB::table('student_work_experiences')->insert([
                        'student_id' => $row->student_id,
                        'experience_type' => $type,
                        'organization' => $organization !== null ? mb_substr($organization, 0, 160) : null,
                        'role_title' => $role !== null ? mb_substr($role, 0, 160) : null,
                        'description' => $description ?? ($hours !== null ? $hours.' hours' : null),
                        'legacy_source' => 'skills_experiences.'.$column,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    /**
     * @return list<mixed>
     */
    private function decode(mixed $json): array
    {
        $value = is_string($json) ? json_decode($json, true) : $json;

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  list<string>  $keys
     */
    private function label(mixed $value, array $keys): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $text = trim((string) $value);

            return $text === '' ? null : $text;
        }
        if (! is_array($value)) {
            return null;
        }
        foreach ($keys as $key) {
            if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key])) && trim((string) $value[$key]) !== '') {
                return trim((string) $value[$key]);
            }
        }

        return null;
    }
};
