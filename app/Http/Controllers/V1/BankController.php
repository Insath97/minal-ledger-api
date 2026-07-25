<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Models\Bank;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BankController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Bank Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Bank List', ['only' => ['getActiveList']]),
            new Middleware('permission:Bank Create', ['only' => ['store']]),
            new Middleware('permission:Bank Update', ['only' => ['update']]),
            new Middleware('permission:Bank Delete', ['only' => ['destroy']]),
            new Middleware('permission:Bank Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of banks.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Bank::query();

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $banks = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Banks retrieved successfully',
                'data' => $banks,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve banks',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created bank in storage.
     */
    public function store(CreateBankRequest $request)
    {
        try {
            $data = $request->validated();
            $bank = Bank::create($data);

            $this->logActivity('CREATE', 'Bank', "Created bank: {$bank->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Bank created successfully',
                'data' => $bank,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create bank',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified bank.
     */
    public function show(string $id)
    {
        try {
            $bank = Bank::find($id);

            if (! $bank) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bank not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Bank retrieved successfully',
                'data' => $bank,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve bank',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified bank in storage.
     */
    public function update(UpdateBankRequest $request, string $id)
    {
        try {
            $bank = Bank::find($id);

            if (! $bank) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bank not found',
                ], 404);
            }

            $data = $request->validated();
            $bank->update($data);

            $this->logActivity('UPDATE', 'Bank', "Updated bank: {$bank->name}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Bank updated successfully',
                'data' => $bank,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update bank',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified bank from storage.
     */
    public function destroy(string $id)
    {
        try {
            $bank = Bank::find($id);

            if (! $bank) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bank not found',
                ], 404);
            }

            $bankName = $bank->name;
            $bank->delete();

            $this->logActivity('DELETE', 'Bank', "Deleted bank: {$bankName}");

            return response()->json([
                'status' => 'success',
                'message' => 'Bank deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete bank',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a list of all active banks (lightweight list).
     */
    public function getActiveList()
    {
        try {
            $banks = Bank::active()->orderBy('name', 'asc')->get(['id', 'name', 'code']);

            if ($banks->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active banks found',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Active banks retrieved successfully',
                'data' => $banks,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active banks',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the status of the bank.
     */
    public function toggleStatus(string $id)
    {
        try {
            $bank = Bank::find($id);

            if (!$bank) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bank not found'
                ], 404);
            }

            $bank->is_active = !$bank->is_active;
            $bank->save();

            $this->logActivity('TOGGLE_STATUS', 'Bank', "Toggled bank status: {$bank->name} (" . ($bank->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'Bank status updated successfully',
                'data' => [
                    'id' => $bank->id,
                    'is_active' => $bank->is_active
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle bank status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
