<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Reports', ['only' => [
                'salesReport', 'customerStatement', 'chequeReport',
                'paymentReport', 'expenseSummary', 'monthlySummary', 'duesAging', 'pnl',
            ]]),
        ];
    }

    /**
     * Sales Report — filterable by date range, customer, business_type, payment_status
     */
    public function salesReport(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', now()->startOfYear()->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());
            $customerId = $request->get('customer_id');
            $businessType = $request->get('business_type');
            $paymentStatus = $request->get('payment_status');

            $query = Sale::with('customer:id,code,name,phone')
                ->whereBetween('sale_date', [$dateFrom, $dateTo]);

            if ($customerId) $query->where('customer_id', $customerId);
            if ($businessType) $query->where('business_type', $businessType);
            if ($paymentStatus) $query->where('payment_status', $paymentStatus);

            $sales = $query->orderBy('sale_date', 'desc')->get();

            $summary = [
                'total_sales' => (float) $sales->sum('total_amount'),
                'total_paid' => (float) $sales->sum('paid_amount'),
                'total_due' => (float) $sales->sum('due_amount'),
                'count' => $sales->count(),
                'paid_count' => $sales->where('payment_status', 'paid')->count(),
                'partial_count' => $sales->where('payment_status', 'partial')->count(),
                'unpaid_count' => $sales->where('payment_status', 'unpaid')->count(),
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Sales report retrieved successfully',
                'data' => [
                    'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                    'summary' => $summary,
                    'sales' => $sales,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sales report',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Customer Statement — full transaction history for a customer
     */
    public function customerStatement(Request $request): JsonResponse
    {
        try {
            $customerId = $request->get('customer_id');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            if (!$customerId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'customer_id is required',
                ], 422);
            }

            $customer = Customer::findOrFail($customerId);

            // Sales
            $salesQuery = Sale::where('customer_id', $customerId)
                ->orderBy('sale_date', 'desc');
            if ($dateFrom) $salesQuery->where('sale_date', '>=', $dateFrom);
            if ($dateTo) $salesQuery->where('sale_date', '<=', $dateTo);
            $sales = $salesQuery->get()->map(fn (Sale $s) => [
                'type' => 'sale',
                'date' => $s->sale_date,
                'reference' => $s->reference_number,
                'description' => "Sale - {$s->reference_number}",
                'debit' => (float) $s->total_amount,
                'credit' => 0.0,
                'balance' => null,
            ]);

            // Payments
            $paymentQuery = Payment::where('customer_id', $customerId)
                ->orderBy('payment_date', 'desc');
            if ($dateFrom) $paymentQuery->where('payment_date', '>=', $dateFrom);
            if ($dateTo) $paymentQuery->where('payment_date', '<=', $dateTo);
            $payments = $paymentQuery->get()->map(fn (Payment $p) => [
                'type' => 'payment',
                'date' => $p->payment_date,
                'reference' => "PAY-{$p->id}",
                'description' => "Payment ({$p->payment_method})",
                'debit' => 0.0,
                'credit' => (float) $p->total_amount,
                'balance' => null,
            ]);

            // Merge and sort by date
            $transactions = $sales->concat($payments)->sortByDesc('date')->values();

            // Running balance
            $runningBalance = 0.0;
            foreach ($transactions as &$txn) {
                $runningBalance += $txn['debit'] - $txn['credit'];
                $txn['balance'] = round($runningBalance, 2);
            }

            $totalSales = (float) $sales->sum('debit');
            $totalPayments = (float) $payments->sum('credit');

            return response()->json([
                'status' => 'success',
                'message' => 'Customer statement retrieved successfully',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'code' => $customer->code,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'outstanding_balance' => (float) $customer->outstanding_balance,
                    ],
                    'summary' => [
                        'total_sales' => $totalSales,
                        'total_payments' => $totalPayments,
                        'net_balance' => $totalSales - $totalPayments,
                    ],
                    'transactions' => $transactions,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customer statement',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Cheque Report — by status, date range, bank
     */
    public function chequeReport(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $status = $request->get('status');
            $bankName = $request->get('bank_name');
            $search = $request->get('search');

            $query = Cheque::with('customer:id,code,name');

            if ($dateFrom) $query->where('cheque_date', '>=', $dateFrom);
            if ($dateTo) $query->where('cheque_date', '<=', $dateTo);
            if ($status) $query->where('status', $status);
            if ($bankName) $query->where('bank_name', $bankName);
            if ($search) $query->where('cheque_number', 'like', "%{$search}%");

            $cheques = $query->orderBy('cheque_date', 'desc')->get();

            $summary = [
                'total_count' => $cheques->count(),
                'total_amount' => (float) $cheques->sum('amount'),
                'pending_count' => $cheques->where('status', 'pending')->count(),
                'pending_amount' => (float) $cheques->where('status', 'pending')->sum('amount'),
                'cleared_count' => $cheques->where('status', 'cleared')->count(),
                'cleared_amount' => (float) $cheques->where('status', 'cleared')->sum('amount'),
                'bounced_count' => $cheques->where('status', 'bounced')->count(),
                'bounced_amount' => (float) $cheques->where('status', 'bounced')->sum('amount'),
            ];

            $banks = $cheques->groupBy('bank_name')->map(fn ($c) => [
                'bank_name' => $c->first()->bank_name,
                'count' => $c->count(),
                'total_amount' => (float) $c->sum('amount'),
            ])->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Cheque report retrieved successfully',
                'data' => [
                    'summary' => $summary,
                    'by_bank' => $banks,
                    'cheques' => $cheques,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve cheque report',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Payment Report — by date range, method, customer
     */
    public function paymentReport(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());
            $paymentMethod = $request->get('payment_method');
            $customerId = $request->get('customer_id');

            $query = Payment::with('customer:id,code,name')
                ->whereBetween('payment_date', [$dateFrom, $dateTo]);

            if ($paymentMethod) $query->where('payment_method', $paymentMethod);
            if ($customerId) $query->where('customer_id', $customerId);

            $payments = $query->orderBy('payment_date', 'desc')->get();

            $summary = [
                'total_amount' => (float) $payments->sum('total_amount'),
                'count' => $payments->count(),
            ];

            $byMethod = $payments->groupBy('payment_method')->map(fn ($p) => [
                'method' => $p->first()->payment_method,
                'count' => $p->count(),
                'total_amount' => (float) $p->sum('total_amount'),
            ])->values();

            $byCustomer = $payments->groupBy('customer_id')->map(fn ($p) => [
                'customer_name' => $p->first()->customer->name ?? 'Unknown',
                'count' => $p->count(),
                'total_amount' => (float) $p->sum('total_amount'),
            ])->sortByDesc('total_amount')->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment report retrieved successfully',
                'data' => [
                    'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                    'summary' => $summary,
                    'by_method' => $byMethod,
                    'by_customer' => $byCustomer,
                    'payments' => $payments,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve payment report',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Expense Summary — monthly trend + category breakdown
     */
    public function expenseSummary(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            // Monthly trend
            $monthly = Expense::select(
                DB::raw('MONTH(expense_date) as month'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as count')
            )
            ->whereYear('expense_date', $year)
            ->groupBy(DB::raw('MONTH(expense_date)'))
            ->orderBy('month', 'asc')
            ->get();

            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthlyData = [];
            for ($m = 1; $m <= 12; $m++) {
                $record = $monthly->firstWhere('month', $m);
                $monthlyData[] = [
                    'month' => $monthNames[$m - 1],
                    'total_amount' => $record ? (float) $record->total_amount : 0.0,
                    'count' => $record ? (int) $record->count : 0,
                ];
            }

            // Category breakdown
            $catQuery = Expense::select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'));
            if ($dateFrom) $catQuery->where('expense_date', '>=', $dateFrom);
            if ($dateTo) $catQuery->where('expense_date', '<=', $dateTo);
            else $catQuery->whereYear('expense_date', $year);
            $categories = $catQuery->groupBy('category')->orderByDesc('total_amount')->get();

            $grandTotal = (float) $categories->sum('total_amount');

            return response()->json([
                'status' => 'success',
                'message' => 'Expense summary retrieved successfully',
                'data' => [
                    'year' => (int) $year,
                    'grand_total' => $grandTotal,
                    'monthly' => $monthlyData,
                    'by_category' => $categories,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve expense summary',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Monthly Summary — year overview with income vs expense vs profit
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));

            $records = DB::table('finance_records')
                ->select(
                    DB::raw('MONTH(record_date) as month'),
                    DB::raw("SUM(CASE WHEN record_type = 'income' THEN amount ELSE 0 END) as income"),
                    DB::raw("SUM(CASE WHEN record_type = 'expense' THEN amount ELSE 0 END) as expense")
                )
                ->whereYear('record_date', $year)
                ->groupBy(DB::raw('MONTH(record_date)'))
                ->orderBy('month', 'asc')
                ->get();

            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthly = [];
            $totalIncome = 0;
            $totalExpense = 0;

            for ($m = 1; $m <= 12; $m++) {
                $record = $records->firstWhere('month', $m);
                $inc = $record ? (float) $record->income : 0.0;
                $exp = $record ? (float) $record->expense : 0.0;
                $totalIncome += $inc;
                $totalExpense += $exp;

                $monthly[] = [
                    'month' => $monthNames[$m - 1],
                    'income' => $inc,
                    'expense' => $exp,
                    'profit' => $inc - $exp,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Monthly summary retrieved successfully',
                'data' => [
                    'year' => (int) $year,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'total_profit' => $totalIncome - $totalExpense,
                    'monthly' => $monthly,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve monthly summary',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Customer Dues Aging Analysis (0-30, 31-60, 61-90, 90+ Days).
     */
    public function duesAging(Request $request): JsonResponse
    {
        try {
            $unpaidSales = Sale::with('customer:id,code,name,phone')
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->where('due_amount', '>', 0)
                ->get();

            $aging = [
                'current_0_30' => 0.00,
                'aging_31_60' => 0.00,
                'aging_61_90' => 0.00,
                'over_90' => 0.00,
                'total_due' => 0.00,
            ];

            $detailedSales = [];

            foreach ($unpaidSales as $sale) {
                $saleDate = Carbon::parse($sale->sale_date)->startOfDay();
                $today = now()->startOfDay();
                $days = $saleDate->greaterThan($today) ? 0 : (int) $saleDate->diffInDays($today);
                $due = (float) $sale->due_amount;

                $aging['total_due'] += $due;

                if ($days <= 30) {
                    $bucket = '0-30 days';
                    $aging['current_0_30'] += $due;
                } elseif ($days <= 60) {
                    $bucket = '31-60 days';
                    $aging['aging_31_60'] += $due;
                } elseif ($days <= 90) {
                    $bucket = '61-90 days';
                    $aging['aging_61_90'] += $due;
                } else {
                    $bucket = '90+ days';
                    $aging['over_90'] += $due;
                }

                $detailedSales[] = [
                    'sale_id' => $sale->id,
                    'reference_number' => $sale->reference_number,
                    'customer' => $sale->customer,
                    'sale_date' => $sale->sale_date->toDateString(),
                    'days_outstanding' => $days,
                    'due_amount' => $due,
                    'aging_bucket' => $bucket,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Customer dues aging analysis retrieved successfully',
                'data' => [
                    'summary' => $aging,
                    'sales' => $detailedSales,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dues aging analysis',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Monthly Profit and Loss Breakdown.
     */
    public function pnl(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));

            $records = \App\Models\FinanceRecord::select(
                DB::raw('MONTH(record_date) as month'),
                DB::raw("SUM(CASE WHEN record_type = 'income' THEN amount ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN record_type = 'expense' THEN amount ELSE 0 END) as expense")
            )
            ->whereYear('record_date', $year)
            ->groupBy(DB::raw('MONTH(record_date)'))
            ->orderBy('month', 'asc')
            ->get();

            $monthlyBreakdown = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthRecord = $records->firstWhere('month', $m);
                $inc = $monthRecord ? (float) $monthRecord->income : 0.00;
                $exp = $monthRecord ? (float) $monthRecord->expense : 0.00;

                $monthlyBreakdown[] = [
                    'month_number' => $m,
                    'month_name' => date('F', mktime(0, 0, 0, $m, 1)),
                    'income' => $inc,
                    'expense' => $exp,
                    'net_profit' => $inc - $exp,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Monthly Profit & Loss breakdown retrieved successfully',
                'data' => [
                    'year' => (int) $year,
                    'monthly' => $monthlyBreakdown,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve P&L breakdown',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
