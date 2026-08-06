<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Register FCM token for push notifications.
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $this->getAuthenticatedCustomer($request);

        $customer->update([
            'fcm_token' => $request->fcm_token,
            'fcm_token_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token registered successfully',
            'data' => [
                'fcm_token' => $customer->fcm_token,
                'fcm_token_verified_at' => $customer->fcm_token_verified_at?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Get notification preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        return response()->json([
            'success' => true,
            'data' => [
                'push_notifications_enabled' => $customer->fcm_token !== null,
                'email_notifications_enabled' => $customer->promotional_email_opt_in ?? true,
                'sms_notifications_enabled' => $customer->promotional_sms_opt_in ?? true,
            ],
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'push_notifications_enabled' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $this->getAuthenticatedCustomer($request);

        $updates = [];

        if ($request->has('push_notifications_enabled')) {
            if (!$request->push_notifications_enabled) {
                $updates['fcm_token'] = null;
                $updates['fcm_token_verified_at'] = null;
            }
        }

        if ($request->has('email_notifications_enabled')) {
            $updates['promotional_email_opt_in'] = $request->email_notifications_enabled;
        }

        if ($request->has('sms_notifications_enabled')) {
            $updates['promotional_sms_opt_in'] = $request->sms_notifications_enabled;
        }

        if (!empty($updates)) {
            $customer->update($updates);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated',
            'data' => [
                'push_notifications_enabled' => $customer->fresh()->fcm_token !== null,
                'email_notifications_enabled' => $customer->fresh()->promotional_email_opt_in ?? true,
                'sms_notifications_enabled' => $customer->fresh()->promotional_sms_opt_in ?? true,
            ],
        ]);
    }

    /**
     * Get the authenticated customer from the request.
     */
    protected function getAuthenticatedCustomer(Request $request): Customer
    {
        $user = $request->user();

        if ($user->customer) {
            return $user->customer;
        }

        $customer = Customer::where('email', $user->email)->first();

        if (!$customer) {
            abort(403, 'Customer not found for authenticated user.');
        }

        return $customer;
    }
}
