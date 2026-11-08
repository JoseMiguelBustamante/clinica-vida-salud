<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Throwable;

final class LaravelAppointmentReminderMailer implements AppointmentReminderMailer
{
    public function __construct(private Mailer $mailer) {}

    public function requestSend(string $destination, string $subject, string $textBody, string $htmlBody): string
    {
        try {
            $this->mailer->html($htmlBody, function (Message $message) use ($destination, $subject, $textBody): void {
                $message->to($destination)->subject($subject)->text($textBody);
            });

            return AppointmentReminderService::PROVIDER_ACCEPTED;
        } catch (Throwable) {
            return AppointmentReminderService::PROVIDER_FAILED;
        }
    }
}
