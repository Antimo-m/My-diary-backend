<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Reset password Diario Segreto</title>
</head>
<body style="margin:0;background:#fff3df;color:#172026;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff3df;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:22px;border:1px solid rgba(23,32,38,.1);overflow:hidden;">
                    <tr>
                        <td style="background:#056274;color:#ffffff;padding:24px 28px;">
                            <strong style="font-size:18px;">My Diary</strong>
                            <h1 style="margin:10px 0 0;font-size:26px;">Diario Segreto</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="font-size:16px;line-height:1.6;margin:0 0 18px;">Ciao {{ $user->name }}, abbiamo ricevuto una richiesta per reimpostare la password del tuo Diario Segreto.</p>
                            <p style="font-size:16px;line-height:1.6;margin:0 0 24px;">Il link e personale e scade automaticamente. Se non hai richiesto tu questa operazione puoi ignorare questa email.</p>
                            <a href="{{ $url }}" style="display:inline-block;background:#00a7c8;color:#ffffff;text-decoration:none;border-radius:999px;padding:13px 20px;font-weight:700;">Reimposta password</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
