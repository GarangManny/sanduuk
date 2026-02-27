<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    public function stkPush(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'phone' => 'required',
        ]);

        $package = Package::find($request->package_id);
        $amount = $package->price;
        $phone = $this->formatPhoneNumber($request->phone);

        // M-Pesa Credentials
        $consumerKey = env('MPESA_CONSUMER_KEY');
        $consumerSecret = env('MPESA_CONSUMER_SECRET');
        $shortCode = env('MPESA_SHORTCODE', '174379');
        $passkey = env('MPESA_PASSKEY');

        // Dynamically use Ngrok URL from .env (APP_URL should be your ngrok https url)
        $callbackUrl = rtrim(env('APP_URL'), '/') . "/api/mpesa/callback";

        // 1) Get Access Token
        $response = Http::withBasicAuth($consumerKey, $consumerSecret)
            ->get('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');

        $accessToken = $response->json()['access_token'] ?? null;

        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to get M-Pesa access token',
                'error' => $response->json()
            ], 400);
        }

        // 2) Generate Password
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($shortCode . $passkey . $timestamp);

        // 3) Initiate STK Push
        $stkResponse = Http::withToken($accessToken)->post(
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            [
                'BusinessShortCode' => $shortCode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $phone,
                'PartyB' => $shortCode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => 'WiFiBilling',
                'TransactionDesc' => 'Payment for ' . $package->name,
            ]
        );

        $resData = $stkResponse->json();

        if (isset($resData['ResponseCode']) && $resData['ResponseCode'] == '0') {
            Payment::create([
                'user_id' => auth()->id(),
                'package_id' => $package->id,
                'phone_number' => $phone,
                'amount' => $amount,
                'checkout_request_id' => $resData['CheckoutRequestID'] ?? null,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'STK Push sent!',
                'data' => $resData
            ]);
        }

        return response()->json([
            'message' => 'Failed to initiate STK',
            'error' => $resData
        ], 400);
    }

    /**
     * Handle the M-Pesa Callback from Safaricom
     * This now ACTUALLY activates internet subscription.
     */
    public function callback(Request $request)
    {
        $data = $request->all();

        // Uncomment if you need to debug callbacks:
        // Log::info('M-Pesa Callback:', $data);

        $stkCallback = $data['Body']['stkCallback'] ?? null;
        if (!$stkCallback) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback payload']);
        }

        $checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
        $resultCode = $stkCallback['ResultCode'] ?? null;

        // 1) Find the payment
        $payment = Payment::where('checkout_request_id', $checkoutRequestID)->first();

        if (!$payment) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Payment not found']);
        }

        if ((int) $resultCode === 0) {
            // --- PAYMENT SUCCESS ---

            // Safaricom metadata can vary. We'll safely try to extract receipt code.
            // Many integrations find it under CallbackMetadata->Item[*] with Name="MpesaReceiptNumber"
            $transactionCode = 'NA';
            $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
            foreach ($items as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $transactionCode = $item['Value'] ?? 'NA';
                    break;
                }
            }

            // A) Mark Payment as Completed + store receipt number
            $payment->update([
                'status' => 'completed',
                'transaction_code' => $transactionCode,
            ]);

            // B) Activate subscription
            $this->activateSubscription($payment);

            Log::info("Payment Successful for CheckoutID: {$checkoutRequestID}");
        } else {
            // --- PAYMENT FAILED ---
            $payment->update(['status' => 'failed']);
            Log::info("Payment Failed/Cancelled for CheckoutID: {$checkoutRequestID}");
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Helper: Turn on the Internet (Calendar Aware)
     * Billing is always due on the same day for monthly packages.
     */
    private function activateSubscription(Payment $payment): void
    {
        $user = $payment->user;
        $package = $payment->package;

        if (!$user || !$package) {
            Log::warning('activateSubscription failed: user/package missing', [
                'payment_id' => $payment->id,
                'user_found' => (bool) $user,
                'package_found' => (bool) $package,
            ]);
            return;
        }

        // 1) Get the Start Date
        // If they are already active, start AFTER the current one ends.
        // If they are expired or new, start NOW.
        $existingSub = $user->subscription ?? null; // latestOfMany

        if ($existingSub && ($existingSub->status === 'active') && $existingSub->expires_at && $existingSub->expires_at->isFuture()) {
            $startsAt = $existingSub->expires_at;
        } else {
            $startsAt = now();
        }

        // 2) Calculate Expiry Date (Same Day Logic)
        $expiresAt = $startsAt->copy();

        if ($package->duration_unit === 'month') {
            // Adds calendar months: Jan 15 -> Feb 15, Jan 31 -> Feb 28/29 (Carbon handles it)
            $expiresAt->addMonths((int) $package->duration_value);
        } else {
            // For 'day' or 'week' packages:
            // If you store 'week' as unit, convert to days.
            if ($package->duration_unit === 'week') {
                $expiresAt->addDays(((int) $package->duration_value) * 7);
            } else {
                // 'day' (or any other non-month treated as days)
                $expiresAt->addDays((int) $package->duration_value);
            }
        }

        // 3) Create Subscription
        Subscription::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'payment_id' => $payment->id,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => 'active',
        ]);

        // Optional sanity log:
        // Log::info("Subscription activated for User {$user->id}. Expires: {$expiresAt}");

        // TODO: Trigger MikroTik API later
        // MikroTikService::enableUser($user->ip_address, $package->speed_profile);
    }

    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            return '254' . substr($phone, 1);
        }
        return $phone;
    }
}
