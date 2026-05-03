<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\GradeEvaluationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GradeEvaluationController::class, 'index'])->name('grade-evaluator.index');
Route::get('/template.csv', [GradeEvaluationController::class, 'template'])->name('grade-evaluator.template');
Route::get('/template.xlsx', [GradeEvaluationController::class, 'excelTemplate'])->name('grade-evaluator.excel-template');
Route::post('/evaluate', [GradeEvaluationController::class, 'store'])->name('grade-evaluator.evaluate');
Route::post('/evaluate-semester', [GradeEvaluationController::class, 'evaluateSemester'])->name('semester-evaluator.evaluate');
Route::resource('courses', CourseController::class)->except('show');
