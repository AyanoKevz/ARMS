<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Application;

class NewApplicationSubmittedNotification extends Notification
{
    use Queueable;

    protected $application;

    /**
     * Create a new notification instance.
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'tracking_number' => $this->application->tracking_number,
            'message' => 'New application ' . $this->application->tracking_number . ' has been submitted by ' . ($this->application->user->name ?? 'Applicant') . ' and is ready for review.',
            'link' => '/admin/hcd/applications/pending'
        ];
    }
}
