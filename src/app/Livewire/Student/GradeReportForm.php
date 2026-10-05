<?php

namespace App\Livewire\Student;

use App\Models\GradeReport;
use App\Services\Grades\GradeReportParser;
use App\Services\Grades\GradeReportWriter;
use App\Services\Grades\GradeRowNormalizer;
use App\Services\Grades\GwaCalculator;
use App\Services\Grades\ParsedGradeRow;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class GradeReportForm extends Component
{
    use WithFileUploads;

    public ?int $reportId = null;

    public string $school_year = '';

    public string $semester = '';

    public string $source = 'manual';

    public ?string $detected_gpa = null;

    public string $pastedText = '';

    public $upload = null;

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @var list<string> */
    public array $warnings = [];

    public bool $usedAiFallback = false;

    public function mount(?int $reportId = null): void
    {
        $this->authorize('create', GradeReport::class);

        if ($reportId) {
            $this->loadReport($reportId);
        } else {
            $this->rows = [$this->blankRow()];
        }
    }

    public function startManual(): void
    {
        $this->authorize('create', GradeReport::class);
        $this->source = 'manual';
        $this->rows = [$this->blankRow()];
        $this->warnings = [];
        $this->usedAiFallback = false;
    }

    public function parsePaste(GradeReportParser $parser): void
    {
        $this->authorize('create', GradeReport::class);
        $this->validate(['pastedText' => ['required', 'string', 'min:10']]);

        $parsed = $parser->parseText($this->pastedText, 'pasted');
        $this->applyParsed($parsed);
    }

    public function parseUpload(GradeReportParser $parser): void
    {
        $this->authorize('create', GradeReport::class);
        $this->validate([
            'upload' => ['required', 'file', 'max:'.config('edupredict.grades.upload_max_kb', 10240), 'mimes:pdf,png,jpg,jpeg,webp,txt,csv'],
        ]);

        $stored = $this->upload->store('grade-reports');
        $path = Storage::disk('local')->path($stored);
        $parsed = $parser->parseFile($path, (string) $this->upload->getMimeType(), (string) $this->upload->getClientOriginalName());
        $this->applyParsed($parsed);
        $this->originalPath = $stored;
    }

    public string $originalPath = '';

    public function addRow(): void
    {
        $this->authorize('create', GradeReport::class);
        $this->rows[] = $this->blankRow();
    }

    public function removeRow(int $index): void
    {
        $this->authorize('create', GradeReport::class);
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        if ($this->rows === []) {
            $this->rows = [$this->blankRow()];
        }
    }

    public function updatedRows(): void
    {
        $this->renormalizeRows();
    }

    public function saveDraft(GradeReportWriter $writer): void
    {
        $student = $this->student();
        $this->validateTerm();
        $this->renormalizeRows();

        $report = $writer->saveDraft($student, $this->reportAttributes(), $this->rows, $this->warnings);
        $this->reportId = $report->id;
        session()->flash('success', 'Draft saved. Confirm when the rows look correct.');
        $this->dispatch('grades-updated');
    }

    public function confirm(GradeReportWriter $writer): void
    {
        $student = $this->student();
        $this->validateTerm();
        $this->renormalizeRows();

        if (count(array_filter($this->rows, fn (array $row): bool => trim((string) $row['subject_code']) !== '')) < 1) {
            throw ValidationException::withMessages(['rows' => 'Add at least one subject before confirming.']);
        }

        $report = $this->existingReport() ?? $writer->saveDraft($student, $this->reportAttributes(), $this->rows, $this->warnings);
        $this->authorize('confirm', $report);

        $writer->confirm($report, $this->reportAttributes(), $this->rows, $this->warnings);
        session()->flash('success', 'Grade report confirmed.');
        $this->redirect(route('student.grades'));
    }

    /**
     * @return array<string, mixed>
     */
    public function computedGwa(): array
    {
        $result = app(GwaCalculator::class)->compute($this->rows);

        return [
            'gpa' => $result->gpa,
            'rounded' => $result->roundedGpa,
            'failed' => $result->failedCount,
            'mismatch' => $this->detected_gpa !== null
                && $result->roundedGpa !== null
                && abs((float) $this->detected_gpa - $result->roundedGpa) > 0.01,
        ];
    }

    public function render()
    {
        return view('livewire.student.grade-report-form', [
            'gwa' => $this->computedGwa(),
        ]);
    }

    private function student(): \App\Models\Student
    {
        $student = auth()->user()?->student;
        abort_unless($student, 403);
        $this->authorize('create', GradeReport::class);

        return $student;
    }

    private function loadReport(int $id): void
    {
        $report = GradeReport::query()->with(['subjectGrades', 'student'])->findOrFail($id);
        $this->authorize('update', $report);

        $this->reportId = $report->id;
        $this->school_year = $report->school_year;
        $this->semester = $report->semester;
        $this->source = $report->source;
        $this->detected_gpa = $report->detected_gpa !== null ? (string) $report->detected_gpa : null;
        $this->warnings = $report->warnings ?? [];
        $this->originalPath = (string) $report->original_file_path;
        $this->rows = $report->subjectGrades->map(fn ($row) => [
            'subject_code' => $row->subject_code,
            'subject_name' => $row->subject_name,
            'units' => (string) $row->units,
            'midterm_grade' => $row->midterm_grade,
            'final_exam_grade' => $row->final_exam_grade,
            'final_grade' => $row->final_grade,
            'remarks' => $row->remarks,
            'is_failed' => $row->is_failed,
            'needs_review' => $row->needs_review,
            'is_major_subject' => $row->is_major_subject,
            'warnings' => $row->needs_review ? ['Needs review'] : [],
        ])->all();

        if ($this->rows === []) {
            $this->rows = [$this->blankRow()];
        }
    }

    private function existingReport(): ?GradeReport
    {
        if ($this->reportId === null) {
            return null;
        }

        $report = GradeReport::query()->findOrFail($this->reportId);
        $this->authorize('update', $report);
        abort_unless($report->student()->where('user_id', auth()->id())->exists(), 403);

        return $report;
    }

    /**
     * @param  \App\Services\Grades\ParsedGradeReport  $parsed
     */
    private function applyParsed($parsed): void
    {
        $this->source = $parsed->source;
        $this->usedAiFallback = $parsed->usedAiFallback;
        $this->warnings = $parsed->warnings;
        if ($parsed->schoolYear) {
            $this->school_year = $parsed->schoolYear;
        }
        if ($parsed->semester) {
            $this->semester = $parsed->semester;
        }
        if ($parsed->detectedGpa !== null) {
            $this->detected_gpa = (string) $parsed->detectedGpa;
        }
        $this->rows = array_map(fn (ParsedGradeRow $row) => $row->toArray(), $parsed->rows);
        if ($this->rows === []) {
            $this->rows = [$this->blankRow()];
            $this->warnings[] = 'Nothing usable was parsed. Enter the rows manually.';
        }
    }

    private function renormalizeRows(): void
    {
        $normalizer = app(GradeRowNormalizer::class);
        $codes = array_map(fn (array $row): string => $normalizer->normalizeCode((string) ($row['subject_code'] ?? '')), $this->rows);
        $this->rows = array_map(fn (array $row): array => $normalizer->normalize($row, $codes)->toArray(), $this->rows);
    }

    private function validateTerm(): void
    {
        $this->validate([
            'school_year' => ['required', 'string', 'max:16'],
            'semester' => ['required', 'string', 'max:32'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportAttributes(): array
    {
        return [
            'id' => $this->reportId,
            'school_year' => $this->school_year,
            'semester' => $this->semester,
            'source' => $this->source,
            'detected_gpa' => $this->detected_gpa !== null && $this->detected_gpa !== '' ? (float) $this->detected_gpa : null,
            'original_file_path' => $this->originalPath !== '' ? $this->originalPath : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankRow(): array
    {
        return [
            'subject_code' => '',
            'subject_name' => '',
            'units' => '',
            'midterm_grade' => '',
            'final_exam_grade' => '',
            'final_grade' => '',
            'remarks' => '',
            'is_failed' => false,
            'needs_review' => false,
            'is_major_subject' => false,
            'warnings' => [],
        ];
    }
}
