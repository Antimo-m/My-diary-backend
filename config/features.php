<?php

// Feature flags dell'applicazione: si spegne una funzionalita cambiando una
// env, senza deploy di codice. Il middleware 'feature:<nome>' li applica alle
// rotte; config() li rende leggibili ovunque.
return [

    'monitoring' => (bool) env('FEATURE_MONITORING', true),
    'reports' => (bool) env('FEATURE_REPORTS', true),
    'secret_diary' => (bool) env('FEATURE_SECRET_DIARY', true),

];
