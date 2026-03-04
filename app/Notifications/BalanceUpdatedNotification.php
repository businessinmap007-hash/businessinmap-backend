<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BalanceUpdatedNotification extends Notification
{
    use Queueable;

    protected $old;
    protected $new;

    public function __construct($old, $new)
    {
        $this->old = $old;
        $this->new = $new;
    }

    public function via($notifiable)
    {
        return ['database']; // أو 'mail'
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Balance Updated',
            'message' => "Your balance has been updated from {$this->old} to {$this->new}.",
        ];
    }
}
