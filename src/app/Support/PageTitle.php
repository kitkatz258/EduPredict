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
            'home' => 'Welcome',
            'login' => 'Sign in',
            'register' => 'Student registration',
            'password.request' => 'Forgot password',
            'password.reset' => 'Reset password',
            'password.confirm' => 'Confirm password',
            'password.forced' => 'Change password',
            'privacy' => 'Privacy notice',
            'about' => 'About and limitations',
            'student.dashboard' => 'Student dashboard',
            'student.results' => 'Results',
            'student.careers' => 'Career matches',
            'student.profile' => 'My profile',
            'student.grades' => 'My grades',
            'student.questionnaire' => 'Questionnaire',
            'faculty.dashboard' => 'Advisees',
            'department.dashboard' => 'Program dashboard',
            'dean.dashboard' => 'College dashboard',
            'admin.dashboard' => 'Institution dashboard',
            'admin.users' => 'Users',
            'admin.institution-students' => 'Institution students',
            'admin.advisers' => 'Adviser assignment',
            'admin.colleges' => 'Colleges and programs',
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
