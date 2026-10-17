# Mobile OTP Setup

Forgot-password recovery now uses a one-time password sent by SMS. The flow is:

1. Enter the Mess ID/code and User ID or registered phone number.
2. Receive a 6-digit OTP on the registered phone.
3. Verify the OTP within 10 minutes.
4. Receive a temporary password and sign in.

The app uses Twilio for SMS. Before testing, create a Twilio account, obtain an SMS-capable Twilio sender number (or an approved sender supported by your account), and verify the destination number if your Twilio account is on a trial plan. Twilio trial restrictions and message charges apply.

Keep the Account SID and Auth Token private. Start the SMS-enabled launcher from the project folder; it securely prompts for the Auth Token and passes credentials only to the app process:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start-with-sms.ps1
```

The registered user phone number must be in international format, such as `+[country code][phone number]`. Without these credentials, the app will not reset a password and will show that SMS service is not configured. This prevents recovery without verifying the user's mobile number.

If the PHP app is already running on port 8000, stop it before starting the SMS-enabled launcher so the new credentials are applied. The launcher refuses to continue while another app server is using that port.

If the SMS provider rejects a request, the user sees a generic delivery error and the server log records the HTTP status only; provider credentials and phone numbers are not included in that diagnostic.