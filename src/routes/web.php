<?php

use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleDashboardController;
use App\Http\Controllers\StudentRecordController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/demo/table', 'pages.demo-table')->name('demo.table');

Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::get('/student/dashboard', [RoleDashboardController::class, 'student'])
        ->middleware('role:student')
        ->name('student.dashboard');

    Route::get('/faculty/dashboard', [RoleDashboardController::class, 'faculty'])
        ->middleware('role:faculty')
        ->name('faculty.dashboard');

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
        Route::get('/admin/advisers', [AdminPageController::class, 'advisers'])->name('admin.advisers');
    });

    Route::get('/students/{student}', [StudentRecordController::class, 'show'])
        ->name('students.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
