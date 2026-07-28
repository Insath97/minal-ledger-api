<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinanceRecord;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Cheque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Dashboard', ['only' => ['getStats', 'getAnalytics', 'getActivity']]),
        ];
    }

    public function getStats(Request $request): JsonResponse
    {
        try {
            $now = now();
            $currentMonthStart = $now->copy()->startOfMonth()->toDateString();
            $currentMonthEnd = $now->copy()->endOfMonth()->toDateString();
            $prevMonthStart = $now->copy()->subMonth()->startOfMonth()->toDateString();
            $prevMonthEnd = $now->copy()->subMonth()->endOfMonth()->toDateString();

            // --- Total Sales ---
            $totalSales = (float) Sale::sum('total_amount');
            $currentMonthSales = (float) Sale::whereBetween('sale_date', [$currentMonthStart, $currentMonthEnd])->sum('total_amount');
            $prevMonthSales = (float) Sale::whereBetween('sale_date', [$prevMonthStart, $prevMonthEnd])->sum('total_amount');
            $salesChange = $prevMonthSales > 0 ? round(($currentMonthSales - $prevMonthSales) / $prevMonthSales * 100, 1) : 0;

            // --- Total Expenses ---
            $totalExpenses = (float) Expense::sum('amount');
            $currentMonthExpenses = (float) Expense::whereBetween('expense_date', [$currentMonthStart, $currentMonthEnd])->sum('amount');
            $prevMonthExpenses = (float) Expense::whereBetween('expense_date', [$prevMonthStart, $prevMonthEnd])->sum('amount');
            $expensesChange = $prevMonthExpenses > 0 ? round(($currentMonthExpenses - $prevMonthExpenses) / $prevMonthExpenses * 100, 1) : 0;

            // --- Total Received (Payments) ---
            $totalReceived = (float) Payment::sum('total_amount');
            $currentMonthReceived = (float) Payment::whereBetween('payment_date', [$currentMonthStart, $currentMonthEnd])->sum('total_amount');
            $prevMonthReceived = (float) Payment::whereBetween('payment_date', [$prevMonthStart, $prevMonthEnd])->sum('total_amount');
            $receivedChange = $prevMonthReceived > 0 ? round(($currentMonthReceived - $prevMonthReceived) / $prevMonthReceived * 100, 1) : 0;

            // --- Outstanding Dues ---
            $totalOutstanding = (float) Customer::active()->sum('outstanding_balance');
            $prevMonthOutstanding = (float) Customer::active()->sum('outstanding_balance');
            $outstandingChange = $prevMonthOutstanding > 0 ? round(($totalOutstanding - $prevMonthOutstanding) / $prevMonthOutstanding * 100, 1) : 0;

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard stats retrieved successfully',
                'data' => [
                    'total_sales' => $totalSales,
                    'sales_change' => $salesChange,
                    'total_expenses' => $totalExpenses,
                    'expenses_change' => $expensesChange,
                    'total_received' => $totalReceived,
                    'received_change' => $receivedChange,
                    'total_outstanding' => $totalOutstanding,
                    'outstanding_change' => $outstandingChange,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard stats',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get Analytics data for dashboard chart.
     * year=2026 -> monthly income/expense breakdown
     * year=2026&month=7 -> daily breakdown for that month
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $month = $request->get('month');

            if ($month) {
                // Daily breakdown for a specific month
                $daily = FinanceRecord::select(
                    DB::raw('DAY(record_date) as day'),
                    DB::raw("SUM(CASE WHEN record_type = 'income' THEN amount ELSE 0 END) as income"),
                    DB::raw("SUM(CASE WHEN record_type = 'expense' THEN amount ELSE 0 END) as expense")
                )
                ->whereYear('record_date', $year)
                ->whereMonth('record_date', $month)
                ->groupBy(DB::raw('DAY(record_date)'))
                ->orderBy('day', 'asc')
                ->get();

                $daysInMonth = \Carbon\Carbon::createFromDate((int)$year, (int)$month, 1)->daysInMonth;
                $dailyData = [];
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $record = $daily->firstWhere('day', $d);
                    $dailyData[] = [
                        'label' => $d,
                        'income' => $record ? (float) $record->income : 0.0,
                        'expense' => $record ? (float) $record->expense : 0.0,
                    ];
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Daily analytics retrieved successfully',
                    'data' => [
                        'type' => 'daily',
                        'year' => (int) $year,
                        'month' => (int) $month,
                        'labels' => $dailyData,
                    ],
                ], 200);
            }

            // Monthly breakdown for a year
            $monthly = FinanceRecord::select(
                DB::raw('MONTH(record_date) as month'),
                DB::raw("SUM(CASE WHEN record_type = 'income' THEN amount ELSE 0 END) as income"),
                DB::raw("SUM(CASE WHEN record_type = 'expense' THEN amount ELSE 0 END) as expense")
            )
            ->whereYear('record_date', $year)
            ->groupBy(DB::raw('MONTH(record_date)'))
            ->orderBy('month', 'asc')
            ->get();

            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthlyData = [];
            for ($m = 1; $m <= 12; $m++) {
                $record = $monthly->firstWhere('month', $m);
                $monthlyData[] = [
                    'label' => $monthNames[$m - 1],
                    'income' => $record ? (float) $record->income : 0.0,
                    'expense' => $record ? (float) $record->expense : 0.0,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Monthly analytics retrieved successfully',
                'data' => [
                    'type' => 'monthly',
                    'year' => (int) $year,
                    'labels' => $monthlyData,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve analytics',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get recent activity for dashboard (recent sales + pending cheques + top customers).
     */
    public function getActivity(): JsonResponse
    {
        try {
            // Recent 5 sales
            $recentSales = Sale::with('customer:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn (Sale $sale) => [
                    'id' => $sale->id,
                    'reference_number' => $sale->reference_number,
                    'customer_name' => $sale->customer->name ?? 'Walk-in',
                    'total_amount' => (float) $sale->total_amount,
                    'due_amount' => (float) $sale->due_amount,
                    'payment_status' => $sale->payment_status,
                    'sale_date' => $sale->sale_date,
                ]);

            // Pending cheques
            $pendingCheques = Cheque::with('customer:id,name')
                ->where('status', 'pending')
                ->orderBy('cheque_date', 'asc')
                ->limit(5)
                ->get()
                ->map(fn (Cheque $cheque) => [
                    'id' => $cheque->id,
                    'cheque_number' => $cheque->cheque_number,
                    'customer_name' => $cheque->customer->name ?? 'Unknown',
                    'amount' => (float) $cheque->amount,
                    'bank_name' => $cheque->bank_name,
                    'cheque_date' => $cheque->cheque_date,
                ]);

            // Top 5 customers by outstanding balance
            $topCustomers = Customer::active()
                ->where('outstanding_balance', '>', 0)
                ->orderBy('outstanding_balance', 'desc')
                ->limit(5)
                ->get()
                ->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'outstanding_balance' => (float) $c->outstanding_balance,
                    'phone' => $c->phone,
                ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard activity retrieved successfully',
                'data' => [
                    'recent_sales' => $recentSales,
                    'pending_cheques' => $pendingCheques,
                    'top_customers' => $topCustomers,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard activity',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
