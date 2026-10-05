<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use Livewire\Component;

class AdviserAssignment extends Component
{
    public ?int $facultyId = null;

    /** @var list<int> */
    public array $studentIds = [];

    public function mount(): void
    {
        $this->authorize('assignAdviser', Student::class);
    }

    public function assign(): void
    {
        $this->authorize('assignAdviser', Student::class);

        $this->validate([
            'facultyId' => ['required', 'integer', 'exists:users,id'],
            'studentIds' => ['required', 'array', 'min:1'],
            'studentIds.*' => ['integer', 'exists:students,id'],
        ]);

        $faculty = User::query()->findOrFail($this->facultyId);
        abort_unless($faculty->isRole(UserRole::Faculty), 422);

        Student::query()
            ->whereIn('id', $this->studentIds)
            ->update(['adviser_id' => $faculty->id]);

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => 'adviser_assigned',
            'subject_type' => User::class,
            'subject_id' => $faculty->id,
            'meta' => ['student_ids' => $this->studentIds],
            'ip' => request()->ip(),
        ]);

        $this->reset('studentIds');
        session()->flash('success', 'Adviser assignment updated.');
    }

    public function render()
    {
        return view('livewire.admin.adviser-assignment', [
            'faculty' => User::query()->where('role', UserRole::Faculty)->orderBy('name')->get(),
            'students' => Student::query()->with('user')->orderBy('student_number')->limit(200)->get(),
        ]);
    }
}
