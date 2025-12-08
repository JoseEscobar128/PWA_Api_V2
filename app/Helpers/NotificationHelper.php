<?php

namespace App\Helpers;

use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    /**
     * Enviar notificación a todos los administradores
     *
     * @param string $title
     * @param string $message
     * @param array $data
     * @return void
     */
    public static function notifyAdmins(string $title, string $message, array $data = [])
    {
        try {
            $admins = User::whereHas('roles', function($query) {
                $query->where('name', 'admin');
            })->get();

            foreach ($admins as $admin) {
                $admin->notify(new AdminAlertNotification($title, $message, $data));
            }

            Log::info("Admin notification sent: {$title}");
        } catch (\Exception $e) {
            Log::error("Failed to send admin notification: " . $e->getMessage());
        }
    }

    /**
     * Notificar sobre nuevo lugar creado (moderación)
     *
     * @param \App\Models\Place $place
     * @return void
     */
    public static function notifyNewPlace($place)
    {
        self::notifyAdmins(
            'Nuevo lugar creado',
            "Se ha creado el lugar '{$place->name}' por {$place->user->name}. Requiere revisión.",
            [
                'type' => 'new_place',
                'place_id' => $place->id,
                'user_id' => $place->user_id,
                'url' => "/admin/places/{$place->id}"
            ]
        );
    }

    /**
     * Notificar sobre reseña reportada o problemática
     *
     * @param \App\Models\Review $review
     * @param string $reason
     * @return void
     */
    public static function notifyReportedReview($review, string $reason = 'Contenido inapropiado')
    {
        self::notifyAdmins(
            'Reseña reportada',
            "Una reseña ha sido reportada: {$reason}",
            [
                'type' => 'reported_review',
                'review_id' => $review->id,
                'place_id' => $review->place_id,
                'user_id' => $review->user_id,
                'reason' => $reason,
                'url' => "/admin/reviews/{$review->id}"
            ]
        );
    }

    /**
     * Notificar sobre múltiples votos sospechosos
     *
     * @param \App\Models\Place $place
     * @param int $votesInShortTime
     * @return void
     */
    public static function notifySuspiciousVotes($place, int $votesInShortTime)
    {
        self::notifyAdmins(
            'Actividad sospechosa detectada',
            "El lugar '{$place->name}' recibió {$votesInShortTime} votos en poco tiempo. Posible manipulación.",
            [
                'type' => 'suspicious_votes',
                'place_id' => $place->id,
                'votes_count' => $votesInShortTime,
                'url' => "/admin/places/{$place->id}/votes"
            ]
        );
    }
}
