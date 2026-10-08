<?php

namespace App\Livewire\Student;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\GradeReport;
use App\Models\Student;
use App\Services\Grades\GradeReportParser;
use App\Services\Grades\GradeReportWriter;
use App\Services\Grades\GradeRowNormalizer;
use App\Services\Grades\GwaCalculator;
use App\Services\Grades\ParsedGradeReport;
use App\Services\Grades\ParsedGradeRow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class GradeReportForm extends Component
{
    use DispatchesToasts;
    use WithFileUploads;

    private const MODES = ['upload', 'paste', 'manual'];

    public string $mode = 'upload';

    /** `input` shows the upload/paste area; `review` shows parsed rows. Manual mode always edits rows. */
    public string $stage = 'input';

    public ?int $reportId = null;

    /** Confirmed report being replaced by a new version. */
    public ?int $replacesId = null;

    public string $replacingLabel = '';

    public string $school_year = '';

    public string $semester = '';

    public string $source = 'manual';

    public ?string $detected_gpa = null;

    public string $pastedText = '';

    public $upload = null;

    public string $originalPath = '';

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @var list<string> */
    public array $warnings = [];

    public bool $usedAiFallback = false;

    public ?int $editingRow = null;

    /** @var list<int> */
    public array $editedRows = [];

    public function mount(?int $reportId = null, ?int $replaceId = null): void
    {
        $this->authorize('create', GradeReport::class);

        if ($replaceId) {
            $this->startReplacement($replaceId);
        } elseif ($reportId) {
            $this->continueDraft($reportId);
        } else {
            $this->rows = [$this->blankRow()];
        }
    }

    public function setMode(string $mode): void
    {
        $this->authorize('create', GradeReport::class);
        if (! in_array($mode, self::MODES, true) || $mode === $this->mode) {
            return;
        }

        $this->mode = $mode;
        if ($this->replacesId === null && $this->reportId === null) {
            $this->resetInput();
        }
    }

    public function startOver(): void
    {
        $this->authorize('create', GradeReport::class);
        $this->reportId = null;
        $this->replacesId = null;
        $this->replacingLabel = '';
        $this->school_year = '';
        $this->semester = '';
        $this->detected_gpa = null;
        $this->resetInput();
    }

    public function continueDraft(int $id): void
    {
        $report = GradeReport::query()->with(['subjectGrades', 'student'])->findOrFail($id);
        $this->authorize('update', $report);

        $this->startOver();
        $this->fillFrom($report);
        $this->reportId = $report->id;
        $this->originalPath = (string) $report->original_file_path;
        $this->mode = 'manual';
    }

    /**
     * Loads a confirmed term into a new draft. Confirming it supersedes the original,
     * which itself is never edited.
     */
    public function startReplacement(int $id): void
    {
        $report = GradeReport::query()->with(['subjectGrades', 'student'])->findOrFail($id);
        $this->authorize('replace', $report);

        $this->startOver();
        $this->fillFrom($report);
        $this->replacesId = $report->id;
        $this->replacingLabel = $report->school_year.' · '.$report->semester.' (version '.$report->version.')';
        $this->source = 'manual';
        $this->mode = 'manual';
    }

    public function parsePaste(GradeReportParser $parser): void
    {
        $this->authorize('create', GradeReport::class);
        $this->validate(['pastedText' => ['required', 'string', 'min:10']], [
            'pastedText.required' => 'Paste the grades table from the UCC portal first.',
        ]);

        $this->applyParsed($parser->parseText($this->pastedText, 'pasted'));
    }

    public function parseUpload(GradeReportParser $parser): void
    {
        $this->authorize('create', GradeReport::class);
        $this->validate([
            'upload' => ['required', 'file', 'max:'.config('edupredict.grades.upload_max_kb', 10240), 'mimes:pdf,png,jpg,jpeg,webp,txt,csv'],
        ], [
            'upload.required' => 'Choose a PDF or image of your grades first.',
        ]);

        $stored = $this->upload->store('grade-reports');
        $path = Storage::disk('local')->path($stored);
        $parsed = $parser->parseFile($path, (string) $this->upload->getMimeType(), (string) $this->upload->getClientOriginalName());
        $this->applyParsed($parsed);
        $this->originalPath = $stored;
    }

    public function editRow(int $index): void
    {
        $this->authorize('create', GradeReport::class);
        $this->editingRow = array_key_exists($index, $this->rows) ? $index : null;
    }

    public function stopEditing(): void
    {
        $this->editingRow = null;
    }

    public function addRow(): void
    {
        $this->authorize('create', GradeReport::class);
        $this->rows[] = $this->blankRow();
        if ($this->mode !== 'manual') {
            $this->editingRow = array_key_last($this->rows);
        }
    }

    public function removeRow(int $index): void
    {
        $this->authorize('create', GradeReport::class);
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        $this->editingRow = null;
        $this->editedRows = [];
        if ($this->rows === []) {
            $this->rows = [$this->blankRow()];
        }
    }

    public function updatedRows(mixed $value, ?string $key = null): void
    {
        [$index, $field] = $key !== null ? array_pad(explode('.', $key, 2), 2, null) : [null, null];
        if ($index !== null && isset($this->rows[(int) $index])) {
            $index = (int) $index;
            if (! in_array($index, $this->editedRows, true)) {
                $this->editedRows[] = $index;
            }
            if ($field === 'final_grade') {
                $this->rows[$index]['remarks'] = '';
            }
        }
        $this->renormalizeRows();
    }

    public function saveDraft(GradeReportWriter $writer): void
    {
        $student = $this->student();
        $this->validateTerm();
        $this->renormalizeRows();

        $report = $writer->saveDraft($student, $this->reportAttributes(), $this->rows, $this->warnings);
        $this->reportId = $report->id;
        $this->toast('Draft saved. It does not count until you confirm it.');
        $this->dispatch('grades-updated');
    }

    public function confirm(GradeReportWriter $writer): void
    {
        $student = $this->student();
        $this->validateTerm();
        $this->renormalizeRows();

        if (count(array_filter($this->rows, fn (array $row): bool => trim((string) $row['subject_code']) !== '')) < 1) {
            throw ValidationException::withMessages(['rows' => 'Add at least one subject before saving.']);
        }
        $writer->assertFinalGrades($this->rows);

        if ($this->replacesId !== null) {
            $this->authorize('replace', GradeReport::query()->findOrFail($this->replacesId));
        }

        $report = $this->existingDraft() ?? $writer->saveDraft($student, $this->reportAttributes(), $this->rows, $this->warnings);
        $this->authorize('confirm', $report);

        $writer->confirm($report, $this->reportAttributes(), $this->rows, $this->warnings, $this->replacesId);

        $this->toast($this->replacesId !== null
            ? 'Grades updated. Earlier predictions keep the version they used.'
            : 'Grades saved. They will be used in your next prediction.');
        $this->startOver();
        $this->dispatch('grades-updated');
    }

    /**
     * @return array{rounded: ?float, failed: int, incomplete: int, provisional: bool, mismatch: bool}
     */
    public function computedGwa(): array
    {
        $result = app(GwaCalculator::class)->compute($this->rows);

        return [
            'rounded' => $result->roundedGpa,
            'failed' => $result->failedCount,
            'incomplete' => $result->incompleteCount,
            'provisional' => $result->isProvisional(),
            'mismatch' => $this->detected_gpa !== null
                && $this->detected_gpa !== ''
                && $result->roundedGpa !== null
                && abs((float) $this->detected_gpa - $result->roundedGpa) > 0.01,
        ];
    }

    public function render(): View
    {
        return view('livewire.student.grade-report-form', [
            'gwa' => $this->computedGwa(),
            'schoolYears' => $this->schoolYearOptions(),
            'semesters' => ['First' => '1st Semester', 'Second' => '2nd Semester', 'Midyear' => 'Midyear'],
            'reviewCount' => count(array_filter($this->rows, fn (array $row): bool => ! empty($row['needs_review']))),
        ]);
    }

    /**
     * Academic years offered in the selector, newest first. A parsed year outside
     * the range is kept so it can still be confirmed.
     *
     * @return list<string>
     */
    private function schoolYearOptions(): array
    {
        $now = now();
        $start = $now->month >= 8 ? $now->year : $now->year - 1;
        $years = [];
        for ($year = $start; $year > $start - 8; $year--) {
            $years[] = $year.'-'.($year + 1);
        }
        if ($this->school_year !== '' && ! in_array($this->school_year, $years, true)) {
            array_unshift($years, $this->school_year);
        }

        return $years;
    }

    private function student(): Student
    {
        $student = auth()->user()?->student;
        abort_unless($student, 403);
        $this->authorize('create', GradeReport::class);

        return $student;
    }

    private function fillFrom(GradeReport $report): void
    {
        $this->school_year = $report->school_year;
        $this->semester = $report->semester;
        $this->source = $report->source;
        $this->detected_gpa = $report->detected_gpa !== null ? (string) $report->detected_gpa : null;
        $this->warnings = $report->warnings ?? [];
        $this->rows = $report->subjectGrades->map(fn ($row) => [
            'subject_code' => $row->subject_code,
            'subject_name' => $row->subject_name,
            'units' => (string) $row->units,
            'midterm_grade' => $row->midterm_grade,
            'final_exam_grade' => $row->final_exam_grade,
            'final_grade' => $row->final_grade,
            'remarks' => $row->remarks,
            'is_failed' => $row->is_failed,
            'is_incomplete' => $row->is_incomplete,
            'needs_review' => $row->needs_review,
            'is_major_subject' => $row->is_major_subject,
            'warnings' => $row->needs_review ? ['Needs review'] : [],
        ])->all();

        if ($this->rows === []) {
            $this->rows = [$this->blankRow()];
        }
        $this->renormalizeRows();
    }

    private function existingDraft(): ?GradeReport
    {
        if ($this->reportId === null) {
            return null;
        }

        $report = GradeReport::query()->findOrFail($this->reportId);
        $this->authorize('update', $report);

        return $report;
    }

    private function applyParsed(ParsedGradeReport $parsed): void
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
            $this->warnings[] = 'Nothing usable was found. Add the rows yourself or switch to Manual Entry.';
        }
        $this->editingRow = null;
        $this->editedRows = [];
        $this->stage = 'review';
    }

    private function resetInput(): void
    {
        $this->stage = 'input';
        $this->source = 'manual';
        $this->pastedText = '';
        $this->upload = null;
        $this->originalPath = '';
        $this->warnings = [];
        $this->usedAiFallback = false;
        $this->editingRow = null;
        $this->editedRows = [];
        $this->rows = [$this->blankRow()];
        $this->resetValidation();
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
        ], [
            'school_year.required' => 'Choose the academic year.',
            'semester.required' => 'Choose the semester.',
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
            'is_incomplete' => false,
            'needs_review' => false,
            'is_major_subject' => false,
            'warnings' => [],
        ];
    }
}
