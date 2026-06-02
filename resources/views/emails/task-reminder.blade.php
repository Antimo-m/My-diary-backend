<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Promemoria attività</title>
</head>
<body style="font-family: Arial, sans-serif; color: #172026; line-height: 1.5;">
    <h1 style="font-size: 22px; margin-bottom: 12px;">Promemoria attività</h1>

    <p>Ciao {{ $task->user->name }},</p>

    <p>hai un'attività in programma:</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Attività</strong></td>
            <td>{{ $task->title }}</td>
        </tr>
        @if ($task->description)
            <tr>
                <td><strong>Descrizione</strong></td>
                <td>{{ $task->description }}</td>
            </tr>
        @endif
        @if ($task->column)
            <tr>
                <td><strong>Colonna</strong></td>
                <td>{{ $task->column->title }}</td>
            </tr>
        @endif
        @if ($task->due_date)
            <tr>
                <td><strong>Scadenza</strong></td>
                <td>{{ $task->due_date->format('d/m/Y') }}{{ $task->due_time ? ' alle '.$task->due_time : '' }}</td>
            </tr>
        @endif
        @if ($task->reminder_at)
            <tr>
                <td><strong>Promemoria</strong></td>
                <td>{{ $task->reminder_at->format('d/m/Y H:i') }}</td>
            </tr>
        @endif
    </table>

    <p style="margin-top: 18px;">My Diary</p>
</body>
</html>
