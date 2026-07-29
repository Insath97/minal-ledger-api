<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - {{ config('app.name') }}</title>
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
                                            <path d="M21 4.5H3C2.175 4.5 1.5 5.175 1.5 6V18C1.5 18.825 2.175 19.5 3 19.5H21C21.825 19.5 22.5 18.825 22.5 18V6C22.5 5.175 21.825 4.5 21 4.5Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M12 11.25C13.6569 11.25 15 9.90685 15 8.25C15 6.59315 13.6569 5.25 12 5.25C10.3431 5.25 9 6.59315 9 8.25C9 9.90685 10.3431 11.25 12 11.25Z" stroke="white" stroke-width="1.5"/>
                                            <path d="M6.5 19.5C6.5 16.7386 8.98726 14.5 12 14.5C15.0127 14.5 17.5 16.7386 17.5 19.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </td>
                                </tr>
                            </table>
                            <h1 style="color: #ffffff; font-size: 22px; font-weight: 700; margin: 20px 0 8px; letter-spacing: -0.025em;">Reset Your Password</h1>
                            <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 0;">You requested a password reset for your account</p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px 32px;">
                            <p style="margin: 0 0 24px; font-size: 15px; color: #334155;">
                                Hi <strong>{{ $user['name'] }}</strong>,
                            </p>
                            <p style="margin: 0 0 24px; font-size: 15px; color: #334155;">
                                We received a request to reset the password for your account associated with <strong>{{ $user['email'] }}</strong>. Click the button below to create a new password:
                            </p>

                            {{-- CTA Button --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px; width: 100%;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $reset_url }}" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 14px 32px; border-radius: 12px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);">
                                            Reset Password
                                            <span style="margin-left: 6px;">&rarr;</span>
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Expiry Notice --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin: 0 0 24px;">
                                <tr>
                                    <td style="background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 10px; padding: 14px 16px;">
                                        <p style="margin: 0; font-size: 13px; color: #92400e; text-align: center;">
                                            <strong>This link expires in 30 minutes.</strong> If you didn't request a password reset, you can safely ignore this email.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- Fallback URL --}}
                            <p style="margin: 0 0 8px; font-size: 13px; color: #64748b;">
                                If the button doesn't work, copy and paste this link into your browser:
                            </p>
                            <p style="margin: 0 0 24px; font-size: 13px; word-break: break-all;">
                                <a href="{{ $reset_url }}" style="color: #10b981; text-decoration: none;">{{ $reset_url }}</a>
                            </p>

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
                                                        Use a unique password with a mix of letters, numbers, and symbols. Never share your password with anyone.
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
