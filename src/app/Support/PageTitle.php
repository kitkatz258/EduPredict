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
            'student.assessment' => 'Assessment',
            'student.history' => 'History',
            'student.grades' => 'Assessment',
            'student.profile' => 'Assessment',
            'student.questionnaire' => 'Assessment',
            'student.results' => 'Student dashboard',
            'student.careers' => 'Student dashboard',
            'department.dashboard' => 'Department dashboard',
            'department.students' => 'Department students',
            'dean.dashboard' => 'CLAS dashboard',
            'admin.dashboard' => 'Institution dashboard',
            'admin.users' => 'Users',
            'admin.institution-students' => 'Eligible students',
            'admin.colleges' => 'Academic structure',
            'admin.questionnaire' => 'Questionnaire',
            'admin.psoc' => 'PSOC occupations',
            'admin.interventions' => 'Interventions',
            'admin.audit' => 'Activity Log',
            'admin.deletion-requests' => 'Deletion requests',
            'students.show' => 'Student record',
            'profile.edit' => 'Account',
            'demo.table' => 'Demo table',
        ];
    }
}
