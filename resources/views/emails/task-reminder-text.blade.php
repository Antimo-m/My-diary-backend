Ciao {{ $task->user->name }},
hai un promemoria My Diary per questa attività.

Attività: {{ $task->title }}
@if ($task->description)
Descrizione: {{ $task->description }}
@endif
@if ($task->column)
Colonna: {{ $task->column->title }}
@endif
@if ($dueAtLabel)
Scadenza: {{ $dueAtLabel }}
@endif
@if ($reminderAtLabel)
Promemoria: {{ $reminderAtLabel }}
@endif

My Diary
