<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Throwable;

final class LaravelAppointmentReminderMailer implements AppointmentReminderMailer
{
    public function __construct(private Mailer $mailer) {}

    public function requestSend(string $destination, string $subject, string $body): string
    {
        try {
            $this->mailer->raw($body, function (Message $message) use ($destination, $subject): void {
                $message->to($destination)->subject($subject);
            });

            return AppointmentReminderService::PROVIDER_ACCEPTED;
        } catch (Throwable) {
            return AppointmentReminderService::PROVIDER_FAILED;
        }
    }
}
