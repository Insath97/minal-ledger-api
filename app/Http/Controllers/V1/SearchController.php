<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Models\Bank;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Cheque;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class SearchController extends Controller
{
    /**
     * Global search across all entities.
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q', '');

            if (strlen($query) < 2) {
                return response()->json([
                    'status' => 'success',
                    'data' => ['results' => []],
                ]);
            }

            $results = collect();

            $results = $results->merge($this->searchCustomers($query));
            $results = $results->merge($this->searchUsers($query));
            $results = $results->merge($this->searchBanks($query));
            $results = $results->merge($this->searchSales($query));
            $results = $results->merge($this->searchPayments($query));
            $results = $results->merge($this->searchCheques($query));
            $results = $results->merge($this->searchExpenses($query));
            $results = $results->merge($this->searchRoles($query));
            $results = $results->merge($this->getNavigationSearchResults($query));

            return response()->json([
                'status' => 'success',
                'data' => ['results' => $results->values()],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Search failed',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    private function searchCustomers(string $query): Collection
    {
        $results = collect();
        $customers = Customer::search($query)
            ->active()
            ->limit(5)
            ->get(['id', 'name', 'code', 'phone', 'email']);

        foreach ($customers as $customer) {
            $results->push([
                'id' => $customer->id,
                'type' => 'customer',
                'title' => $customer->name,
                'subtitle' => "{$customer->code}" . ($customer->phone ? " • {$customer->phone}" : ''),
                'href' => "/customers/{$customer->id}",
                'icon' => 'UserCheck',
            ]);
        }
        return $results;
    }

    private function searchUsers(string $query): Collection
    {
        $results = collect();
        $users = User::search($query)
            ->where('is_active', true)
            ->limit(5)
            ->get(['id', 'name', 'username', 'email']);

        foreach ($users as $user) {
            $results->push([
                'id' => $user->id,
                'type' => 'user',
                'title' => $user->name,
                'subtitle' => $user->email ?? $user->username,
                'href' => "/users/{$user->id}",
                'icon' => 'Users',
            ]);
        }
        return $results;
    }

    private function searchBanks(string $query): Collection
    {
        $results = collect();
        $banks = Bank::search($query)
            ->active()
            ->limit(5)
            ->get(['id', 'name', 'code']);

        foreach ($banks as $bank) {
            $results->push([
                'id' => $bank->id,
                'type' => 'bank',
                'title' => $bank->name,
                'subtitle' => $bank->code,
                'href' => "/banks/{$bank->id}",
                'icon' => 'Building2',
            ]);
        }
        return $results;
    }

    private function searchSales(string $query): Collection
    {
        $results = collect();
        $sales = Sale::search($query)
            ->limit(5)
            ->get(['id', 'reference_number', 'invoice_number', 'total_amount', 'customer_id'])
            ->load('customer:id,name,code');

        foreach ($sales as $sale) {
            $customerName = $sale->customer?->name ?? 'Unknown';
            $results->push([
                'id' => $sale->id,
                'type' => 'sale',
                'title' => $sale->reference_number,
                'subtitle' => "{$customerName} • Rs. " . number_format($sale->total_amount, 2),
                'href' => "/sales/{$sale->id}",
                'icon' => 'ShoppingCart',
            ]);
        }
        return $results;
    }

    private function searchPayments(string $query): Collection
    {
        $results = collect();
        $payments = Payment::search($query)
            ->limit(5)
            ->get(['id', 'total_amount', 'payment_method', 'payment_date', 'customer_id'])
            ->load('customer:id,name,code');

        foreach ($payments as $payment) {
            $customerName = $payment->customer?->name ?? 'Unknown';
            $results->push([
                'id' => $payment->id,
                'type' => 'payment',
                'title' => "Payment #{$payment->id}",
                'subtitle' => "{$customerName} • Rs. " . number_format($payment->total_amount, 2),
                'href' => "/payments/{$payment->id}",
                'icon' => 'ArrowDownRight',
            ]);
        }
        return $results;
    }

    private function searchCheques(string $query): Collection
    {
        $results = collect();
        $cheques = Cheque::search($query)
            ->limit(5)
            ->get(['id', 'cheque_number', 'bank_name', 'amount', 'status', 'customer_id'])
            ->load('customer:id,name,code');

        foreach ($cheques as $cheque) {
            $customerName = $cheque->customer?->name ?? 'Unknown';
            $results->push([
                'id' => $cheque->id,
                'type' => 'cheque',
                'title' => $cheque->cheque_number,
                'subtitle' => "{$customerName} • {$cheque->bank_name} • Rs. " . number_format($cheque->amount, 2),
                'href' => "/cheques/{$cheque->id}",
                'icon' => 'CreditCard',
            ]);
        }
        return $results;
    }

    private function searchExpenses(string $query): Collection
    {
        $results = collect();
        $expenses = Expense::search($query)
            ->limit(5)
            ->get(['id', 'title', 'amount', 'category']);

        foreach ($expenses as $expense) {
            $results->push([
                'id' => $expense->id,
                'type' => 'expense',
                'title' => $expense->title,
                'subtitle' => ucfirst($expense->category) . ' • Rs. ' . number_format($expense->amount, 2),
                'href' => "/expenses/{$expense->id}",
                'icon' => 'Receipt',
            ]);
        }
        return $results;
    }

    private function searchRoles(string $query): Collection
    {
        $results = collect();
        $roles = Role::where('name', 'like', "%{$query}%")
            ->limit(3)
            ->get(['id', 'name']);

        foreach ($roles as $role) {
            $results->push([
                'id' => $role->id,
                'type' => 'role',
                'title' => $role->name,
                'subtitle' => 'Role',
                'href' => "/roles/{$role->id}",
                'icon' => 'Shield',
            ]);
        }
        return $results;
    }

    /**
     * Search navigation items.
     */
    private function getNavigationSearchResults(string $query): array
    {
        $navigation = [
            ['title' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'LayoutDashboard', 'section' => 'MAIN MENU'],
            ['title' => 'Sales', 'href' => '/sales', 'icon' => 'ShoppingCart', 'section' => 'MAIN MENU'],
            ['title' => 'Cheques', 'href' => '/cheques', 'icon' => 'CreditCard', 'section' => 'MAIN MENU'],
            ['title' => 'Payments', 'href' => '/payments', 'icon' => 'ArrowDownRight', 'section' => 'MAIN MENU'],
            ['title' => 'Expenses', 'href' => '/expenses', 'icon' => 'Receipt', 'section' => 'MAIN MENU'],
            ['title' => 'Customers', 'href' => '/customers', 'icon' => 'UserCheck', 'section' => 'MAIN MENU'],
            ['title' => 'Reports', 'href' => '/reports', 'icon' => 'FileText', 'section' => 'REPORTS'],
            ['title' => 'Profit & Loss', 'href' => '/reports/pnl', 'icon' => 'TrendingUp', 'section' => 'REPORTS'],
            ['title' => 'Dues Aging', 'href' => '/reports/dues-aging', 'icon' => 'Clock', 'section' => 'REPORTS'],
            ['title' => 'Sales Report', 'href' => '/reports/sales', 'icon' => 'ShoppingCart', 'section' => 'REPORTS'],
            ['title' => 'Customer Statement', 'href' => '/reports/customer-statement', 'icon' => 'UserCheck', 'section' => 'REPORTS'],
            ['title' => 'Cheque Report', 'href' => '/reports/cheque-report', 'icon' => 'CreditCard', 'section' => 'REPORTS'],
            ['title' => 'Payment Report', 'href' => '/reports/payment-report', 'icon' => 'ArrowDownRight', 'section' => 'REPORTS'],
            ['title' => 'Expense Summary', 'href' => '/reports/expense-summary', 'icon' => 'Receipt', 'section' => 'REPORTS'],
            ['title' => 'Monthly Summary', 'href' => '/reports/monthly-summary', 'icon' => 'TrendingUp', 'section' => 'REPORTS'],
            ['title' => 'Banks', 'href' => '/banks', 'icon' => 'Building2', 'section' => 'GENERAL'],
            ['title' => 'Users', 'href' => '/users', 'icon' => 'Users', 'section' => 'GENERAL'],
            ['title' => 'Roles', 'href' => '/roles', 'icon' => 'Shield', 'section' => 'GENERAL'],
            ['title' => 'Settings', 'href' => '/settings', 'icon' => 'Settings', 'section' => 'GENERAL'],
        ];

        $results = [];
        $queryLower = strtolower($query);

        foreach ($navigation as $item) {
            if (str_contains(strtolower($item['title']), $queryLower)) {
                $results[] = [
                    'id' => null,
                    'type' => 'navigation',
                    'title' => $item['title'],
                    'subtitle' => $item['section'],
                    'href' => $item['href'],
                    'icon' => $item['icon'],
                ];
            }
        }

        return $results;
    }
}
