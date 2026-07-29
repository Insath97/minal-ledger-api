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
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\ActivityLogController;
use App\Http\Controllers\V1\ReportController;
use Illuminate\Support\Facades\Route;

/* public routes */

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
});

/* protected routes */
Route::middleware(['auth:api'])->prefix('v1')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('profile', [AuthController::class, 'updateProfile']);

    Route::get('permissions/list', [PermissionController::class, 'getPermissionList']);
    Route::apiResource('permissions', PermissionController::class);

    Route::get('roles/list/', [RoleController::class, 'getAvailableRoles']);
    Route::apiResource('roles', RoleController::class);

    Route::prefix('users')->group(function () {
        Route::get('list', [UserController::class, 'getActiveList']);
        Route::patch('{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::patch('{id}/toggle-can-login', [UserController::class, 'toggleCanLogin']);
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
    Route::apiResource('expenses', ExpenseController::class);

    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('dashboard/analytics', [DashboardController::class, 'getAnalytics']);
    Route::get('dashboard/activity', [DashboardController::class, 'getActivity']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('pnl', [ReportController::class, 'pnl']);
        Route::get('sales', [ReportController::class, 'salesReport']);
        Route::get('customer-statement', [ReportController::class, 'customerStatement']);
        Route::get('cheques', [ReportController::class, 'chequeReport']);
        Route::get('payments', [ReportController::class, 'paymentReport']);
        Route::get('expense-summary', [ReportController::class, 'expenseSummary']);
        Route::get('monthly-summary', [ReportController::class, 'monthlySummary']);
        Route::get('dues-aging', [ReportController::class, 'duesAging']);
    });

    // Activity Logs (Read-only: Get All and Get By ID)
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('modules', [ActivityLogController::class, 'getModules']);
        Route::get('actions', [ActivityLogController::class, 'getActions']);
        Route::get('{id}', [ActivityLogController::class, 'show']);
    });
});
