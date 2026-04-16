<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CategoryController;

// Route::get('/', function () {
//     return view('welcome');
// });

// Route::inertia('/home', 'Home');

// not working in laravel11
// Route::redirect('/login', '/');

// Root Route
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->to('/login');
});

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

// Logout Route
Route::post('/logout', [LogoutController::class, '__invoke'])
    ->middleware('auth')
    ->name('logout');


// Admin Routes
Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {

    Route::get('/dashboard', DashboardController::class)
        // hanya pengguna dengan izin 'dashboard.index' yang dapat mengakses dashboard
        ->middleware('permission:dashboard.index')
        ->name('dashboard');

        $resources = [
            'roles' => [
                'controller' => RoleController::class,
                'permissions' => 'roles.index|roles.create|roles.edit|roles.delete',
                'names' => 'roles'
            ],
            'users' => [
                'controller' => UserController::class,
                'permissions' => 'users.index|users.create|users.edit|users.delete',
                'name' => 'users'
            ],
            'suppliers' => [
                'controller' => SupplierController::class,
                'permissions' => 'suppliers.index|suppliers.create|suppliers.edit|suppliers.delete'
            ],
            'customers' => [
                'controller' => CustomerController::class,
                'permissions' => 'customers.index|customers.create|customers.edit|customers.delete'
            ],
            'categories' => [
                'controller' => CategoryController::class,
                'permissions' => 'categories.index|categories.create|categories.edit|categories.delete',
                'name' => 'categories'
            ],
        ];

        foreach ($resources as $name => $resource) {
            $route = Route::resource($name, $resource['controller'])
                ->middleware("permission:{$resource['permissions']}");
            if (isset($resource['names'])) {
                $route->names($resource['names']);
            }
        }

        // Utility Routes
        Route::get('/get-cities/{provinceId}', [SupplierController::class, 'getCitiesByProvince'])
            ->middleware('permission:suppliers.index')
            ->name('get-cities');
});
