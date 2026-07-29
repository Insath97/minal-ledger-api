<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f8fafc; line-height: 1.6; color: #0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 480px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%); padding: 40px 32px; text-align: center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td style="background-color: rgba(255,255,255,0.15); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,0.2);">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="8.5" cy="7" r="4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M20 8V14M23 11H17" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </td>
                                </tr>
                            </table>
                            <h1 style="color: #ffffff; font-size: 22px; font-weight: 700; margin: 20px 0 8px; letter-spacing: -0.025em;">Welcome to {{ config('app.name') }}</h1>
                            <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 0;">Your account has been successfully created</p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px 32px;">
                            <p style="margin: 0 0 24px; font-size: 15px; color: #334155;">
                                Hi <strong>{{ $user['name'] }}</strong>,
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; color: #334155;">
                                Your {{ ucfirst($role ?? 'user') }} account has been created. You can now access the platform using the credentials below:
                            </p>

                            {{-- Credentials Card --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0 0 24px;">
                                <tr>
                                    <td style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
                                        <p style="margin: 0 0 14px; font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Access Credentials</p>

                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <span style="font-size: 13px; color: #64748b;">Username</span>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                    <span style="font-size: 13px; font-weight: 600; color: #0f172a;">{{ $user['username'] }}</span>
                                                </td>
                                            </tr>
                                            @if (!empty($user['email']))
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <span style="font-size: 13px; color: #64748b;">Email</span>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                    <span style="font-size: 13px; font-weight: 600; color: #0f172a;">{{ $user['email'] }}</span>
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <span style="font-size: 13px; color: #64748b;">Password</span>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">
                                                    <span style="font-size: 13px; font-weight: 600; color: #0f172a; font-family: monospace; background-color: #f1f5f9; padding: 2px 8px; border-radius: 4px;">{{ $password }}</span>
                                                </td>
                                            </tr>
                                            @if (isset($role) && !empty($role))
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <span style="font-size: 13px; color: #64748b;">Role</span>
                                                </td>
                                                <td style="padding: 8px 0; text-align: right;">
                                                    <span style="display: inline-block; font-size: 12px; font-weight: 600; color: #047857; background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 2px 10px; border-radius: 20px;">{{ $role }}</span>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- CTA Button --}}
                            @if (isset($login_url) && !empty($login_url))
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px; width: 100%;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $login_url }}" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 14px 32px; border-radius: 12px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);">
                                            Login to Your Account
                                            <span style="margin-left: 6px;">&rarr;</span>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            {{-- Fallback URL --}}
                            @if (isset($login_url) && !empty($login_url))
                            <p style="margin: 0 0 8px; font-size: 13px; color: #64748b;">
                                If the button doesn't work, copy and paste this link into your browser:
                            </p>
                            <p style="margin: 0 0 24px; font-size: 13px; word-break: break-all;">
                                <a href="{{ $login_url }}" style="color: #10b981; text-decoration: none;">{{ $login_url }}</a>
                            </p>
                            @endif

                            {{-- Divider --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0 0 24px;">
                                <tr>
                                    <td style="border-top: 1px solid #e2e8f0; height: 1px; width: 100%;"></td>
                                </tr>
                            </table>

                            {{-- Security Tip --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0;">
                                <tr>
                                    <td style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="vertical-align: top; padding-right: 12px;">
                                                    <div style="background-color: #dcfce7; border-radius: 8px; padding: 6px; width: 28px; height: 28px; text-align: center;">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </div>
                                                </td>
                                                <td style="vertical-align: top;">
                                                    <p style="margin: 0 0 4px; font-size: 13px; font-weight: 600; color: #166534;">Security Tip</p>
                                                    <p style="margin: 0; font-size: 12px; color: #166534; opacity: 0.8;">
                                                        For your security, please change your password after your first login. Never share your credentials with anyone.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f8fafc; padding: 24px 32px; border-top: 1px solid #e2e8f0;">
                            @if (isset($created_by) && !empty($created_by))
                            <p style="margin: 0 0 8px; font-size: 12px; color: #94a3b8; text-align: center;">
                                Created by: <strong>{{ $created_by }}</strong>
                            </p>
                            @endif
                            <p style="margin: 0 0 4px; font-size: 12px; color: #94a3b8; text-align: center;">
                                This is an automated message from {{ config('app.name') }}. Please do not reply directly to this email.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #94a3b8; text-align: center;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
