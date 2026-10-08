<?php

declare(strict_types=1);

namespace App\Support;

class PageTitle
{
    public static function forRequest(): string
    {
        $app = (string) config('app.name');
        $label = self::labels()[request()->route()?->getName() ?? ''] ?? null;

        return $label === null ? $app : $label.' — '.$app;
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'login' => 'Sign in',
            'register' => 'Student registration',
            'password.request' => 'Forgot password',
            'password.reset' => 'Reset password',
            'password.confirm' => 'Confirm password',
            'password.forced' => 'Change password',
            'privacy' => 'Privacy notice',
            'student.dashboard' => 'Student dashboard',
            'student.results' => 'Results',
            'student.careers' => 'Career matches',
            'student.assessment' => 'Assessment',
            'student.history' => 'History',
            'student.grades' => 'My grades',
            'student.questionnaire' => 'Questionnaire',
            'department.dashboard' => 'Department dashboard',
            'dean.dashboard' => 'College dashboard',
            'admin.dashboard' => 'Institution dashboard',
            'admin.users' => 'Users',
            'admin.institution-students' => 'Institution students',
            'admin.colleges' => 'Academic structure',
            'admin.questionnaire' => 'Questionnaire items',
            'admin.psoc' => 'PSOC occupations',
            'admin.interventions' => 'Interventions',
            'admin.audit' => 'Audit log',
            'admin.deletion-requests' => 'Deletion requests',
            'students.show' => 'Student record',
            'profile.edit' => 'Account',
            'demo.table' => 'Demo table',
        ];
    }
}
