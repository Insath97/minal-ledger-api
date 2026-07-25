<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinanceRecord;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Finance Dashboard', ['only' => ['getDashboard', 'getPnL', 'getIncomeBreakdown', 'getExpenseBreakdown', 'getDuesAging']]),
        ];
    }

    /**
     * Get Real-time Financial Dashboard Summary.
     */
    public function getDashboard(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            // 1. Total Income (Settled Payments)
            $totalIncome = (float) FinanceRecord::income()
                ->byDateRange($dateFrom, $dateTo)
                ->sum('amount');

            // 2. Total Expenses
            $totalExpenses = (float) FinanceRecord::expense()
                ->byDateRange($dateFrom, $dateTo)
                ->sum('amount');

            // 3. Net Profit
            $netProfit = $totalIncome - $totalExpenses;

            // 4. Total Receivable (Sum of customer outstanding balances)
            $totalReceivable = (float) Customer::active()->sum('outstanding_balance');

            // 5. Total Pending Cheques Exposure
            $pendingChequesAmount = (float) Cheque::pending()->sum('amount');
            $pendingChequesCount = Cheque::pending()->count();

            return response()->json([
                'status' => 'success',
                'message' => 'Financial dashboard summary retrieved successfully',
                'data' => [
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo,
                    ],
                    'total_income' => $totalIncome,
                    'total_expenses' => $totalExpenses,
                    'net_profit' => $netProfit,
                    'total_receivable' => $totalReceivable,
                    'pending_cheques' => [
                        'count' => $pendingChequesCount,
                        'total_amount' => $pendingChequesAmount,
                    ],
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve financial dashboard',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Monthly Profit and Loss Breakdown.
     */
    public function getPnL(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));

            $records = FinanceRecord::select(
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

    /**
     * Get Income breakdown by Payment Method.
     */
    public function getIncomeBreakdown(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            $breakdown = Payment::select('payment_method', DB::raw('SUM(total_amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
                ->whereDate('payment_date', '>=', $dateFrom)
                ->whereDate('payment_date', '<=', $dateTo)
                ->groupBy('payment_method')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Income breakdown by payment method retrieved successfully',
                'data' => [
                    'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                    'grand_total' => (float) $breakdown->sum('total_amount'),
                    'by_method' => $breakdown,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve income breakdown',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Expense breakdown by Category.
     */
    public function getExpenseBreakdown(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            $breakdown = Expense::select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
                ->whereDate('expense_date', '>=', $dateFrom)
                ->whereDate('expense_date', '<=', $dateTo)
                ->groupBy('category')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Expense breakdown by category retrieved successfully',
                'data' => [
                    'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                    'grand_total' => (float) $breakdown->sum('total_amount'),
                    'by_category' => $breakdown,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve expense breakdown',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Customer Dues Aging Analysis (0-30, 31-60, 61-90, 90+ Days).
     */
    public function getDuesAging(Request $request)
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
                $days = now()->diffInDays($sale->sale_date);
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
}
