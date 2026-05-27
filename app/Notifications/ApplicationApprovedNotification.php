<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Application;

class ApplicationApprovedNotification extends Notification
{
    use Queueable;

    protected $application;
    protected $accreditationNumber;

    /**
     * Create a new notification instance.
     */
    public function __construct(Application $application, string $accreditationNumber)
    {
        $this->application = $application;
        $this->accreditationNumber = $accreditationNumber;
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
            'message' => 'Congratulations! Your accreditation application ' . $this->application->tracking_number . ' has been approved. Issued Number: ' . $this->accreditationNumber,
            'link' => '/applicant/dashboard'
        ];
    }
}
