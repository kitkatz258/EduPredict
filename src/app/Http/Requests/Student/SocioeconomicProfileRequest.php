<?php

namespace App\Http\Requests\Student;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocioeconomicProfileRequest extends FormRequest
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
        return self::fieldRules(required: true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function draftRules(): array
    {
        return self::fieldRules(required: false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fieldRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'household_income_bracket' => [$presence, 'string', Rule::in(array_keys(config('edupredict.profile.income_brackets', [])))],
            'household_size' => [$presence, 'integer', 'between:1,20'],
            'scholarship_status' => [$presence, 'string', Rule::in(array_keys(config('edupredict.profile.scholarship_statuses', [])))],
            'employment_status' => [$presence, 'string', Rule::in(array_keys(config('edupredict.profile.employment_statuses', [])))],
            'living_arrangement' => [$presence, 'string', Rule::in(array_keys(config('edupredict.profile.living_arrangements', [])))],
            'has_internet' => [$presence, 'string', Rule::in(array_keys(config('edupredict.profile.internet_access', [])))],
            'has_device' => [$presence, 'string', Rule::in(array_keys(config('edupredict.profile.device_access', [])))],
            'has_study_space' => [$presence, 'string', Rule::in(array_keys(config('edupredict.profile.study_space', [])))],
        ];
    }
}
