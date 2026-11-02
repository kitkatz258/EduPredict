<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Services\Questionnaire\QuestionnaireVersionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionnaireItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isRole(UserRole::Administrator) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'section' => ['required', 'string', Rule::in(array_keys(config('edupredict.questionnaire.sections', [])))],
            'definition_version' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! app(QuestionnaireVersionCatalog::class)->exists($value)) {
                    $fail('Choose a questionnaire version that exists.');
                }
            }],
            'construct' => ['required', 'string', Rule::in(array_keys(config('edupredict.questionnaire.constructs', [])))],
            'text' => ['required', 'string', 'max:500'],
            'reverse_scored' => ['boolean'],
            'is_active' => ['boolean'],
            'is_draft' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ];
    }
}
