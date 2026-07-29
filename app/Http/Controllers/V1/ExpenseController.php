<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\FinanceRecord;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:Expense Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Expense Create', ['only' => ['store']]),
            new Middleware('permission:Expense Update', ['only' => ['update']]),
            new Middleware('permission:Expense Delete', ['only' => ['destroy']]),
        ];
    }

    /**
     * Display a listing of expenses with category and date range filters.
     */
    public function index(Request $request)
    {
        try {
            $this->logActivity('INDEX', 'Expense', 'Viewed expense list');
            $perPage = $request->get('per_page', 15);
            $query = Expense::with(['creator:id,name', 'items']);

            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('category') && $request->category != '') {
                $query->byCategory($request->category);
            }

            if ($request->has('date_from') && $request->date_from != '') {
                $query->whereDate('expense_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to != '') {
                $query->whereDate('expense_date', '<=', $request->date_to);
            }

            $expenses = $query->orderBy('expense_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Expenses retrieved successfully',
                'data' => $expenses,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve expenses',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created expense and log to finance_records.
     */
    public function store(CreateExpenseRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $authUser = auth()->user();

            if ($request->hasFile('receipt_image')) {
                $data['receipt_image'] = $this->handleFileUpload($request, 'receipt_image', null, 'expenses');
            }

            if ($request->hasFile('bill_image')) {
                $data['bill_image'] = $this->handleFileUpload($request, 'bill_image', null, 'expenses');
            }

            $items = $data['items'] ?? [];
            unset($data['items']);

            if (empty($data['amount']) && !empty($items)) {
                $data['amount'] = array_sum(array_map(function ($item) {
                    return $item['quantity'] * $item['unit_price'];
                }, $items));
            }

            $data['created_by'] = $authUser->id ?? 1;

            $expense = Expense::create($data);

            if (!empty($items)) {
                $expenseItems = [];
                foreach ($items as $item) {
                    $total = $item['quantity'] * $item['unit_price'];
                    $expenseItems[] = new ExpenseItem([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $total,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
                $expense->items()->saveMany($expenseItems);
            }

            // Log expense in FinanceRecord
            FinanceRecord::create([
                'record_type' => 'expense',
                'reference_type' => 'Expense',
                'reference_id' => $expense->id,
                'amount' => $expense->amount,
                'description' => "Expense: {$expense->title} ({$expense->category})",
                'record_date' => $expense->expense_date,
            ]);

            DB::commit();

            $this->logActivity('CREATE', 'Expense', "Created expense: {$expense->title} for amount Rs. {$expense->amount}", $request->validated());

            $expense->load(['creator:id,name', 'items']);

            return response()->json([
                'status' => 'success',
                'message' => 'Expense recorded successfully',
                'data' => $expense,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to record expense',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified expense.
     */
    public function show(string $id)
    {
        try {
            $expense = Expense::with(['creator:id,name', 'updater:id,name', 'items'])->find($id);

            if (!$expense) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expense not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'Expense', "Viewed expense: {$expense->title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Expense retrieved successfully',
                'data' => $expense,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve expense',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified expense.
     */
    public function update(UpdateExpenseRequest $request, string $id)
    {
        DB::beginTransaction();
        try {
            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expense not found',
                ], 404);
            }

            $data = $request->validated();

            if ($request->hasFile('receipt_image')) {
                $data['receipt_image'] = $this->handleFileUpload($request, 'receipt_image', $expense->receipt_image, 'expenses');
            }

            if ($request->hasFile('bill_image')) {
                $data['bill_image'] = $this->handleFileUpload($request, 'bill_image', $expense->bill_image, 'expenses');
            }

            $items = $data['items'] ?? null;
            unset($data['items']);

            if (empty($data['amount']) && $items !== null && !empty($items)) {
                $data['amount'] = array_sum(array_map(function ($item) {
                    return $item['quantity'] * $item['unit_price'];
                }, $items));
            }

            $data['updated_by'] = auth()->id() ?? 1;

            $expense->update($data);

            if ($items !== null) {
                $expense->items()->delete();
                if (!empty($items)) {
                    $expenseItems = [];
                    foreach ($items as $item) {
                        $total = $item['quantity'] * $item['unit_price'];
                        $expenseItems[] = new ExpenseItem([
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total_price' => $total,
                            'notes' => $item['notes'] ?? null,
                        ]);
                    }
                    $expense->items()->saveMany($expenseItems);
                }
            }

            // Update associated FinanceRecord
            FinanceRecord::where('reference_type', 'Expense')
                ->where('reference_id', $expense->id)
                ->update([
                    'amount' => $expense->amount,
                    'description' => "Expense: {$expense->title} ({$expense->category})",
                    'record_date' => $expense->expense_date,
                ]);

            DB::commit();

            $this->logActivity('UPDATE', 'Expense', "Updated expense: {$expense->title}", $request->validated());

            $expense->load(['creator:id,name', 'items']);

            return response()->json([
                'status' => 'success',
                'message' => 'Expense updated successfully',
                'data' => $expense,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update expense',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expense not found',
                ], 404);
            }

            // Delete FinanceRecord
            FinanceRecord::where('reference_type', 'Expense')
                ->where('reference_id', $expense->id)
                ->delete();

            if (!empty($expense->receipt_image)) {
                $this->deleteFile($expense->receipt_image);
            }

            if (!empty($expense->bill_image)) {
                $this->deleteFile($expense->bill_image);
            }

            $title = $expense->title;
            $expense->delete();

            DB::commit();

            $this->logActivity('DELETE', 'Expense', "Deleted expense: {$title}");

            return response()->json([
                'status' => 'success',
                'message' => 'Expense deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete expense',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
