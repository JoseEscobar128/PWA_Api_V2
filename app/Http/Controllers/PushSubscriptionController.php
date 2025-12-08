<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    /**
     * Store or update a push subscription for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|url',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string'
        ]);

        try {
            $user = $request->user();
            
            PushSubscription::updateOrCreate(
                [
                    'subscribable_type' => get_class($user),
                    'subscribable_id' => $user->id,
                    'endpoint' => $request->endpoint
                ],
                [
                    'public_key' => $request->input('keys.p256dh'),
                    'auth_token' => $request->input('keys.auth'),
                    'content_encoding' => 'aesgcm'
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Push subscription saved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a push subscription for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|url'
        ]);

        try {
            $user = $request->user();
            
            PushSubscription::where('subscribable_type', get_class($user))
                ->where('subscribable_id', $user->id)
                ->where('endpoint', $request->endpoint)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Push subscription deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the VAPID public key for push notifications.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function publicKey()
    {
        return response()->json([
            'success' => true,
            'publicKey' => config('webpush.vapid.public_key')
        ], 200);
    }
}
