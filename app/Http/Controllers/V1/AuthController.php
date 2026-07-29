<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Traits\ActivityLogTrait;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Http\Requests\UpdateProfileRequest;

class AuthController extends Controller
{
    use ActivityLogTrait, FileUploadTrait;
      /**
     * Admin Login
     * Only users with user_type = 'admin' can login here
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'login' => 'required|string',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $loginVal = $request->input('login');
            $passwordVal = $request->input('password');

            // Find user by username or email
            $user = User::where('username', $loginVal)
                ->orWhere('email', $loginVal)
                ->first();

            if (!$user || !Hash::check($passwordVal, $user->password)) {
                $this->logActivity('LOGIN_FAILED', 'Auth', "Failed login attempt for login: {$loginVal}", ['login' => $loginVal], 'warning');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $token = $guard->login($user);
            $user = auth('api')->user();

            if (!$user->canLogin()) {
                $this->logActivity('LOGIN_FAILED', 'Auth', "Deactivated user attempted login: {$user->username}", ['user_id' => $user->id], 'warning');
                Auth::guard('api')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is deactivated'
                ], 401);
            }

            if (!$user->roles()->exists()) {
                $this->logActivity('LOGIN_FAILED', 'Auth', "User without role attempted login: {$user->username}", ['user_id' => $user->id], 'warning');
                Auth::guard('api')->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'No admin role assigned. Please contact Super Admin.'
                ], 403);
            }

            $user->updateLastLogin($request->ip());

            $this->logActivity('LOGIN_SUCCESS', 'Auth', "User logged in successfully: {$user->username} (IP: {$request->ip()})", [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $request->ip(),
            ]);

            $cookie = cookie(
                'auth_token',
                $token,
                60 * 24 * 7,
                '/',
                null,
                true,  // Secure
                true,  // HttpOnly
                false,
                'lax'
            );

            $user->load([
                'roles' => function ($query) {
                    $query->select('id', 'name')
                        ->with(['permissions' => function ($query) {
                            $query->select('id', 'name');
                        }]);
                }
            ]);

            if ($user->relationLoaded('roles')) {
                $user->roles->each->makeHidden(['pivot']);
                $user->roles->each(function ($role) {
                    if ($role->relationLoaded('permissions')) {
                        $role->permissions->each->makeHidden(['pivot']);
                    }
                });
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'auth_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 200)->cookie($cookie);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to login',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Admin Logout
     */
    public function logout(Request $request)
    {
        try {
            $user = auth('api')->user();
            if ($user) {
                $this->logActivity('LOGOUT', 'Auth', "User logged out: {$user->username}", ['user_id' => $user->id]);
            }

            // Logout the user (invalidates the token)
            Auth::guard('api')->logout();

            // Create an expired cookie to remove it from browser
            $cookie = Cookie::forget('auth_token');

            return response()->json([
                'status' => 'success',
                'message' => 'Logout successful'
            ], 200)->withCookie($cookie);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to logout',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function me()
    {
        try {
            $user = auth('api')->user();

            $user->load([
                'roles' => function ($query) {
                    $query->select('id', 'name')
                        ->with(['permissions' => function ($query) {
                            $query->select('id', 'name');
                        }]);
                }
            ]);

            if ($user->relationLoaded('roles')) {
                $user->roles->each->makeHidden(['pivot']);
                $user->roles->each(function ($role) {
                    if ($role->relationLoaded('permissions')) {
                        $role->permissions->each->makeHidden(['pivot']);
                    }
                });
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User details fetched successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user details',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update authenticated user profile
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        try {
            /** @var User $user */
            $user = auth('api')->user();

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
            unset($data['current_password']);
            unset($data['confirm_password']);

            $user->update($data);

            // Log activity
            $this->logActivity('UPDATE_PROFILE', 'Auth', "User updated their own profile: {$user->username}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
