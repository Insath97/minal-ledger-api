<?php

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\PermissionController;
use App\Http\Controllers\V1\RoleController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\BankController;
use App\Http\Controllers\V1\CustomerController;
use App\Http\Controllers\V1\SaleController;
use App\Http\Controllers\V1\ChequeController;
use App\Http\Controllers\V1\PaymentController;
use App\Http\Controllers\V1\ExpenseController;
use App\Http\Controllers\V1\FinanceController;
use App\Http\Controllers\V1\ActivityLogController;
use Illuminate\Support\Facades\Route;

/* public routes */

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

/* protected routes */
Route::middleware(['auth:api'])->prefix('v1')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::put('profile', [AuthController::class, 'updateProfile']);

    Route::get('permissions/list', [PermissionController::class, 'getPermissionList']);
    Route::apiResource('permissions', PermissionController::class);

    Route::get('roles/list/', [RoleController::class, 'getAvailableRoles']);
    Route::apiResource('roles', RoleController::class);

    Route::prefix('users')->group(function () {
        Route::get('list', [UserController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [UserController::class, 'toggleStatus']);
    });
    Route::apiResource('users', UserController::class);

    // Banks
    Route::prefix('banks')->group(function () {
        Route::get('list', [BankController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [BankController::class, 'toggleStatus']);
    });
    Route::apiResource('banks', BankController::class);

    // Customers
    Route::prefix('customers')->group(function () {
        Route::get('list', [CustomerController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [CustomerController::class, 'toggleStatus']);
    });
    Route::apiResource('customers', CustomerController::class);

    // Sales
    Route::prefix('sales')->group(function () {
        Route::get('list', [SaleController::class, 'getActiveList']);
    });
    Route::apiResource('sales', SaleController::class);

    // Cheques
    Route::prefix('cheques')->group(function () {
        Route::get('list', [ChequeController::class, 'getActiveList']);
        Route::patch('{id}/status', [ChequeController::class, 'updateStatus']);
    });
    Route::apiResource('cheques', ChequeController::class);

    // Payments (Bulk FIFO Settlement)
    Route::apiResource('payments', PaymentController::class);

    // Expenses
    Route::prefix('expenses')->group(function () {
        Route::get('summary', [ExpenseController::class, 'getSummary']);
    });
    Route::apiResource('expenses', ExpenseController::class);

    // Finance & Reports
    Route::prefix('finance')->group(function () {
        Route::get('dashboard', [FinanceController::class, 'getDashboard']);
        Route::get('pnl', [FinanceController::class, 'getPnL']);
        Route::get('income-breakdown', [FinanceController::class, 'getIncomeBreakdown']);
        Route::get('expense-breakdown', [FinanceController::class, 'getExpenseBreakdown']);
        Route::get('dues-aging', [FinanceController::class, 'getDuesAging']);
    });

    // Activity Logs (Read-only: Get All and Get By ID)
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('{id}', [ActivityLogController::class, 'show']);
    });
});
