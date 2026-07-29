<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\DatabaseService;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    protected DatabaseService $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    /**
     * Define middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Database Export', only: ['export']),
        ];
    }

    /**
     * Export the database and download the SQL dump file (Protected).
     */
    public function export(): BinaryFileResponse|JsonResponse
    {
        try {
            $this->logActivity('EXPORT', 'Database', 'Database backup exported');

            $filePath = $this->databaseService->export();
            $filename = basename($filePath);

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $th) {
            $this->logActivity('ERROR', 'Database', "Database export failure: " . $th->getMessage(), null, 'error');

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export database.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
