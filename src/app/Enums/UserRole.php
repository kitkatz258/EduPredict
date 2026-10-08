<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    /** Legacy only: kept so historical rows still load. It grants no access. */
    case Faculty = 'faculty';
    case DepartmentHead = 'department_head';
    case Dean = 'dean';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Faculty => 'Faculty (legacy)',
            self::DepartmentHead => 'Department Head',
            self::Dean => 'Dean',
            self::Administrator => 'Administrator',
        };
    }

    public function isLegacy(): bool
    {
        return $this === self::Faculty;
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Student => 'student.dashboard',
            self::Faculty => 'login',
            self::DepartmentHead => 'department.dashboard',
            self::Dean => 'dean.dashboard',
            self::Administrator => 'admin.dashboard',
        };
    }

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => ! $role->isLegacy()));
    }

    /**
     * Roles an administrator may create. Students self-register.
     *
     * @return list<self>
     */
    public static function staffAssignable(): array
    {
        return [self::DepartmentHead, self::Dean, self::Administrator];
    }
}
