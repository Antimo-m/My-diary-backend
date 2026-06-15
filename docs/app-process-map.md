# My Diary - Process Map

Schema sintetico dei processi principali dell'app, utile per presentazione tecnica o colloquio.

```mermaid
flowchart TD
    User[Utente] --> FE[Frontend React/Vite]
    FE -->|Bearer token / Sanctum API| API[Laravel API]
    API --> DB[(Database)]
    API --> Storage[(Private storage immagini)]
    API --> Mail[Mail / notifiche]

    subgraph Auth[Autenticazione]
        Login[Login / registrazione] --> Token[Token sessione API]
        Token --> Guard[Middleware auth:sanctum]
        Guard --> UserScope[Query limitate allo user autenticato]
    end

    subgraph Diary[Diario]
        DiaryList[Lista pagine] --> DiaryDetail[Dettaglio pagina]
        DiaryForm[Crea / modifica] --> DiaryValidation[Validazione input]
        DiaryValidation --> DiaryCovers[Cover private e fallback sicuro]
        DiaryCovers --> Storage
    end

    subgraph Secret[Diario segreto]
        SecretGate[Sblocco password] --> SecretSession[Sessione temporanea diario segreto]
        SecretForgot[Password dimenticata] --> SecretBroker[Password broker secret_diary]
        SecretBroker --> Mail
        SecretSession --> SecretNotes[Note segrete e cover private]
        SecretNotes --> Storage
    end

    subgraph Kanban[Kanban]
        Daily[Kanban giornaliero] --> Columns[Colonne]
        Boards[Bacheche / progetti] --> ProjectColumns[Colonne progetto]
        Columns --> Tasks[Task]
        ProjectColumns --> Tasks
        Tasks --> Labels[Etichette]
        Tasks --> Reminders[Promemoria]
        Reminders --> Mail
    end

    subgraph Analytics[Profilo e statistiche]
        Profile[Profilo utente] --> Stats[Statistiche diario / task]
        Stats --> OptimizedQueries[Query aggregate e scoped per utente]
    end

    FE --> Auth
    FE --> Diary
    FE --> Secret
    FE --> Kanban
    FE --> Analytics
    API --> Auth
    API --> Diary
    API --> Secret
    API --> Kanban
    API --> Analytics
```

## Flusso Frontend/Backend

- Il frontend chiama solo endpoint API Laravel.
- Gli endpoint privati passano da autenticazione Sanctum e lavorano sullo user autenticato.
- Le immagini diario e diario segreto sono servite tramite endpoint protetti, non come file pubblici diretti.
- Le Bacheche usano colonne e task scoped per utente/progetto; se una colonna viene eliminata, i task vengono spostati nella prima colonna disponibile della stessa board.
- I promemoria task sono preparati lato backend e inviati via mail/job, rispettando preferenze utente e timezone.
