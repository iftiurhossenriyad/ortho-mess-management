# Mobile OTP Setup

Forgot-password recovery now uses a one-time password sent by SMS. The flow is:

1. Enter the Mess ID/code and User ID or registered phone number.
2. Receive a 6-digit OTP on the registered phone.
3. Verify the OTP within 10 minutes.
4. Receive a temporary password and sign in.

The app uses Twilio for SMS. Set these environment variables in the same PowerShell session before starting PHP:

```powershell
$env:TWILIO_ACCOUNT_SID = 'your-account-sid'
$env:TWILIO_AUTH_TOKEN = 'your-auth-token'
$env:TWILIO_FROM_NUMBER = '+1your-twilio-number'
```

The registered user phone number must be in international format, such as `+[country code][phone number]`. Without these credentials, the app will not reset a password and will show that SMS service is not configured. This prevents recovery without verifying the user's mobile number.