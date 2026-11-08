<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f7f6;font-family:Georgia,'Times New Roman',serif;color:#163238;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f7f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #d7e5e0;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#0f5c52;padding:22px 28px;">
                            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#b7e4d8;">{{ $clinicName }}</p>
                            <h1 style="margin:8px 0 0;font-size:24px;line-height:1.25;font-weight:normal;color:#ffffff;">Recordatorio de cita</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#163238;">
                                Hola, {{ $patientName }}.
                            </p>
                            <p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#5b6f6c;">
                                Le recordamos su cita programada. Conserve este mensaje como referencia; no confirma asistencia ni reemplaza su documento de identidad.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f7f6;border:1px solid #d7e5e0;border-radius:6px;">
                                <tr>
                                    <td style="padding:18px 20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#163238;">
                                        <p style="margin:0 0 10px;"><strong style="color:#0f5c52;">Código</strong><br>{{ $codigo }}</p>
                                        <p style="margin:0 0 10px;"><strong style="color:#0f5c52;">Fecha</strong><br>{{ $fecha }}</p>
                                        <p style="margin:0 0 10px;"><strong style="color:#0f5c52;">Horario</strong><br>{{ $horario }}</p>
                                        <p style="margin:0 0 10px;"><strong style="color:#0f5c52;">Médico</strong><br>{{ $medico }}</p>
                                        <p style="margin:0 0 10px;"><strong style="color:#0f5c52;">Especialidad</strong><br>{{ $especialidad }}</p>
                                        <p style="margin:0;"><strong style="color:#0f5c52;">Modalidad</strong><br>{{ $modalidad }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:22px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:#5b6f6c;">
                                Si necesita reprogramar o cancelar, comuníquese con la clínica con anticipación. Este correo no registra llegada ni acredita atención médica.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 22px;border-top:1px solid #d7e5e0;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.45;color:#5b6f6c;">
                            {{ $clinicName }} · Mensaje automático de recordatorio
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
