<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Cheque;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Sale Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Sale List', ['only' => ['getActiveList']]),
            new Middleware('permission:Sale Create', ['only' => ['store']]),
            new Middleware('permission:Sale Update', ['only' => ['update']]),
            new Middleware('permission:Sale Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of sales.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Sale::with(['customer:id,code,name,phone', 'creator:id,name']);

            // Search Filter
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Business Type Filter
            if ($request->has('business_type') && $request->business_type != '') {
                $query->byBusinessType($request->business_type);
            }

            // Payment Status Filter
            if ($request->has('payment_status') && $request->payment_status != '') {
                $query->byPaymentStatus($request->payment_status);
            }

            // Customer Filter
            if ($request->has('customer_id') && $request->customer_id != '') {
                $query->byCustomer($request->customer_id);
            }

            // Date Range Filters
            if ($request->has('date_from') && $request->date_from != '') {
                $query->whereDate('sale_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to != '') {
                $query->whereDate('sale_date', '<=', $request->date_to);
            }

            $sales = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Sales retrieved successfully',
                'data' => $sales,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sales',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created sale in storage inside DB transaction.
     */
    public function store(CreateSaleRequest $request)
    {
        DB::beginTransaction();
        try {
            $authUser = auth()->user();
            $data = $request->validated();

            $totalAmount = (float) $data['total_amount'];
            $paidAmount = (float) ($data['paid_amount'] ?? 0);
            $dueAmount = max(0, $totalAmount - $paidAmount);

            // Determine payment status
            if ($paidAmount >= $totalAmount) {
                $paymentStatus = 'paid';
                $dueAmount = 0.00;
                $paidAmount = $totalAmount;
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
                $paidAmount = 0.00;
            }

            // Handle Bill Image Upload
            if ($request->hasFile('bill_image')) {
                $data['bill_image'] = $this->handleFileUpload($request, 'bill_image', null, 'sales');
            }

            $data['paid_amount'] = $paidAmount;
            $data['due_amount'] = $dueAmount;
            $data['payment_status'] = $paymentStatus;
            $data['created_by'] = $authUser->id ?? 1;

            // Remove non-table inputs before creating sale
            $paymentMethod = $data['payment_method'] ?? null;
            $chequeNumber = $data['cheque_number'] ?? null;
            $bankName = $data['bank_name'] ?? null;
            $chequeDate = $data['cheque_date'] ?? null;
            $chequeAmount = $data['cheque_amount'] ?? null;

            unset(
                $data['payment_method'],
                $data['cheque_number'],
                $data['bank_name'],
                $data['cheque_date'],
                $data['cheque_amount']
            );

            // Create Sale record
            $sale = Sale::create($data);

            // Update Customer Outstanding Balance if there is a due amount & customer attached
            if (!empty($sale->customer_id) && $dueAmount > 0) {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->increment('outstanding_balance', $dueAmount);
                }
            }

            // Handle Cheque record creation if initial payment is via cheque
            if ($paymentMethod === 'cheque' && !empty($chequeNumber) && !empty($sale->customer_id)) {
                $chequeImagePath = null;
                if ($request->hasFile('cheque_image')) {
                    $chequeImagePath = $this->handleFileUpload($request, 'cheque_image', null, 'cheques');
                }

                Cheque::create([
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'cheque_number' => $chequeNumber,
                    'bank_name' => $bankName,
                    'cheque_date' => $chequeDate,
                    'amount' => $chequeAmount ?? $paidAmount,
                    'cheque_image' => $chequeImagePath,
                    'status' => 'pending',
                    'notes' => "Initial cheque deposit for sale ref {$sale->reference_number}",
                    'created_by' => $authUser->id ?? 1,
                ]);
            }

            DB::commit();

            // Log activity
            $this->logActivity('CREATE', 'Sale', "Created sale: {$sale->reference_number} for amount {$sale->total_amount}", $request->validated());

            $sale->load(['customer:id,code,name,phone', 'creator:id,name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Sale created successfully',
                'data' => $sale,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create sale',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified sale.
     */
    public function show(string $id)
    {
        try {
            $sale = Sale::with([
                'customer:id,code,name,email,phone,address_line1,city,outstanding_balance',
                'creator:id,name,username',
                'paymentSales.payment',
                'cheques',
            ])->find($id);

            if (!$sale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sale not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sale retrieved successfully',
                'data' => $sale,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sale',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update specified sale metadata (invoice_number, bill_image, notes, sale_date).
     */
    public function update(UpdateSaleRequest $request, string $id)
    {
        try {
            $sale = Sale::find($id);

            if (!$sale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sale not found',
                ], 404);
            }

            $data = $request->validated();

            // Handle bill image update & deletion
            if ($request->hasFile('bill_image')) {
                $data['bill_image'] = $this->handleFileUpload($request, 'bill_image', $sale->bill_image, 'sales');
            } elseif ($request->exists('bill_image') && empty($request->bill_image)) {
                $this->deleteFile($sale->bill_image);
                $data['bill_image'] = null;
            } else {
                unset($data['bill_image']);
            }

            $data['updated_by'] = auth()->id() ?? 1;

            $sale->update($data);

            $this->logActivity('UPDATE', 'Sale', "Updated metadata for sale ref: {$sale->reference_number}", $request->validated());

            $sale->load(['customer:id,code,name,phone', 'creator:id,name']);

            return response()->json([
                'status' => 'success',
                'message' => 'Sale updated successfully',
                'data' => $sale,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update sale',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified sale from storage if unpaid and no payments attached.
     */
    public function destroy(string $id)
    {
        try {
            $sale = Sale::find($id);

            if (!$sale) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sale not found',
                ], 404);
            }

            if ($sale::where('id', $id)->whereHas('paymentSales')->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete sale that has settled payment allocations',
                ], 422);
            }

            DB::transaction(function () use ($sale) {
                // Adjust customer balance if due amount was added
                if (!empty($sale->customer_id) && $sale->due_amount > 0) {
                    $customer = Customer::find($sale->customer_id);
                    if ($customer) {
                        $customer->decrement('outstanding_balance', min($customer->outstanding_balance, $sale->due_amount));
                    }
                }

                // Delete bill image file
                if (!empty($sale->bill_image)) {
                    $this->deleteFile($sale->bill_image);
                }

                $saleRef = $sale->reference_number;
                $sale->delete();

                $this->logActivity('DELETE', 'Sale', "Deleted sale: {$saleRef}");
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Sale deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete sale',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get active list of sales for dropdowns.
     */
    public function getActiveList(Request $request)
    {
        try {
            $query = Sale::whereIn('payment_status', ['unpaid', 'partial']);

            if ($request->has('customer_id') && $request->customer_id != '') {
                $query->where('customer_id', $request->customer_id);
            }

            $sales = $query->orderBy('id', 'desc')
                ->get(['id', 'reference_number', 'invoice_number', 'customer_id', 'total_amount', 'paid_amount', 'due_amount', 'payment_status', 'sale_date']);

            return response()->json([
                'status' => 'success',
                'message' => 'Unpaid and partial sales retrieved successfully',
                'data' => $sales,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sales list',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
