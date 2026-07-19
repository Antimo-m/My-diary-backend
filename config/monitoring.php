<?php

// Configurazione del modulo Frontend Monitoring: niente costanti sparse nel
// codice, tutto sovrascrivibile via env senza toccare i sorgenti.
return [

    // Giorni di ritenzione dei report prima del prune schedulato.
    'retention_days' => (int) env('MONITORING_RETENTION_DAYS', 90),

    // Tetto per IP al minuto sull'endpoint pubblico di raccolta.
    'reports_per_minute' => (int) env('MONITORING_REPORTS_PER_MINUTE', 10),

];
