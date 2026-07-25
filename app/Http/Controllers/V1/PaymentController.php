<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Models\Customer;
use App\Models\FinanceRecord;
use App\Models\Payment;
use App\Models\PaymentSale;
use App\Models\Sale;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Payment Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Payment Create', ['only' => ['store']]),
            new Middleware('permission:Payment Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a paginated listing of payments.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Payment::with(['customer:id,code,name,phone', 'creator:id,name', 'paymentSales.sale:id,reference_number,total_amount,due_amount']);

            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('customer_id') && $request->customer_id != '') {
                $query->byCustomer($request->customer_id);
            }

            if ($request->has('payment_method') && $request->payment_method != '') {
                $query->byPaymentMethod($request->payment_method);
            }

            if ($request->has('date_from') && $request->date_from != '') {
                $query->whereDate('payment_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to != '') {
                $query->whereDate('payment_date', '<=', $request->date_to);
            }

            $payments = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Payments retrieved successfully',
                'data' => $payments,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve payments',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created payment and run the FIFO Settlement Algorithm inside DB transaction.
     */
    public function store(CreatePaymentRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $authUser = auth()->user();

            $totalPaymentAmount = (float) $data['total_amount'];
            $customerId = (int) $data['customer_id'];
            $saleIds = $data['sale_ids'] ?? [];

            // Handle Proof Image Upload
            $proofImagePath = null;
            if ($request->hasFile('proof_image')) {
                $proofImagePath = $this->handleFileUpload($request, 'proof_image', null, 'payments');
            }

            // Create Payment record
            $payment = Payment::create([
                'customer_id' => $customerId,
                'cheque_id' => $data['cheque_id'] ?? null,
                'total_amount' => $totalPaymentAmount,
                'payment_method' => $data['payment_method'],
                'payment_date' => $data['payment_date'],
                'proof_image_path' => $proofImagePath,
                'notes' => $data['notes'] ?? null,
                'created_by' => $authUser->id ?? 1,
            ]);

            // Fetch open/partial sales for customer
            $salesQuery = Sale::where('customer_id', $customerId)
                ->whereIn('payment_status', ['unpaid', 'partial']);

            if (!empty($saleIds)) {
                $salesQuery->whereIn('id', $saleIds);
            }

            // FIFO Order: Oldest sales first
            $openSales = $salesQuery->orderBy('sale_date', 'asc')->orderBy('id', 'asc')->get();

            $remainingPool = $totalPaymentAmount;
            $totalAllocated = 0.00;

            foreach ($openSales as $sale) {
                if ($remainingPool <= 0) {
                    break;
                }

                $dueForSale = (float) $sale->due_amount;
                if ($dueForSale <= 0) {
                    continue;
                }

                if ($remainingPool >= $dueForSale) {
                    $allocation = $dueForSale;
                    $newPaid = $sale->total_amount;
                    $newDue = 0.00;
                    $newStatus = 'paid';
                    $remainingPool -= $dueForSale;
                } else {
                    $allocation = $remainingPool;
                    $newPaid = (float) $sale->paid_amount + $remainingPool;
                    $newDue = (float) $sale->total_amount - $newPaid;
                    $newStatus = 'partial';
                    $remainingPool = 0.00;
                }

                // Create PaymentSale pivot record
                PaymentSale::create([
                    'payment_id' => $payment->id,
                    'sale_id' => $sale->id,
                    'allocated_amount' => $allocation,
                ]);

                // Update Sale
                $sale->update([
                    'paid_amount' => $newPaid,
                    'due_amount' => $newDue,
                    'payment_status' => $newStatus,
                ]);

                $totalAllocated += $allocation;
            }

            // Decrement Customer Outstanding Balance
            $customer = Customer::find($customerId);
            if ($customer && $totalAllocated > 0) {
                $customer->decrement('outstanding_balance', min($customer->outstanding_balance, $totalAllocated));
            }

            // Create Income entry in FinanceRecord
            FinanceRecord::create([
                'record_type' => 'income',
                'reference_type' => 'Payment',
                'reference_id' => $payment->id,
                'amount' => $totalPaymentAmount,
                'description' => "Payment collected via {$payment->payment_method} from customer {$customer->name}",
                'record_date' => $payment->payment_date,
            ]);

            DB::commit();

            // Log activity
            $this->logActivity('CREATE', 'Payment', "Recorded payment of Rs. {$totalPaymentAmount} for customer ID {$customerId}", $request->validated());

            $payment->load(['customer:id,code,name,phone', 'paymentSales.sale:id,reference_number,total_amount,paid_amount,due_amount,payment_status']);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment recorded and allocated successfully',
                'data' => $payment,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record payment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(string $id)
    {
        try {
            $payment = Payment::with([
                'customer:id,code,name,phone,email,outstanding_balance',
                'cheque',
                'creator:id,name',
                'paymentSales.sale:id,reference_number,total_amount,paid_amount,due_amount,payment_status,sale_date',
            ])->find($id);

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Payment retrieved successfully',
                'data' => $payment,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve payment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified payment and reverse allocations.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $payment = Payment::with('paymentSales')->find($id);

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found',
                ], 404);
            }

            // Reverse sale allocations
            foreach ($payment->paymentSales as $ps) {
                $sale = Sale::find($ps->sale_id);
                if ($sale) {
                    $revertedPaid = max(0, (float) $sale->paid_amount - (float) $ps->allocated_amount);
                    $revertedDue = (float) $sale->total_amount - $revertedPaid;
                    $revertedStatus = $revertedPaid <= 0 ? 'unpaid' : 'partial';

                    $sale->update([
                        'paid_amount' => $revertedPaid,
                        'due_amount' => $revertedDue,
                        'payment_status' => $revertedStatus,
                    ]);
                }
            }

            // Re-increment Customer Outstanding Balance
            $customer = Customer::find($payment->customer_id);
            if ($customer) {
                $customer->increment('outstanding_balance', (float) $payment->total_amount);
            }

            // Delete FinanceRecord income entry
            FinanceRecord::where('reference_type', 'Payment')
                ->where('reference_id', $payment->id)
                ->delete();

            // Delete proof image if exists
            if (!empty($payment->proof_image_path)) {
                $this->deleteFile($payment->proof_image_path);
            }

            $paymentId = $payment->id;
            $payment->delete();

            DB::commit();

            $this->logActivity('DELETE', 'Payment', "Reversed payment ID: {$paymentId}");

            return response()->json([
                'status' => 'success',
                'message' => 'Payment deleted and allocations reversed successfully',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete payment',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
