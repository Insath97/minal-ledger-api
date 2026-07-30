<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinanceRecord;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Cheque;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Dashboard', ['only' => ['getStats', 'getAnalytics', 'getActivity']]),
        ];
    }

    public function getStats(Request $request): JsonResponse
    {
        try {
            $this->logActivity('DASHBOARD_STATS', 'Dashboard', 'Viewed dashboard stats overview');
            $now = now();
            $currentMonthStart = $now->copy()->startOfMonth()->toDateString();
            $currentMonthEnd = $now->copy()->endOfMonth()->toDateString();
            $prevMonthStart = $now->copy()->subMonth()->startOfMonth()->toDateString();
            $prevMonthEnd = $now->copy()->subMonth()->endOfMonth()->toDateString();

            // --- Total Sales (Consolidated Query) ---
            $salesStats = Sale::selectRaw("
                SUM(total_amount) as total_sales,
                SUM(CASE WHEN sale_date BETWEEN ? AND ? THEN total_amount ELSE 0 END) as current_month_sales,
                SUM(CASE WHEN sale_date BETWEEN ? AND ? THEN total_amount ELSE 0 END) as prev_month_sales
            ", [$currentMonthStart, $currentMonthEnd, $prevMonthStart, $prevMonthEnd])->first();

            $totalSales = (float) ($salesStats->total_sales ?? 0);
            $currentMonthSales = (float) ($salesStats->current_month_sales ?? 0);
            $prevMonthSales = (float) ($salesStats->prev_month_sales ?? 0);
            $salesChange = $prevMonthSales > 0 ? round(($currentMonthSales - $prevMonthSales) / $prevMonthSales * 100, 1) : 0;

            // --- Total Expenses (Consolidated Query) ---
            $expenseStats = Expense::selectRaw("
                SUM(amount) as total_expenses,
                SUM(CASE WHEN expense_date BETWEEN ? AND ? THEN amount ELSE 0 END) as current_month_expenses,
                SUM(CASE WHEN expense_date BETWEEN ? AND ? THEN amount ELSE 0 END) as prev_month_expenses
            ", [$currentMonthStart, $currentMonthEnd, $prevMonthStart, $prevMonthEnd])->first();

            $totalExpenses = (float) ($expenseStats->total_expenses ?? 0);
            $currentMonthExpenses = (float) ($expenseStats->current_month_expenses ?? 0);
            $prevMonthExpenses = (float) ($expenseStats->prev_month_expenses ?? 0);
            $expensesChange = $prevMonthExpenses > 0 ? round(($currentMonthExpenses - $prevMonthExpenses) / $prevMonthExpenses * 100, 1) : 0;

            // --- Total Received (Consolidated Query) ---
            $receivedStats = FinanceRecord::where('record_type', 'income')->selectRaw("
                SUM(amount) as total_received,
                SUM(CASE WHEN record_date BETWEEN ? AND ? THEN amount ELSE 0 END) as current_month_received,
                SUM(CASE WHEN record_date BETWEEN ? AND ? THEN amount ELSE 0 END) as prev_month_received
            ", [$currentMonthStart, $currentMonthEnd, $prevMonthStart, $prevMonthEnd])->first();

            $totalReceived = (float) ($receivedStats->total_received ?? 0);
            $currentMonthReceived = (float) ($receivedStats->current_month_received ?? 0);
            $prevMonthReceived = (float) ($receivedStats->prev_month_received ?? 0);
            $receivedChange = $prevMonthReceived > 0 ? round(($currentMonthReceived - $prevMonthReceived) / $prevMonthReceived * 100, 1) : 0;

            // --- Outstanding Dues ---
            $totalOutstanding = (float) Customer::active()->sum('outstanding_balance');
            
            // Calculate previous month's outstanding balance:
            // Outstanding (End of Prev Month) = Current Outstanding + Payments (Current Month) - Sales Dues (Current Month)
            $paymentsCurrentMonth = (float) Payment::whereBetween('payment_date', [$currentMonthStart, $currentMonthEnd])->sum('total_amount');
            $salesDueCurrentMonth = (float) Sale::whereBetween('sale_date', [$currentMonthStart, $currentMonthEnd])->sum('due_amount');
            $prevMonthOutstanding = max(0.0, $totalOutstanding + $paymentsCurrentMonth - $salesDueCurrentMonth);

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

            $this->logActivity('DASHBOARD_ANALYTICS', 'Dashboard', "Viewed dashboard analytics (Year: {$year}" . ($month ? ", Month: {$month}" : '') . ')');

            if ($month) {
                // Daily breakdown — use finance_records as single source of truth
                $daily = FinanceRecord::selectRaw(
                    "DAY(record_date) as day,
                     SUM(CASE WHEN record_type = 'income'  THEN amount ELSE 0 END) as income,
                     SUM(CASE WHEN record_type = 'expense' THEN amount ELSE 0 END) as expense"
                )
                ->whereYear('record_date', $year)
                ->whereMonth('record_date', $month)
                ->groupByRaw('DAY(record_date)')
                ->get();

                $daysInMonth = \Carbon\Carbon::createFromDate((int)$year, (int)$month, 1)->daysInMonth;
                $dailyData = [];
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $rec = $daily->first(fn ($item) => (int) $item->day === $d);
                    $dailyData[] = [
                        'label'   => $d,
                        'income'  => $rec ? (float) $rec->income  : 0.0,
                        'expense' => $rec ? (float) $rec->expense : 0.0,
                    ];
                }

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Daily analytics retrieved successfully',
                    'data'    => [
                        'type'   => 'daily',
                        'year'   => (int) $year,
                        'month'  => (int) $month,
                        'labels' => $dailyData,
                    ],
                ], 200);
            }

            // Monthly breakdown — use finance_records as single source of truth
            $monthly = FinanceRecord::selectRaw(
                "MONTH(record_date) as month,
                 SUM(CASE WHEN record_type = 'income'  THEN amount ELSE 0 END) as income,
                 SUM(CASE WHEN record_type = 'expense' THEN amount ELSE 0 END) as expense"
            )
            ->whereYear('record_date', $year)
            ->groupByRaw('MONTH(record_date)')
            ->get();

            $monthNames  = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthlyData = [];
            for ($m = 1; $m <= 12; $m++) {
                $rec = $monthly->first(fn ($item) => (int) $item->month === $m);
                $monthlyData[] = [
                    'label'   => $monthNames[$m - 1],
                    'income'  => $rec ? (float) $rec->income  : 0.0,
                    'expense' => $rec ? (float) $rec->expense : 0.0,
                ];
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Monthly analytics retrieved successfully',
                'data'    => [
                    'type'   => 'monthly',
                    'year'   => (int) $year,
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
            $this->logActivity('DASHBOARD_ACTIVITY', 'Dashboard', 'Viewed recent dashboard activity');
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
