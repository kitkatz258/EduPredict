<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Validation\ValidationException;

/**
 * Flips academic is_active flags without deleting or unlinking rows.
 *
 * The CLAS college stays active. CLAS keeps at least one active department
 * and the catalog keeps at least one active program. An inactive program
 * cannot be turned on, because that would widen registration.
 */
final class AcademicActivation
{
    public function setCollegeActive(College $college, bool $active): void
    {
        $this->assertCollegeActive($college, $active);
        if ($college->is_active === $active) {
            return;
        }

        $college->update(['is_active' => $active]);
    }

    public function assertCollegeActive(College $college, bool $active): void
    {
        if (! $active && $college->code === ClasStructureSynchronizer::COLLEGE_CODE) {
            throw ValidationException::withMessages([
                'is_active' => 'The CLAS pilot college stays active. Departments, programs, students, and predictions are not removed.',
            ]);
        }

        if ($active && $college->code !== ClasStructureSynchronizer::COLLEGE_CODE) {
            throw ValidationException::withMessages([
                'is_active' => 'Only the CLAS college stays in the pilot. Activating another college is not available.',
            ]);
        }
    }

    public function setDepartmentActive(Department $department, bool $active): void
    {
        $this->assertDepartmentActive($department, $active);
        if ($department->is_active === $active) {
            return;
        }

        $department->update(['is_active' => $active]);
    }

    public function assertDepartmentActive(Department $department, bool $active): void
    {
        if ($active || ! $department->is_active) {
            return;
        }

        $department->loadMissing('college');
        if ($department->college?->code !== ClasStructureSynchronizer::COLLEGE_CODE) {
            return;
        }

        $others = Department::query()
            ->where('college_id', $department->college_id)
            ->where('is_active', true)
            ->whereKeyNot($department->id)
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                'is_active' => 'CLAS needs at least one active department. Students, programs, and prediction history stay linked.',
            ]);
        }
    }

    public function setProgramActive(Program $program, bool $active): void
    {
        $this->assertProgramActive($program, $active);
        if ($program->is_active === $active) {
            return;
        }

        $program->update(['is_active' => $active]);
    }

    public function assertProgramActive(Program $program, bool $active): void
    {
        if ($active && ! $program->is_active) {
            throw ValidationException::withMessages([
                'is_active' => 'An inactive program stays outside the CLAS pilot. Turning it on would allow new registrations in that program.',
            ]);
        }

        if (! $active && $program->is_active) {
            $others = Program::query()->where('is_active', true)->whereKeyNot($program->id)->count();
            if ($others === 0) {
                throw ValidationException::withMessages([
                    'is_active' => 'CLAS needs at least one active program. This program, its students, and their predictions stay in place.',
                ]);
            }
        }
    }
}
