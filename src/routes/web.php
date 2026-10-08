<?php

use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\QuestionnairePageController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleDashboardController;
use App\Http\Controllers\Student\AssessmentPageController;
use App\Http\Controllers\Student\CareerMatchController;
use App\Http\Controllers\Student\GradePageController;
use App\Http\Controllers\Student\HistoryPageController;
use App\Http\Controllers\Student\QuestionnairePageController as StudentQuestionnairePageController;
use App\Http\Controllers\Student\ResultsPageController;
use App\Http\Controllers\StudentRecordController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::view('/demo/table', 'pages.demo-table')->name('demo.table');
Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy');

Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::get('/student/dashboard', [RoleDashboardController::class, 'student'])
        ->middleware('role:student')
        ->name('student.dashboard');

    Route::get('/student/grades', [GradePageController::class, 'index'])
        ->middleware('role:student')
        ->name('student.grades');

    Route::get('/student/assessment', [AssessmentPageController::class, 'index'])
        ->middleware('role:student')
        ->name('student.assessment');

    Route::redirect('/student/profile', '/student/assessment')
        ->middleware('role:student')
        ->name('student.profile');

    Route::get('/student/history', [HistoryPageController::class, 'index'])
        ->middleware('role:student')
        ->name('student.history');

    Route::get('/student/questionnaire', [StudentQuestionnairePageController::class, 'index'])
        ->middleware('role:student')
        ->name('student.questionnaire');

    Route::get('/student/results', [ResultsPageController::class, 'index'])
        ->middleware('role:student')
        ->name('student.results');

    Route::get('/student/career-matches', [CareerMatchController::class, 'index'])
        ->middleware('role:student')
        ->name('student.careers');

    Route::get('/department/dashboard', [RoleDashboardController::class, 'department'])
        ->middleware('role:department_head')
        ->name('department.dashboard');

    Route::get('/dean/dashboard', [RoleDashboardController::class, 'dean'])
        ->middleware('role:dean')
        ->name('dean.dashboard');

    Route::get('/admin/dashboard', [RoleDashboardController::class, 'admin'])
        ->middleware('role:administrator')
        ->name('admin.dashboard');

    Route::middleware('role:administrator')->group(function () {
        Route::get('/admin/users', [AdminPageController::class, 'users'])->name('admin.users');
        Route::get('/admin/institution-students', [AdminPageController::class, 'institutionStudents'])->name('admin.institution-students');
        Route::get('/admin/questionnaire', [QuestionnairePageController::class, 'index'])->name('admin.questionnaire');
        Route::get('/admin/psoc', [AdminPageController::class, 'psoc'])->name('admin.psoc');
        Route::get('/admin/colleges', [AdminPageController::class, 'colleges'])->name('admin.colleges');
        Route::get('/admin/interventions', [AdminPageController::class, 'interventions'])->name('admin.interventions');
        Route::get('/admin/audit', [AdminPageController::class, 'audit'])->name('admin.audit');
        Route::get('/admin/deletion-requests', [AdminPageController::class, 'deletionRequests'])->name('admin.deletion-requests');
    });

    Route::middleware('role:student')->group(function () {
        Route::get('/privacy/download.json', [PrivacyController::class, 'downloadJson'])->name('privacy.download.json');
        Route::get('/privacy/download.pdf', [PrivacyController::class, 'downloadPdf'])->name('privacy.download.pdf');
    });

    Route::get('/students/{student}', [StudentRecordController::class, 'show'])
        ->name('students.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
