<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification implements ShouldQueue
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
    $url = $this->resetUrl($notifiable);
    $expireMinutes = config('auth.passwords.users.expire', 60);

    return (new MailMessage)
      ->subject('Reset Password — ' . "Garam Capkapal")
      ->view('emails.auth.reset-password', [
        'user'          => $notifiable,
        'url'           => $url,
        'expireMinutes' => $expireMinutes,
        'appName'       => "Garam Capkapal",
      ]);
  }

  protected function resetUrl(object $notifiable): string
  {
    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

    return $frontendUrl . '/auth/reset-password?token=' . $this->token
      . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
  }
}
