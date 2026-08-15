<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', fn () => redirect()->route('admin.login'))->name('login');
Route::get('/opnavi', fn () => redirect()->route('customers.index'))->name('opnavi');
Route::get('/opnavi/user', [HomeController::class, 'user'])->name('user.home');
Route::get('/opnavi/admin/login', [LoginController::class, 'admin'])->name('admin.login');
Route::post('/opnavi/admin/login', [LoginController::class, 'authenticateAdmin'])->name('admin.login.submit');
Route::get('/opnavi/user/login', [LoginController::class, 'user'])->name('user.login');
Route::post('/opnavi/user/login', [LoginController::class, 'authenticateUser'])->name('user.login.submit');
Route::get('/opnavi/initial_setup', [LoginController::class, 'editInitialSetup'])->middleware('auth')->name('initial-setup.edit');
Route::post('/opnavi/initial_setup', [LoginController::class, 'updateInitialSetup'])->middleware('auth')->name('initial-setup.update');
Route::post('/opnavi/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'initial.setup'])->group(function () {
    Route::get('/opnavi/password/change', [LoginController::class, 'editPassword'])->name('password.edit');
    Route::post('/opnavi/password/change', [LoginController::class, 'updatePassword'])->name('password.update');
});
$missingCustomer = function (Request $request) {
    $message = '対象の顧客が見つかりません。別タブで削除された可能性があります。一覧を再読み込みしてください。';

    if ($request->ajax()) {
        return response($message, 404);
    }

    $indexRoute = $request->is('opnavi/user*') ? 'user.customers.index' : 'customers.index';

    return redirect()->route($indexRoute)->with('error', $message);
};

Route::middleware(['auth', 'initial.setup', 'opnavi.role:admin'])->group(function () use ($missingCustomer) {
    Route::get('/opnavi/dashboard', fn () => redirect()->route('dashboard'));
    Route::get('/opnavi/customers', fn (Request $request) => redirect()->route('customers.index', $request->query()));
    Route::get('/opnavi/customers/{customer}', fn (Request $request, string $customer) => redirect()->route(
        'customers.show',
        array_merge(['customer' => $customer], $request->query())
    ));

    Route::get('/opnavi/admin/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/opnavi/admin/user_management', [UserManagementController::class, 'index'])->name('admin.user-management.index');
    Route::post('/opnavi/admin/user_management', [UserManagementController::class, 'store'])->name('admin.user-management.store');
    Route::get('/opnavi/admin/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/opnavi/admin/customers/import', [CustomerController::class, 'import'])->name('customers.import');
    Route::patch('/opnavi/admin/customers/bulk-owner', [CustomerController::class, 'bulkUpdateOwner'])->name('customers.bulk-owner');
    Route::get('/opnavi/admin/customers/{customer}', [CustomerController::class, 'show'])->missing($missingCustomer)->name('customers.show');
    Route::patch('/opnavi/admin/customers/{customer}', [CustomerController::class, 'update'])->missing($missingCustomer)->name('customers.update');
    Route::delete('/opnavi/admin/customers/{customer}', [CustomerController::class, 'destroy'])->missing($missingCustomer)->name('customers.destroy');
    Route::post('/opnavi/admin/customers/{customer}/activities', [CustomerController::class, 'storeActivity'])->missing($missingCustomer)->name('customers.activities.store');
    Route::patch('/opnavi/admin/customers/{customer}/activities/{activity}', [CustomerController::class, 'updateActivity'])->missing($missingCustomer)->name('customers.activities.update');
    Route::delete('/opnavi/admin/customers/{customer}/activities/{activity}', [CustomerController::class, 'destroyActivity'])->missing($missingCustomer)->name('customers.activities.destroy');
});

Route::middleware(['auth', 'initial.setup', 'opnavi.role:appointment,sales'])->group(function () use ($missingCustomer) {
    Route::get('/opnavi/user/customers', [CustomerController::class, 'userIndex'])->name('user.customers.index');
    Route::get('/opnavi/user/customers/{customer}', [CustomerController::class, 'userShow'])->missing($missingCustomer)->name('user.customers.show');
    Route::patch('/opnavi/user/customers/{customer}', [CustomerController::class, 'userUpdate'])->missing($missingCustomer)->name('user.customers.update');
    Route::post('/opnavi/user/customers/{customer}/activities', [CustomerController::class, 'userStoreActivity'])->missing($missingCustomer)->name('user.customers.activities.store');
    Route::patch('/opnavi/user/customers/{customer}/activities/{activity}', [CustomerController::class, 'userUpdateActivity'])->missing($missingCustomer)->name('user.customers.activities.update');
});
