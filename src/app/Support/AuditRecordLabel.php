<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Department;
use App\Models\GradeReport;
use App\Models\InstitutionStudent;
use App\Models\Intervention;
use App\Models\Prediction;
use App\Models\Program;
use App\Models\PsocOccupation;
use App\Models\QuestionnaireItem;
use App\Models\SocioeconomicProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Readable Activity Log labels from stored rows. Missing records stay unnamed.
 */
final class AuditRecordLabel
{
    public static function affected(AuditLog $log): string
    {
        $class = $log->subject_type;
        if (! is_string($class) || $class === '') {
            return '—';
        }

        if (! class_exists($class)) {
            return 'Record (no longer available)';
        }

        $type = self::typeName($class);

        if ($class === SocioeconomicProfile::class) {
            return 'Socioeconomic profile';
        }

        if ($log->subject_id === null) {
            return $type.' (no specific record)';
        }

        $subject = $log->subject;
        if (! $subject instanceof Model) {
            return $type.' (no longer available)';
        }

        $label = self::fromModel($subject);

        return $label !== '' ? $label : $type;
    }

    public static function actor(AuditLog $log): string
    {
        $user = $log->user;
        if ($user instanceof User) {
            $label = self::person($user->name, $user->email);

            return $label !== '' ? $label : 'User';
        }

        return $log->user_id ? 'User (no longer available)' : 'System';
    }

    private static function typeName(string $class): string
    {
        return match ($class) {
            User::class => 'User',
            Student::class => 'Student',
            InstitutionStudent::class => 'Eligible student',
            Prediction::class => 'Prediction',
            AccountDeletionRequest::class => 'Deletion request',
            College::class => 'College',
            Department::class => 'Department',
            Program::class => 'Program',
            QuestionnaireItem::class => 'Questionnaire item',
            PsocOccupation::class => 'PSOC occupation',
            Intervention::class => 'Intervention',
            GradeReport::class => 'Grade report',
            SocioeconomicProfile::class => 'Socioeconomic profile',
            default => class_basename($class),
        };
    }

    private static function fromModel(Model $subject): string
    {
        return match (true) {
            $subject instanceof User => self::person($subject->name, $subject->email),
            $subject instanceof Student => self::student($subject),
            $subject instanceof InstitutionStudent => self::eligible($subject),
            $subject instanceof Prediction => self::prediction($subject),
            $subject instanceof AccountDeletionRequest => self::deletion($subject),
            $subject instanceof College,
            $subject instanceof Department,
            $subject instanceof Program => self::named($subject->getAttribute('code'), $subject->getAttribute('name')),
            $subject instanceof QuestionnaireItem => self::clip((string) $subject->text),
            $subject instanceof PsocOccupation => self::named($subject->psoc_code, $subject->title),
            $subject instanceof Intervention => self::named($subject->code, $subject->title),
            $subject instanceof GradeReport => self::grade($subject),
            default => self::generic($subject),
        };
    }

    private static function person(?string $name, ?string $email): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return $name;
        }

        return trim((string) $email);
    }

    private static function student(Student $student): string
    {
        $number = trim((string) $student->student_number);
        $name = self::person($student->user?->name, null);
        if ($number !== '' && $name !== '') {
            return $number.' · '.$name;
        }

        return $number !== '' ? $number : $name;
    }

    private static function eligible(InstitutionStudent $student): string
    {
        $number = trim((string) $student->student_number);
        $last = trim((string) $student->last_name);
        $first = trim((string) $student->first_name);
        $who = trim($last.($last !== '' && $first !== '' ? ', ' : '').$first);
        if ($number !== '' && $who !== '') {
            return $number.' · '.$who;
        }

        return $number !== '' ? $number : $who;
    }

    private static function prediction(Prediction $prediction): string
    {
        if ($prediction->student === null) {
            return 'Prediction (student record no longer available)';
        }

        $number = trim((string) $prediction->student->student_number);

        return $number !== '' ? 'Prediction · '.$number : 'Prediction';
    }

    private static function deletion(AccountDeletionRequest $request): string
    {
        $who = self::person($request->user?->name, $request->user?->email);

        return $who !== '' ? 'Deletion request · '.$who : 'Deletion request (account no longer available)';
    }

    private static function grade(GradeReport $report): string
    {
        $year = trim((string) $report->school_year);
        $semester = trim((string) $report->semester);
        $label = trim(($year !== '' ? 'AY '.$year : '').($semester !== '' ? ' '.$semester : ''));

        return $label;
    }

    private static function named(mixed $code, mixed $name): string
    {
        $code = trim((string) $code);
        $name = trim((string) $name);
        if ($code !== '' && $name !== '') {
            return $code.' · '.$name;
        }

        return $code !== '' ? $code : $name;
    }

    private static function clip(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 77).'…' : $text;
    }

    private static function generic(Model $subject): string
    {
        foreach (['name', 'title', 'code'] as $field) {
            $value = $subject->getAttribute($field);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
