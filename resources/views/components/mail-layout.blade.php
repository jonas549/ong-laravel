<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#faf7f3;font-family:'Inter',Arial,Helvetica,sans-serif;color:#33363a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f3;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px;background:#ffffff;border:1px solid #eceef0;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="padding:26px 32px 0;">
                            <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:#e57200;">
                                {{ config('app.name') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 32px 30px;font-size:15px;line-height:1.6;color:#4a4d51;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px 26px;border-top:1px solid #f2f3f4;font-size:12.5px;color:#8f9399;">
                            Recibiste este correo porque tienes una cuenta en {{ config('app.name') }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
