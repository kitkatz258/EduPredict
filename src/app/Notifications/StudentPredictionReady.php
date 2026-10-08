<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Prediction;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the department heads whose scope includes the student.
 */
class StudentPredictionReady extends Notification
{
    use Queueable;

    public function __construct(public Student $student, public Prediction $prediction) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{prediction_id: int, student_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $name = $this->student->user?->name ?: 'A student';

        return [
            'prediction_id' => $this->prediction->id,
            'student_id' => $this->student->id,
            'title' => 'New student prediction',
            'message' => $name.' requested a new prediction.',
            'url' => route('students.show', $this->student),
        ];
    }
}
