<?php

namespace Database\Seeders;

use App\Models\QuestionnaireItem;
use Illuminate\Database\Seeder;

class QuestionnaireItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'study_habits' => [
                ['I review my notes after class.', false],
                ['I wait until the night before an exam to study.', true],
                ['I set aside a regular place to study.', false],
                ['I often study without a plan.', true],
            ],
            'time_management' => [
                ['I start assignments soon after they are given.', false],
                ['I often run out of time for coursework.', true],
                ['I keep a schedule for school tasks.', false],
                ['I underestimate how long schoolwork will take.', true],
            ],
            'motivation' => [
                ['I am interested in learning the material in my program.', false],
                ['I see little point in attending class.', true],
                ['I want to finish my degree.', false],
                ['I feel unmotivated to work on my courses.', true],
            ],
            'procrastination' => [
                ['I delay starting important schoolwork.', false],
                ['I start schoolwork as soon as I can.', true],
                ['I put off tasks even when I know they matter.', false],
                ['I finish coursework ahead of the deadline.', true],
            ],
            'engagement' => [
                ['I participate in class activities.', false],
                ['I rarely talk with classmates about coursework.', true],
                ['I keep up with course announcements.', false],
                ['I feel disconnected from my classes.', true],
            ],
        ];

        foreach ($items as $construct => $rows) {
            foreach ($rows as $index => [$text, $reverse]) {
                QuestionnaireItem::query()->updateOrCreate(
                    [
                        'definition_version' => config('edupredict.questionnaire.current_version', 'draft-v1'),
                        'construct' => $construct,
                        'text' => $text,
                    ],
                    [
                        'section' => 'academic_behavior',
                        'reverse_scored' => $reverse,
                        'is_active' => true,
                        'is_draft' => true,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }
}
