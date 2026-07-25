<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to {{ config('app.name') }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Welcome to {{ config('app.name') }}, {{ $user['name'] }}!</h2>
    <p>Your account has been successfully created. You can access the platform using the credentials below:</p>

    <h3>Access Credentials</h3>
    <ul>
        <li><strong>Username:</strong> {{ $user['username'] }}</li>
        @if (!empty($user['email']))
            <li><strong>Email:</strong> {{ $user['email'] }}</li>
        @endif
        <li><strong>Password:</strong> {{ $password }}</li>
        @if (isset($role) && !empty($role))
            <li><strong>Assigned Role:</strong> {{ $role }}</li>
        @endif
        @if (isset($user['user_scope']) && !empty($user['user_scope']))
            <li><strong>Access Scope:</strong> {{ ucfirst($user['user_scope']) }}</li>
        @endif
    </ul>

    <h3>Organization Scope</h3>
    <ul>
        @if (isset($branch_name) && !empty($branch_name))
            <li><strong>Branch:</strong> {{ $branch_name }}</li>
        @endif
        @if (isset($warehouse_name) && !empty($warehouse_name))
            <li><strong>Warehouse:</strong> {{ $warehouse_name }}</li>
        @endif
    </ul>

    @if (isset($login_url) && !empty($login_url))
        <p>
            <a href="{{ $login_url }}" style="display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: #ffffff; text-decoration: none; border-radius: 5px;">Login to Your Account</a>
        </p>
    @endif

    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="font-size: 12px; color: #777;">This is an automated message from {{ config('app.name') }}. Please do not reply directly to this email.</p>
    @if (isset($created_by) && !empty($created_by))
        <p style="font-size: 12px; color: #777;">Created by: {{ $created_by }}</p>
    @endif
</body>
</html>
