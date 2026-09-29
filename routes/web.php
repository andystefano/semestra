<?php

use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
});

Route::middleware('auth')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('asignaturas', [AsignaturaController::class, 'index'])->name('asignaturas.index');
    Route::get('asignaturas/preparar', [AsignaturaController::class, 'create'])->name('asignaturas.create');
    Route::post('asignaturas', [AsignaturaController::class, 'store'])->name('asignaturas.store');
    Route::get('asignaturas/{asignatura}', [AsignaturaController::class, 'show'])->name('asignaturas.show');
    Route::get('asignaturas/{asignatura}/documento', [AsignaturaController::class, 'documento'])->name('asignaturas.documento');
    Route::post('asignaturas/{asignatura}/documento', [AsignaturaController::class, 'subirPdf'])->name('asignaturas.documento.store');
    Route::post('asignaturas/{asignatura}/documento/procesar', [AsignaturaController::class, 'procesarPdf'])->name('asignaturas.documento.procesar');
    Route::post('asignaturas/{asignatura}/documento/aprender', [AsignaturaController::class, 'aprenderPrograma'])->name('asignaturas.documento.aprender');
    Route::get('asignaturas/{asignatura}/unidades', [AsignaturaController::class, 'unidades'])->name('asignaturas.unidades');
    Route::get('asignaturas/{asignatura}/actividades', [AsignaturaController::class, 'actividades'])->name('asignaturas.actividades');
    Route::post('asignaturas/{asignatura}/actividades/generar', [AsignaturaController::class, 'generarPlanificacion'])->name('asignaturas.actividades.generar');
    Route::post('asignaturas/{asignatura}/planificacion/guias', [AsignaturaController::class, 'generarGuia'])->name('asignaturas.guias.generar');
    Route::get('asignaturas/{asignatura}/guias/{guia}/descargar', [AsignaturaController::class, 'descargarGuia'])->name('asignaturas.guias.descargar');
    Route::post('asignaturas/{asignatura}/planificacion/presentaciones', [AsignaturaController::class, 'generarPresentacion'])->name('asignaturas.presentaciones.generar');
    Route::get('asignaturas/{asignatura}/presentaciones/{presentacion}/descargar', [AsignaturaController::class, 'descargarPresentacion'])->name('asignaturas.presentaciones.descargar');
    Route::get('asignaturas/{asignatura}/planificacion', [AsignaturaController::class, 'planificacion'])->name('asignaturas.planificacion');
    Route::get('asignaturas/{asignatura}/documento/descargar', [AsignaturaController::class, 'descargarPdf'])->name('asignaturas.documento.descargar');
    Route::get('asignaturas/{asignatura}/editar', [AsignaturaController::class, 'edit'])->name('asignaturas.edit');
    Route::put('asignaturas/{asignatura}', [AsignaturaController::class, 'update'])->name('asignaturas.update');
    Route::delete('asignaturas/{asignatura}', [AsignaturaController::class, 'destroy'])->name('asignaturas.destroy');
});
