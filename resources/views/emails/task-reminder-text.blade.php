Ciao {{ $task->user->name }},
@php
    $userTimezone = $task->user->timezone ?: config('app.timezone');
    $reminderAt = $task->reminder_at?->copy()->timezone($userTimezone);
@endphp

hai un promemoria My Diary per questa attività.

Attività: {{ $task->title }}
@if ($task->description)
Descrizione: {{ $task->description }}
@endif
@if ($task->column)
Colonna: {{ $task->column->title }}
@endif
@if ($task->due_date)
Scadenza: {{ $task->due_date->format('d/m/Y') }}{{ $task->due_time ? ' alle '.$task->due_time : '' }}
@endif
@if ($reminderAt)
Promemoria: {{ $reminderAt->format('d/m/Y H:i') }}
@endif

My Diary
