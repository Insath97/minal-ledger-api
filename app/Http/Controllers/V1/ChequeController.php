<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateChequeRequest;
use App\Http\Requests\UpdateChequeStatusRequest;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Sale;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ChequeController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Cheque Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Cheque List', ['only' => ['getActiveList']]),
            new Middleware('permission:Cheque Create', ['only' => ['store']]),
            new Middleware('permission:Cheque Update Status', ['only' => ['updateStatus']]),
            new Middleware('permission:Cheque Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of cheques with filters.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Cheque::with(['customer:id,code,name,phone', 'sale:id,reference_number,total_amount,due_amount', 'creator:id,name']);

            // Search Filter
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Status Filter
            if ($request->has('status') && $request->status != '') {
                $query->byStatus($request->status);
            }

            // Customer Filter
            if ($request->has('customer_id') && $request->customer_id != '') {
                $query->byCustomer($request->customer_id);
            }

            // Date Range Filters
            if ($request->has('date_from') && $request->date_from != '') {
                $query->whereDate('cheque_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to != '') {
                $query->whereDate('cheque_date', '<=', $request->date_to);
            }

            $cheques = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Cheques retrieved successfully',
                'data' => $cheques,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve cheques',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created pending cheque.
     */
    public function store(CreateChequeRequest $request)
    {
        try {
            $data = $request->validated();
            $authUser = auth()->user();

            // Handle Cheque Image Upload
            if ($request->hasFile('cheque_image')) {
                $data['cheque_image'] = $this->handleFileUpload($request, 'cheque_image', null, 'cheques');
            }

            $data['status'] = 'pending';
            $data['created_by'] = $authUser->id ?? 1;

            $cheque = Cheque::create($data);

            // Log Activity
            $this->logActivity('CREATE', 'Cheque', "Recorded pending cheque #{$cheque->cheque_number} for customer ID {$cheque->customer_id}", $data);

            $cheque->load(['customer:id,code,name,phone', 'sale:id,reference_number']);

            return response()->json([
                'status' => 'success',
                'message' => 'Cheque recorded successfully in PENDING status',
                'data' => $cheque,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record cheque',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified cheque details.
     */
    public function show(string $id)
    {
        try {
            $cheque = Cheque::with([
                'customer:id,code,name,phone,email,outstanding_balance',
                'sale:id,reference_number,total_amount,paid_amount,due_amount,payment_status',
                'creator:id,name',
                'updater:id,name',
            ])->find($id);

            if (!$cheque) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cheque not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Cheque retrieved successfully',
                'data' => $cheque,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve cheque',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update cheque status (State Machine: pending -> cleared / bounced / cancelled).
     */
    public function updateStatus(UpdateChequeStatusRequest $request, string $id)
    {
        DB::beginTransaction();
        try {
            $cheque = Cheque::find($id);

            if (!$cheque) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cheque not found',
                ], 404);
            }

            if ($cheque->status === 'cleared') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cleared cheques cannot have their status altered to preserve audit integrity',
                ], 422);
            }

            $data = $request->validated();
            $newStatus = $data['status'];
            $clearanceDate = $data['clearance_date'] ?? now()->toDateString();
            $notes = $data['notes'] ?? $cheque->notes;

            // State Machine Logic
            if ($newStatus === 'cleared') {
                $cheque->status = 'cleared';
                $cheque->clearance_date = $clearanceDate;
                $cheque->notes = $notes;
                $cheque->updated_by = auth()->id() ?? 1;
                $cheque->save();

                // 1. Decrement Customer Outstanding Balance
                $customer = Customer::find($cheque->customer_id);
                if ($customer) {
                    $customer->decrement('outstanding_balance', min($customer->outstanding_balance, $cheque->amount));
                }

                // 2. Adjust attached sale if sale_id exists
                if ($cheque->sale_id) {
                    $sale = Sale::find($cheque->sale_id);
                    if ($sale && $sale->due_amount > 0) {
                        $allocatedAmount = min($sale->due_amount, $cheque->amount);
                        $newPaidAmount = $sale->paid_amount + $allocatedAmount;
                        $newDueAmount = max(0, $sale->total_amount - $newPaidAmount);

                        $paymentStatus = $newDueAmount <= 0 ? 'paid' : 'partial';

                        $sale->update([
                            'paid_amount' => $newPaidAmount,
                            'due_amount' => $newDueAmount,
                            'payment_status' => $paymentStatus,
                        ]);
                    }
                }

                $this->logActivity('CHEQUE_CLEARED', 'Cheque', "Cheque #{$cheque->cheque_number} marked CLEARED. Amount: {$cheque->amount}", $data);
            } elseif ($newStatus === 'bounced') {
                $cheque->status = 'bounced';
                $cheque->notes = $notes;
                $cheque->updated_by = auth()->id() ?? 1;
                $cheque->save();

                $this->logActivity('CHEQUE_BOUNCED', 'Cheque', "Cheque #{$cheque->cheque_number} marked BOUNCED. Amount: {$cheque->amount}", $data);
            } else {
                // Cancelled
                $cheque->status = 'cancelled';
                $cheque->notes = $notes;
                $cheque->updated_by = auth()->id() ?? 1;
                $cheque->save();

                $this->logActivity('CHEQUE_CANCELLED', 'Cheque', "Cheque #{$cheque->cheque_number} marked CANCELLED", $data);
            }

            DB::commit();

            $cheque->load(['customer:id,code,name,phone', 'sale:id,reference_number,payment_status']);

            return response()->json([
                'status' => 'success',
                'message' => "Cheque status updated to " . strtoupper($newStatus),
                'data' => $cheque,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update cheque status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified pending cheque.
     */
    public function destroy(string $id)
    {
        try {
            $cheque = Cheque::find($id);

            if (!$cheque) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cheque not found',
                ], 404);
            }

            if ($cheque->status === 'cleared') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete a cleared cheque',
                ], 422);
            }

            $chequeNum = $cheque->cheque_number;

            // Delete cheque image if exists
            if (!empty($cheque->cheque_image)) {
                $this->deleteFile($cheque->cheque_image);
            }

            $cheque->delete();

            $this->logActivity('DELETE', 'Cheque', "Deleted cheque #{$chequeNum}");

            return response()->json([
                'status' => 'success',
                'message' => 'Cheque deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete cheque',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get active list of pending cheques for dropdowns.
     */
    public function getActiveList(Request $request)
    {
        try {
            $query = Cheque::pending();

            if ($request->has('customer_id') && $request->customer_id != '') {
                $query->byCustomer($request->customer_id);
            }

            $cheques = $query->orderBy('cheque_date', 'asc')
                ->get(['id', 'cheque_number', 'bank_name', 'amount', 'cheque_date', 'customer_id', 'sale_id', 'status']);

            return response()->json([
                'status' => 'success',
                'message' => 'Pending cheques retrieved successfully',
                'data' => $cheques,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve cheques list',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
