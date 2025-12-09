<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
    Log::info('📥 Recibiendo suscripción push', [
        'headers' => $request->headers->all(),
        'has_bearer' => $request->bearerToken() ? 'YES' : 'NO',
        'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : null,
        'user_id' => $request->user()?->id,
        'endpoint' => $request->endpoint,
        'has_auth' => !empty($request->input('keys.auth')),
        'has_p256dh' => !empty($request->input('keys.p256dh'))
    ]);

    // Verificar autenticación ANTES de validar
    $user = $request->user();
    if (!$user) {
        Log::error('❌ Usuario NO autenticado');
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
    }

    $validated = $request->validate([
        'endpoint' => 'required|url',
        'keys.auth' => 'required|string',
        'keys.p256dh' => 'required|string'
    ]);

    try {
        Log::info('💾 Guardando suscripción', [
            'user_id' => $user->id,
            'user_type' => get_class($user)
        ]);
        
        // Usar DB directamente para evitar restricciones del modelo
        $existing = DB::table('push_subscriptions')
            ->where('endpoint', $validated['endpoint'])
            ->first();
        
        if ($existing) {
            // Actualizar suscripción existente
            DB::table('push_subscriptions')
                ->where('endpoint', $validated['endpoint'])
                ->update([
                    'subscribable_type' => get_class($user),
                    'subscribable_id' => $user->id,
                    'public_key' => $validated['keys']['p256dh'],
                    'auth_token' => $validated['keys']['auth'],
                    'content_encoding' => 'aesgcm',
                    'updated_at' => now()
                ]);
        } else {
            // Crear nueva suscripción
            DB::table('push_subscriptions')->insert([
                'subscribable_type' => get_class($user),
                'subscribable_id' => $user->id,
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => 'aesgcm',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        Log::info('✅ Suscripción guardada exitosamente');

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved successfully'
        ], 200);
    } catch (\Exception $e) {
        Log::error('❌ Error guardando suscripción push', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to save subscription',
            'error' => $e->getMessage(),
            'debug' => config('app.debug') ? $e->getTraceAsString() : null
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
