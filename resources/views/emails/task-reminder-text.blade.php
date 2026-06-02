Ciao {{ $task->user->name }},

hai un'attività in programma.

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
@if ($task->reminder_at)
Promemoria: {{ $task->reminder_at->format('d/m/Y H:i') }}
@endif

My Diary
