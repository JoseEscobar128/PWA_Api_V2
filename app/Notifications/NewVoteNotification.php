<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Models\Place;

class NewVoteNotification extends Notification
{
    use Queueable;

    protected $place;
    protected $voterName;
    protected $totalVotes;

    /**
     * Create a new notification instance.
     */
    public function __construct(Place $place, string $voterName, int $totalVotes)
    {
        $this->place = $place;
        $this->voterName = $voterName;
        $this->totalVotes = $totalVotes;
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
            ->title('👍 ¡Nuevo voto en tu lugar!')
            ->icon('/icon-192x192.png')
            ->body("{$this->voterName} le dio me gusta a {$this->place->name}. Total: {$this->totalVotes} votos")
            ->action('Ver lugar', 'view_place')
            ->data([
                'place_id' => $this->place->id,
                'total_votes' => $this->totalVotes,
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
