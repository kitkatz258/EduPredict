<?php

namespace App\Http\Requests\Student;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class SkillsExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Student) === true
            && $this->user()->student !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Empty lists are valid. Many students have no internship or certification yet.
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'technicalSkills' => ['nullable', 'string', 'max:4000'],
            'certifications' => ['nullable', 'string', 'max:4000'],
            'internships' => ['nullable', 'string', 'max:4000'],
            'projects' => ['nullable', 'string', 'max:4000'],
            'workExperience' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
