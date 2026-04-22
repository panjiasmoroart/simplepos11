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
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductStockController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StockOpnameController;

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
            'units' => [
                'controller' => UnitController::class,
                'permissions' => 'units.index|units.create|units.edit|units.delete',
                'name' => 'units'
            ],
            'products' => [
                'controller' => ProductController::class,
                'permissions' => 'products.index|products.create|products.edit|products.delete',
                'name' => 'products'
            ],
            'stocks' => [
                'controller' => ProductStockController::class,
                'permissions' => 'stocks.index|stocks.create|stocks.edit|stocks.delete',
                'name' => 'stocks'
            ],
            'stock-opnames' => [
                'controller' => StockOpnameController::class,
                'permissions' => 'stock-opnames.index|stock-opnames.create|stock-opnames.edit|stock-opnames.show',
                'name' => 'stock-opnames'
            ],
        ];

        foreach ($resources as $name => $resource) {
            $route = Route::resource($name, $resource['controller'])
                ->middleware("permission:{$resource['permissions']}");
            if (isset($resource['names'])) {
                $route->names($resource['names']);
            }
        }

        Route::prefix('sales')->name('sales.')->middleware('permission:transactions.index')->group(function () {
            Route::get('/', [TransactionController::class, 'index'])->name('index');
            Route::post('/add-product', [TransactionController::class, 'addProductToCart'])->name('add-product');
            Route::delete('/delete-from-cart/{id}', [TransactionController::class, 'deleteFromCart'])->name('delete-from-cart');
            Route::post('/process-payment', [TransactionController::class, 'processPayment'])->name('process-payment');
            Route::post('/get-snap-token', [TransactionController::class, 'getSnapToken'])->name('get-snap-token');
        });

        Route::prefix('report')->name('report.')->middleware('permission:reports.index')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/generate', [ReportController::class, 'generate'])->name('generate');
        });

        Route::get('/stock-opnames/{id}/export', [StockOpnameController::class, 'export'])
            ->name('stock-opnames.export');

        // Utility Routes
        Route::get('/get-cities/{provinceId}', [SupplierController::class, 'getCitiesByProvince'])
            ->middleware('permission:suppliers.index')
            ->name('get-cities');

        Route::post('/get-courier-cost', [ProductStockController::class, 'getCourierCost'])->name('get-courier-cost')
            ->middleware('permission:stocks.index');
});
