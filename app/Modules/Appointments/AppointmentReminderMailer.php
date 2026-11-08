<?php

declare(strict_types=1);

namespace App\Modules\Appointments;

interface AppointmentReminderMailer
{
    /**
     * Request that the mail provider send the reminder.
     *
     * @return 'AceptadoPorProveedor'|'Fallido'|'ResultadoDesconocido'
     */
    public function requestSend(string $destination, string $subject, string $textBody, string $htmlBody): string;
}
