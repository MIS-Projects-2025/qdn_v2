<?php
//Template
use App\Http\Controllers\General\AdminController;
use App\Http\Controllers\General\ProfileController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SystemStatusController;

$app_name = env('APP_NAME', '');

Route::redirect('/', "/$app_name");

Route::post('/session/close-beacon', [\App\Http\Controllers\SessionController::class, 'closeBeacon'])
  ->name('session.close-beacon');

Route::get('/session/check', [\App\Http\Controllers\SessionController::class, 'check'])
  ->name('session.check');
Route::post('/session/lock', [\App\Http\Controllers\SessionController::class, 'lock'])->name('session.lock');
Route::post('/session/touch', [\App\Http\Controllers\SessionController::class, 'touch'])->name('session.touch');

Route::prefix($app_name)->middleware(AuthMiddleware::class)->group(function () {

  Route::middleware(AdminMiddleware::class)->group(function () {
    Route::get("/admin", [AdminController::class, 'index'])->name('admin');
    Route::get("/new-admin", [AdminController::class, 'index_addAdmin'])->name('index_addAdmin');
    Route::post("/add-admin", [AdminController::class, 'addAdmin'])->name('addAdmin');
    Route::post("/remove-admin", [AdminController::class, 'removeAdmin'])->name('removeAdmin');
    Route::patch("/change-admin-role", [AdminController::class, 'changeAdminRole'])->name('changeAdminRole');


    // Maintenance toggle (Sidebar). AuthMiddleware lets these two through
    // even while the app is in maintenance, so an admin can turn it off.
    Route::post("/system-status/maintenance", [SystemStatusController::class, 'maintenance'])->name('system-status.maintenance');
    Route::post("/system-status/online", [SystemStatusController::class, 'online'])->name('system-status.online');
  });

  Route::get("/", [DashboardController::class, 'index'])->name('dashboard');
  Route::get("/profile", [ProfileController::class, 'index'])->name('profile.index');
  Route::post("/change-password", [ProfileController::class, 'changePassword'])->name('changePassword');
  Route::post("/verify-password", [ProfileController::class, 'verifyPassword'])->name('verifyPassword');
});
