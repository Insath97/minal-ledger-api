<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Customer Index', ['only' => ['index', 'show']]),
            new Middleware('permission:Customer List', ['only' => ['getActiveList']]),
            new Middleware('permission:Customer Create', ['only' => ['store']]),
            new Middleware('permission:Customer Update', ['only' => ['update']]),
            new Middleware('permission:Customer Delete', ['only' => ['destroy']]),
            new Middleware('permission:Customer Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        try {
            $this->logActivity('INDEX', 'Customer', 'Viewed customer list');
            $perPage = $request->get('per_page', 15);
            $query = Customer::query();

            // Apply Search Scope
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $customers = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Customers retrieved successfully',
                'data' => $customers,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customers',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created customer.
     */
    public function store(CreateCustomerRequest $request)
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('profile_image')) {
                $data['profile_image'] = $this->handleFileUpload($request, 'profile_image', null, 'customer');
            }

            if ($request->hasFile('nic_image')) {
                $data['nic_image'] = $this->handleFileUpload($request, 'nic_image', null, 'customer');
            }

            // Auto-generate customer code if not explicitly provided
            if (empty($data['code'])) {
                $data['code'] = $this->generateCustomerCode();
            }

            $customer = Customer::create($data);

            $this->logActivity('CREATE', 'Customer', "Created customer: {$customer->name} ({$customer->code})", $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Customer created successfully',
                'data' => $customer,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create customer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(string $id)
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'Customer', "Viewed customer: {$customer->name} ({$customer->code})");

            return response()->json([
                'status' => 'success',
                'message' => 'Customer retrieved successfully',
                'data' => $customer,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, string $id)
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found',
                ], 404);
            }

            $data = $request->validated();

            // Handle Profile Image
            if ($request->hasFile('profile_image')) {
                $data['profile_image'] = $this->handleFileUpload($request, 'profile_image', $customer->profile_image, 'customer');
            } elseif ($request->exists('profile_image') && (empty($request->profile_image) || $request->profile_image === 'null')) {
                $this->deleteFile($customer->profile_image);
                $data['profile_image'] = null;
            } else {
                unset($data['profile_image']);
            }

            // Handle NIC Image
            if ($request->hasFile('nic_image')) {
                $data['nic_image'] = $this->handleFileUpload($request, 'nic_image', $customer->nic_image, 'customer');
            } elseif ($request->exists('nic_image') && (empty($request->nic_image) || $request->nic_image === 'null')) {
                $this->deleteFile($customer->nic_image);
                $data['nic_image'] = null;
            } else {
                unset($data['nic_image']);
            }

            $customer->update($data);

            $this->logActivity('UPDATE', 'Customer', "Updated customer: {$customer->name} ({$customer->code})", $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Customer updated successfully',
                'data' => $customer,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update customer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(string $id)
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found',
                ], 404);
            }

            $customerCode = $customer->code;
            $customerName = $customer->name;

            $profileImage = $customer->profile_image;
            $nicImage = $customer->nic_image;

            $customer->delete();

            // Only delete files if database deletion succeeded
            if (!empty($profileImage)) {
                $this->deleteFile($profileImage);
            }

            if (!empty($nicImage)) {
                $this->deleteFile($nicImage);
            }

            $this->logActivity('DELETE', 'Customer', "Deleted customer: {$customerName} ({$customerCode})");

            return response()->json([
                'status' => 'success',
                'message' => 'Customer deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // Check for integrity constraint violation (e.g. foreign key constraint restriction)
            if ($e->getCode() === '23000' || (isset($e->errorInfo[0]) && $e->errorInfo[0] === '23000')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete customer because they have active transactions (such as sales or cheques) associated with them. You can deactivate them instead.',
                ], 422);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete customer',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete customer',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle active status of customer.
     */
    public function toggleStatus(string $id)
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found',
                ], 404);
            }

            $customer->is_active = !$customer->is_active;
            $customer->save();

            $statusText = $customer->is_active ? 'Active' : 'Inactive';
            $this->logActivity('TOGGLE_STATUS', 'Customer', "Toggled customer status: {$customer->name} ({$statusText})");

            return response()->json([
                'status' => 'success',
                'message' => 'Customer status updated successfully',
                'data' => [
                    'id' => $customer->id,
                    'is_active' => $customer->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle customer status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a lightweight list of active customers (for dropdowns).
     */
    public function getActiveList()
    {
        try {
            $customers = Customer::active()
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'code', 'phone', 'outstanding_balance']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active customers list retrieved successfully',
                'data' => $customers,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active customers list',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Helper to auto-generate unique customer code.
     */
    private function generateCustomerCode(): string
    {
        return Customer::generateNextCode();
    }
}
