<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
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
Route::get('/opnavi', fn () => redirect()->route('customers.index'))->name('opnavi');
Route::get('/opnavi/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/opnavi/customers', [CustomerController::class, 'index'])->name('customers.index');
Route::post('/opnavi/customers/import', [CustomerController::class, 'import'])->name('customers.import');
Route::patch('/opnavi/customers/bulk-owner', [CustomerController::class, 'bulkUpdateOwner'])->name('customers.bulk-owner');

$missingCustomer = function (Request $request) {
    $message = '対象の顧客が見つかりません。別タブで削除された可能性があります。一覧を再読み込みしてください。';

    if ($request->ajax()) {
        return response($message, 404);
    }

    return redirect()->route('customers.index')->with('error', $message);
};

Route::get('/opnavi/customers/{customer}', [CustomerController::class, 'show'])->missing($missingCustomer)->name('customers.show');
Route::patch('/opnavi/customers/{customer}', [CustomerController::class, 'update'])->missing($missingCustomer)->name('customers.update');
Route::delete('/opnavi/customers/{customer}', [CustomerController::class, 'destroy'])->missing($missingCustomer)->name('customers.destroy');
Route::post('/opnavi/customers/{customer}/activities', [CustomerController::class, 'storeActivity'])->missing($missingCustomer)->name('customers.activities.store');
Route::patch('/opnavi/customers/{customer}/activities/{activity}', [CustomerController::class, 'updateActivity'])->missing($missingCustomer)->name('customers.activities.update');
