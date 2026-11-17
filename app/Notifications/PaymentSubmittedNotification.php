<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Application;

class PaymentSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $application;

    /**
     * Route each channel to its own queue connection.
     *
     * Mail follows QUEUE_CONNECTION like every other ARMS email: sent at once
     * on "sync", or by the queue worker on "database". (Pinning it to
     * "database" left it unsent in the jobs table wherever no worker runs.)
     *
     * The database channel stays on "sync" so the in-app notification bell still
     * updates immediately — it is a single fast INSERT with nothing to wait on.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'mail'     => config('queue.default'),
            'database' => 'sync',
        ];
    }

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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable)
    {
        return (new \App\Mail\AdminPaymentSubmittedEmail($this->application))
                    ->to($notifiable->email);
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
            'message' => 'Applicant submitted payment for ' . $this->application->tracking_number,
            'link' => '/admin/hcd/applications/awaiting-payment'
        ];
    }
}
