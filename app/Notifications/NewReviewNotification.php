<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Models\Review;
use App\Models\Place;

class NewReviewNotification extends Notification
{
    use Queueable;

    protected $review;
    protected $place;
    protected $reviewerName;

    /**
     * Create a new notification instance.
     */
    public function __construct(Review $review, Place $place, string $reviewerName)
    {
        $this->review = $review;
        $this->place = $place;
        $this->reviewerName = $reviewerName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('⭐ Nueva reseña en tu lugar')
            ->icon('/icon-192x192.png')
            ->body("{$this->reviewerName} dejó una reseña de {$this->review->rating} estrellas en {$this->place->name}")
            ->action('Ver reseña', 'view_review')
            ->data([
                'place_id' => $this->place->id,
                'review_id' => $this->review->id,
                'rating' => $this->review->rating,
                'url' => "/places/{$this->place->id}"
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
