<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ActivityLogController extends Controller implements HasMiddleware
{
    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ActivityLog Index', ['only' => ['index', 'show']]),
        ];
    }

    /**
     * Display a listing of activity logs (Get All).
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ActivityLog::with(['user:id,name,username,email']);

            // Apply Search Scope
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filters
            if ($request->has('module') && $request->module != '') {
                $query->byModule($request->module);
            }

            if ($request->has('action') && $request->action != '') {
                $query->byAction($request->action);
            }

            if ($request->has('level') && $request->level != '') {
                $query->byLevel($request->level);
            }

            if ($request->has('user_id') && $request->user_id != '') {
                $query->byUser($request->user_id);
            }

            if ($request->has('start_date') || $request->has('end_date')) {
                $query->dateRange($request->get('start_date'), $request->get('end_date'));
            }

            $logs = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Activity logs retrieved successfully',
                'data' => $logs,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity logs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get distinct modules.
     */
    public function getModules()
    {
        try {
            $modules = ActivityLog::distinct()->pluck('module')->filter()->values();

            return response()->json([
                'status' => 'success',
                'data' => $modules,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve modules',
            ], 500);
        }
    }

    /**
     * Get distinct actions.
     */
    public function getActions()
    {
        try {
            $actions = ActivityLog::distinct()->pluck('action')->filter()->values();

            return response()->json([
                'status' => 'success',
                'data' => $actions,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve actions',
            ], 500);
        }
    }

    /**
     * Display the specified activity log (Get By ID).
     */
    public function show(string $id)
    {
        try {
            $log = ActivityLog::with(['user:id,name,username,email'])->find($id);

            if (!$log) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Activity log not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Activity log retrieved successfully',
                'data' => $log,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
