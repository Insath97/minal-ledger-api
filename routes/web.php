<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Landing page
Route::get('/', function () {
    return view('api-landing');
});

// Root endpoint (JSON fallback)
Route::get('/api', function () {
    return response()->json([
        'message' => 'Minal Ledger API',
        'version' => '1.0.0',
        'health' => '/health-check',
        'baseUrl' => env('APP_URL', 'http://localhost'),
    ]);
});

// Comprehensive health check
Route::get('/health', function () {
    $currentDateTime = now();
    $status = [
        'status' => 'healthy',
        'date' => $currentDateTime->toDateString(),
        'time' => $currentDateTime->toTimeString(),
        'service' => 'Minal Ledger API System',
        'components' => []
    ];

    // Check database
    try {
        DB::select('SELECT 1');
        $status['components']['database'] = 'healthy';
    } catch (\Exception $e) {
        $status['components']['database'] = 'unhealthy';
        $status['status'] = 'degraded';
    }

    return response()->json($status);
});
