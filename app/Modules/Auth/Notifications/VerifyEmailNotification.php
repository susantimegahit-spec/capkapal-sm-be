<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
  use Queueable;

  public function __construct(
    public readonly string $token
  ) {}

  public function via(object $notifiable): array
  {
    return ['mail'];
  }

  public function toMail(object $notifiable): MailMessage
  {
    $url = $this->verificationUrl($notifiable);
    $expireMinutes = config('auth.verification.expire', 60);

    return (new MailMessage)
      ->subject('Verifikasi Email — ' . config('app.name'))
      ->view('emails.auth.verify-email', [
        'user'          => $notifiable,
        'url'           => $url,
        'expireMinutes' => $expireMinutes,
        'appName'       => config('app.name', 'Garam Cap Kapal'),
      ]);
  }

  protected function verificationUrl(object $notifiable): string
  {
    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

    return $frontendUrl . '/auth/verify-email?token=' . $this->token
      . '&email=' . urlencode($notifiable->getEmailForVerification());
  }
}
