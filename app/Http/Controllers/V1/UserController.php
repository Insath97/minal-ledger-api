<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use App\Mail\UserCreateMail;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller implements HasMiddleware
{
    use ActivityLogTrait, FileUploadTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:User Index', ['only' => ['index', 'show']]),
            new Middleware('permission:User List', ['only' => ['getActiveList']]),
            new Middleware('permission:User Create', ['only' => ['store']]),
            new Middleware('permission:User Update', ['only' => ['update']]),
            new Middleware('permission:User Delete', ['only' => ['destroy']]),
            new Middleware('permission:User Toggle Status', ['only' => ['toggleStatus', 'toggleCanLogin']]),
        ];
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        try {
            $this->logActivity('INDEX', 'User', 'Viewed user list');
            $perPage = $request->get('per_page', 15);

            $query = User::with(['roles']);

            // Apply Search Scope
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('role') && $request->role != '') {
                $query->role($request->role);
            }

            $users = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'data' => $users,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve users',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(CreateUserRequest $request)
    {
        try {
            $authUser = auth()->user();
            $data = $request->validated();

            // Auto-generate password if not provided
            if (empty($data['password'])) {
                $rawPassword = Str::random(12);
            } else {
                $rawPassword = $data['password'];
            }
            $data['password'] = Hash::make($rawPassword);

            // Handle Profile Image Upload
            if ($request->hasFile('profile_image')) {
                $data['profile_image'] = $this->handleFileUpload(
                    $request,
                    'profile_image',
                    null,
                    'users'
                );
            }

            $roles = $data['roles'] ?? [];
            unset($data['roles']);

            $user = User::create($data);

            if (!empty($roles)) {
                $user->syncRoles($roles);
            }

            $user->load(['roles']);

            // Send Welcome Email if email is provided
            if (!empty($user->email)) {
                try {
                    $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
                    $mailData = [
                        'user' => $user->toArray(),
                        'password' => $rawPassword,
                        'role' => $user->roles->pluck('name')->implode(', '),
                        'login_url' => $frontendUrl . '/login',
                        'created_by' => $authUser->name ?? 'System',
                    ];

                    Mail::to($user->email)->send(new UserCreateMail($mailData));
                    $this->logActivity('EMAIL_SENT', 'User', "Welcome email sent to user: {$user->username} ({$user->email})", ['user_id' => $user->id, 'email' => $user->email]);
                } catch (\Throwable $mailEx) {
                    Log::warning("Failed to send welcome email to user {$user->username}: " . $mailEx->getMessage());
                    $this->logActivity('EMAIL_FAILED', 'User', "Failed to send welcome email to user: {$user->username} ({$user->email}) - " . $mailEx->getMessage(), ['user_id' => $user->id, 'email' => $user->email, 'error' => $mailEx->getMessage()], 'warning');
                }
            }

            $this->logActivity('CREATE', 'User', "Created user: {$user->name} ({$user->username})", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => $user,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(string $id)
    {
        try {
            $user = User::with(['roles'])->find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'User', "Viewed user: {$user->name} ({$user->username})");

            return response()->json([
                'status' => 'success',
                'message' => 'User retrieved successfully',
                'data' => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $data = $request->validated();

            // Handle Profile Image Upload & Delete Old Image
            if ($request->hasFile('profile_image')) {
                $data['profile_image'] = $this->handleFileUpload(
                    $request,
                    'profile_image',
                    $user->profile_image,
                    'users'
                );
            } elseif ($request->exists('profile_image') && (empty($request->profile_image) || $request->profile_image === 'null')) {
                $this->deleteFile($user->profile_image);
                $data['profile_image'] = null;
            } else {
                unset($data['profile_image']);
            }

            // Handle password update if provided
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $roles = $data['roles'] ?? null;
            unset($data['roles']);

            $user->update($data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            $user->load(['roles']);

            $this->logActivity('UPDATE', 'User', "Updated user: {$user->name} ({$user->username})", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(string $id)
    {
        try {
            $authUser = auth()->user();

            if ($authUser->id == $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot delete your own user account',
                ], 422);
            }

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            // Delete profile image if exists
            if (!empty($user->profile_image)) {
                $this->deleteFile($user->profile_image);
            }

            $username = $user->username;
            $name = $user->name;
            $user->delete();

            $this->logActivity('DELETE', 'User', "Deleted user: {$name} ({$username})");

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a lightweight list of active users (for dropdowns).
     */
    public function getActiveList(Request $request)
    {
        try {
            $users = User::where('is_active', true)
                ->where('can_login', true)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'username', 'email', 'phone', 'profile_image']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active users retrieved successfully',
                'data' => $users,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active users',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggleStatus(string $id)
    {
        try {
            $authUser = auth()->user();

            if ($authUser->id == $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot toggle your own active status',
                ], 422);
            }

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $user->is_active = !$user->is_active;
            $user->save();

            $this->logActivity('TOGGLE_STATUS', 'User', "Toggled active status for user: {$user->username} (" . ($user->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'User active status updated successfully',
                'data' => [
                    'id' => $user->id,
                    'is_active' => $user->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle user status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the can_login status of a user.
     */
    public function toggleCanLogin(string $id)
    {
        try {
            $authUser = auth()->user();

            if ($authUser->id == $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot toggle your own login access',
                ], 422);
            }

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $user->can_login = !$user->can_login;
            $user->save();

            $this->logActivity('TOGGLE_CAN_LOGIN', 'User', "Toggled login access for user: {$user->username} (" . ($user->can_login ? 'Can Login' : 'No Login') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'User login access updated successfully',
                'data' => [
                    'id' => $user->id,
                    'can_login' => $user->can_login,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle user login access',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
