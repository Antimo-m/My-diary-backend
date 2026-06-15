<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Promemoria attività</title>
</head>
@php
    $taskColor = $task->color ?: ($task->column?->color ?: '#00a7c8');
    $taskColor = preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', $taskColor) ? $taskColor : '#00a7c8';
    $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');
@endphp
<body style="margin:0; padding:0; background:#fff3df; color:#172026; font-family:'Avenir Next','Nunito Sans',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; background:#fff3df; padding:28px 0;">
        <tr>
            <td align="center" style="padding:28px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px; border-collapse:separate; border-spacing:0; background:#ffffff; border:1px solid rgba(41,49,61,0.12); border-radius:28px; overflow:hidden; box-shadow:0 18px 45px rgba(24,32,46,0.10);">
                    <tr>
                        <td style="padding:0; background:#00a7c8;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="padding:28px 30px 24px; color:#ffffff;">
                                        <div style="font-size:13px; line-height:18px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#d8f8ff;">My Diary</div>
                                        <h1 style="margin:8px 0 0; font-size:28px; line-height:34px; font-weight:800; color:#ffffff;">Promemoria attività</h1>
                                        <p style="margin:10px 0 0; font-size:16px; line-height:24px; color:#eaffff;">Ciao {{ $task->user->name }}, hai un'attività in programma.</p>
                                    </td>
                                    <td width="96" valign="top" style="padding:26px 28px 0 0;">
                                        <div style="width:64px; height:64px; border-radius:22px; background:#ff6b4a; color:#ffffff; text-align:center; line-height:64px; font-size:30px; font-weight:800;">!</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; border-spacing:0; background:#fffaf0; border:1px solid #e8ddc8; border-radius:20px;">
                                <tr>
                                    <td style="padding:22px 22px 18px; border-left:6px solid {{ $taskColor }}; border-radius:20px;">
                                        <div style="font-size:13px; line-height:18px; font-weight:700; color:#66717d; text-transform:uppercase; letter-spacing:0.06em;">Attività</div>
                                        <h2 style="margin:6px 0 0; font-size:24px; line-height:30px; color:#172026;">{{ $task->title }}</h2>

                                        @if ($task->description)
                                            <p style="margin:14px 0 0; font-size:15px; line-height:23px; color:#44515d;">{!! nl2br(e($task->description)) !!}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px; border-collapse:separate; border-spacing:0 10px;">
                                @if ($task->column)
                                    <tr>
                                        <td width="120" style="padding:12px 14px; background:#f7f8fb; border-radius:14px 0 0 14px; color:#66717d; font-size:13px; font-weight:700;">Colonna</td>
                                        <td style="padding:12px 14px; background:#f7f8fb; border-radius:0 14px 14px 0; color:#172026; font-size:15px;">{{ $task->column->title }}</td>
                                    </tr>
                                @endif
                                @if ($dueAtLabel)
                                    <tr>
                                        <td width="120" style="padding:12px 14px; background:#d8f8ff; border-radius:14px 0 0 14px; color:#056274; font-size:13px; font-weight:700;">Scadenza</td>
                                        <td style="padding:12px 14px; background:#d8f8ff; border-radius:0 14px 14px 0; color:#056274; font-size:15px; font-weight:700;">{{ $dueAtLabel }}</td>
                                    </tr>
                                @endif
                                @if ($reminderAtLabel)
                                    <tr>
                                        <td width="120" style="padding:12px 14px; background:#ffe1d8; border-radius:14px 0 0 14px; color:#9d3b25; font-size:13px; font-weight:700;">Promemoria</td>
                                        <td style="padding:12px 14px; background:#ffe1d8; border-radius:0 14px 14px 0; color:#9d3b25; font-size:15px; font-weight:700;">{{ $reminderAtLabel }}</td>
                                    </tr>
                                @endif
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:20px; border-collapse:collapse;">
                                <tr>
                                    <td style="border-radius:999px; background:#00a7c8;">
                                        <a href="{{ $frontendUrl }}" style="display:inline-block; padding:13px 20px; color:#ffffff; font-size:15px; line-height:20px; font-weight:800; text-decoration:none; border-radius:999px;">Apri My Diary</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 30px 28px; background:#f7f8fb; border-top:1px solid rgba(41,49,61,0.08);">
                            <p style="margin:0; color:#66717d; font-size:13px; line-height:20px;">Ricevi questa email perché hai attivato i promemoria per le attività Kanban.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
