# 4. System Diagrams

All diagrams are written in Mermaid and render in GitHub, Visual Studio Code (Markdown preview with Mermaid support), and the Mermaid Live Editor. Each diagram reflects the implementation described in Document 3.

Notation for the data flow diagrams: rectangles are external entities, rounded nodes are processes, and cylinders are data stores.

---

## 4.1 Context Diagram

```mermaid
flowchart LR
    F[Farmer]
    M[MAO Personnel]
    I[IT Expert]
    V[Visitor]
    OM[Open-Meteo]
    PG[PAGASA Website]
    GR[GeoRisk Philippines]
    GQ[Groq API]
    ML[Python ML Service]
    SM[SMTP Mail Server]

    S((iClimate<br/>System))

    V -- registration, contact message --> S
    S -- landing statistics, weather --> V

    F -- farm data, planting schedules, harvests,<br/>prediction requests, feedback, messages --> S
    S -- weather, risk level, yield estimate,<br/>advisories, alerts, reports --> F

    M -- climate and production records, approvals,<br/>advisories, alerts, barangay profiles --> S
    S -- dashboards, analyses, forecasts,<br/>reports, activity log --> M

    I -- accounts, settings, permissions,<br/>backup and model commands --> S
    S -- system health, logs, evaluation,<br/>backup files --> I

    S -- coordinates of 19 barangays --> OM
    OM -- current weather, daily forecast --> S
    PG -- bulletins and advisories --> S
    GR -- barangay boundaries --> S
    S -- question and grounded context --> GQ
    GQ -- generated answer --> S
    S -- model features --> ML
    ML -- predicted yield, metrics --> S
    S -- account and reset e-mails --> SM
```

---

## 4.2 Data Flow Diagram: Level 0

```mermaid
flowchart TB
    F[Farmer]
    M[MAO Personnel]
    I[IT Expert]
    EXT[External Weather Sources<br/>Open-Meteo, PAGASA, GeoRisk]
    ML[Python ML Service]
    GQ[Groq API]

    P1([1.0 Manage Accounts<br/>and Access])
    P2([2.0 Manage Farmer<br/>and Farm Records])
    P3([3.0 Acquire Weather<br/>and Rate Risk])
    P4([4.0 Manage Climate and<br/>Production Records])
    P5([5.0 Forecast Weather<br/>and Predict Yield])
    P6([6.0 Produce Advisories<br/>and Alerts])
    P7([7.0 Communicate<br/>and Notify])
    P8([8.0 Generate Reports])
    P9([9.0 Administer<br/>the System])

    D1[(D1 Users)]
    D2[(D2 Farmers and Farms)]
    D3[(D3 Weather and Risk)]
    D4[(D4 Climate and Production)]
    D5[(D5 Predictions)]
    D6[(D6 Advisories)]
    D7[(D7 Communication)]
    D8[(D8 Reports)]
    D9[(D9 Logs, Models, Backups)]

    F -- registration, credentials --> P1
    M -- approval decision --> P1
    I -- account data --> P1
    P1 <--> D1
    P1 -- audit entry --> D9

    F -- farm, schedule, harvest --> P2
    M -- profile, association decision --> P2
    P2 <--> D2

    EXT -- weather, bulletins, boundaries --> P3
    P3 <--> D3
    D2 -- farm type --> P3
    D6 -- typhoon advisory --> P3
    P3 -- risk map --> F
    P3 -- risk map --> M

    M -- records, CSV, validation --> P4
    P4 <--> D4

    D4 -- climate history, yield records --> P5
    D2 -- farm inputs --> P5
    P5 <-->|features / yield| ML
    P5 <--> D5
    F -- prediction request --> P5
    M -- forecast request --> P5
    P5 -- estimates, forecasts --> F
    P5 -- forecasts, analyses --> M

    D3 -- forecast --> P6
    EXT -- PAGASA advisories --> P6
    M -- review, alert --> P6
    P6 <--> D6
    P6 -- advisory --> F
    F -- feedback --> P6

    P6 -- publication event --> P7
    P1 -- account event --> P7
    F <-->|messages, questions / answers| P7
    M <-->|messages, announcements| P7
    P7 <--> D7
    P7 <-->|question / answer| GQ
    D3 -- stored data --> P7
    D5 -- stored data --> P7

    M -- report request --> P8
    F -- report request --> P8
    D2 --> P8
    D4 --> P8
    D5 --> P8
    D6 --> P8
    P8 <--> D8
    P8 -- report, export --> M
    P8 -- PDF --> F

    I -- settings, backup, model commands --> P9
    P9 <--> D9
    P9 -- model switch --> ML
    P9 -- health, logs --> I
```

**Data stores and their tables**

| Store | Tables |
|---|---|
| D1 Users | `users`, `sessions`, `password_reset_tokens`, `mao_user_barangays` |
| D2 Farmers and Farms | `farmer_profiles`, `farms`, `farm_boundaries`, `farmer_planting_schedules`, `farmer_organizations`, `farmer_organization_members`, `barangay_profiles`, `rice_varieties` |
| D3 Weather and Risk | `external_weather_data`, `weather_fetch_logs`, `heatmap_areas` |
| D4 Climate and Production | `climate_records`, `rice_productions`, `historical_rice_productions` |
| D5 Predictions | `monthly_weather_predictions`, `yield_predictions`, `yield_prediction_requests`, `municipal_yield_predictions`, `yield_prediction_feedback`, `weather_impact_analyses`, `analytics_runs` |
| D6 Advisories | `planting_advisories`, `advisory_rules`, `advisory_feedback`, `typhoon_safety_responses` |
| D7 Communication | `notifications`, `conversations`, `conversation_messages`, `feed_posts`, `feed_media`, `feed_comments`, `feed_reactions`, `a_i_chats`, `ai_*`, `knowledge_base`, `chatbot_responses` |
| D8 Reports | `reports` |
| D9 Logs, Models, Backups | `system_logs`, `mao_activity_logs`, `ml_models`, `backup_verifications`, `maintenance_records`, `scheduled_task_runs`, `farmer_survey_responses`, `model_feedback_decisions`, settings file |

---

## 4.3 Data Flow Diagrams: Level 1

### 4.3.1 Process 1.0: Manage Accounts and Access

```mermaid
flowchart LR
    V[Visitor]
    U[Registered User]
    M[MAO Personnel]
    I[IT Personnel]

    P11([1.1 Register Farmer])
    P12([1.2 Approve or<br/>Reject Farmer])
    P13([1.3 Authenticate])
    P14([1.4 Enforce Role<br/>and Module Access])
    P15([1.5 Manage Accounts])
    P16([1.6 Reset Password])

    D1[(D1 Users)]
    D2[(D2 Farmer Profiles)]
    D7[(D7 Notifications)]
    D9[(D9 System Logs)]
    DS[(Settings File)]

    V -- details, CAPTCHA --> P11
    P11 -- pending account --> D1
    P11 -- profile --> D2
    P11 -- approval notice --> D7

    M -- decision, reason --> P12
    D1 -- pending accounts --> P12
    P12 -- status --> D1
    P12 -- result notice --> D7

    U -- e-mail, password, CAPTCHA --> P13
    D1 -- stored hash, status --> P13
    DS -- lockout policy --> P13
    P13 -- session --> P14
    P13 -- success or failure --> D9

    DS -- permission matrix --> P14
    P14 -- permitted page --> U

    I -- account data --> P15
    P15 <--> D1
    P15 -- audit --> D9

    U -- reset request --> P16
    I -- reset command --> P16
    P16 -- new hash --> D1
```

### 4.3.2 Process 3.0: Acquire Weather and Rate Risk

```mermaid
flowchart LR
    SCH[Scheduler]
    OM[Open-Meteo]
    PG[PAGASA]
    GR[GeoRisk]
    USR[Farmer / MAO Personnel]

    P31([3.1 Fetch Barangay<br/>Weather])
    P32([3.2 Fetch PAGASA<br/>Bulletins])
    P33([3.3 Rate Weekly Risk])
    P34([3.4 Compute Composite<br/>Risk Score])
    P35([3.5 Refresh Boundaries])
    P36([3.6 Serve Heat Map])

    D3a[(external_weather_data)]
    D3b[(weather_fetch_logs)]
    D3c[(heatmap_areas)]
    D6[(planting_advisories)]
    D2[(barangay_profiles,<br/>farmer_profiles)]
    D5[(yield_predictions)]
    DC[(Boundary Cache)]

    SCH -- every 10 min --> P31
    OM -- forecast --> P31
    P31 --> D3a
    P31 --> D3b

    SCH -- every 15 min --> P32
    PG -- bulletins --> P32
    P32 --> D6

    D3a -- daily forecast --> P33
    D6 -- typhoon advisory --> P33
    D2 -- farm type --> P33
    P33 -- level, main factor --> P36

    D3a --> P34
    D2 --> P34
    P34 --> D3c

    GR -- polygons --> P35
    P35 --> DC

    USR -- range, layer --> P36
    DC --> P36
    D3c --> P36
    D5 -- yield layer --> P36
    D2 -- flood, drought marks --> P36
    P36 -- coloured map, detail panel --> USR
```

### 4.3.3 Process 5.0: Forecast Weather and Predict Yield

```mermaid
flowchart LR
    SCH[Scheduler]
    F[Farmer]
    M[MAO Personnel]
    ML[Python ML Service]

    P51([5.1 Generate Monthly<br/>Weather Forecast])
    P52([5.2 Assemble<br/>Model Input])
    P53([5.3 Run Rice<br/>Yield Model])
    P54([5.4 Store Farm and<br/>Barangay Estimates])
    P55([5.5 Process Prediction<br/>Request])
    P56([5.6 Analyse Weather<br/>Impact])
    P57([5.7 Record Actual<br/>Harvest])

    D4[(climate_records)]
    D4b[(rice_productions)]
    D5a[(monthly_weather_predictions)]
    D5b[(yield_predictions)]
    D5c[(yield_prediction_requests)]
    D5d[(yield_prediction_feedback)]
    D5e[(weather_impact_analyses)]
    D2[(farms,<br/>farmer_planting_schedules)]

    SCH -- daily 02:00 --> P51
    D4 -- history --> P51
    P51 --> D5a

    D4 --> P52
    D5a --> P52
    D2 -- area, season --> P52
    P52 -- features --> P53
    P53 <-->|features / yield| ML

    SCH -- every 3 h --> P54
    P53 --> P54
    P54 --> D5b

    F -- season, irrigation,<br/>barangay, area --> P55
    M -- scope, season --> P55
    P55 --> P52
    P53 -- yield --> P55
    D4b -- historical average --> P55
    P55 <--> D5c
    P55 -- result, impact level --> F
    P55 -- forecast --> M

    D4 --> P56
    D4b --> P56
    M -- filters --> P56
    P56 --> D5e
    P56 -- ranked correlations --> M

    F -- harvest date, actual yield --> P57
    D5b -- predicted value --> P57
    P57 --> D2
    P57 --> D5d
```

### 4.3.4 Process 6.0: Produce Advisories and Alerts

```mermaid
flowchart LR
    SCH[Scheduler]
    M[MAO Personnel]
    F[Farmer]
    PG[PAGASA]

    P61([6.1 Generate Advisories<br/>from Rules])
    P62([6.2 Store PAGASA<br/>Advisories])
    P63([6.3 Review and<br/>Publish])
    P64([6.4 Send or<br/>Schedule Alert])
    P65([6.5 Notify Farmers])
    P66([6.6 Record Feedback])
    P67([6.7 Expire Advisories])

    D3[(external_weather_data)]
    DR[(advisory_rules)]
    DA[(planting_advisories)]
    DN[(notifications)]
    DF[(advisory_feedback)]
    DU[(users)]

    SCH --> P61
    D3 -- forecast metrics --> P61
    DR -- active rules --> P61
    P61 -- draft --> DA

    PG --> P62
    P62 --> DA

    M -- edit, approve, reject --> P63
    DA --> P63
    P63 -- published --> DA
    P63 --> P65

    M -- type, severity, recipients, time --> P64
    P64 -- published or scheduled --> DA
    SCH -- every minute --> P64
    P64 --> P65

    DU -- farmers of target barangay --> P65
    P65 --> DN
    DN -- alert --> F
    DA -- advisory --> F

    F -- helpful, comment --> P66
    P66 --> DF
    M -- response --> P66

    SCH --> P67
    P67 --> DA
```

### 4.3.5 Process 9.0: Administer the System

```mermaid
flowchart LR
    I[IT Personnel]
    SCH[Scheduler]
    ML[Python ML Service]

    P91([9.1 Configure Settings<br/>and Permissions])
    P92([9.2 Back Up, Verify,<br/>Restore])
    P93([9.3 Manage and<br/>Compare Models])
    P94([9.4 Validate and<br/>Activate Model])
    P95([9.5 Monitor Health])
    P96([9.6 Review Logs])
    P97([9.7 Review Model<br/>Feedback])

    DS[(Settings File)]
    DB[(Backup Files)]
    DV[(backup_verifications)]
    DM[(ml_models)]
    MF[(Model Files)]
    DT[(scheduled_task_runs,<br/>weather_fetch_logs, jobs)]
    DL[(system_logs)]
    DR[(maintenance_records)]
    DFS[(farmer_survey_responses,<br/>model_feedback_decisions)]

    I -- values --> P91
    P91 --> DS
    P91 --> DL

    SCH -- daily 03:15, 03:25 --> P92
    I -- back up, restore --> P92
    P92 <--> DB
    P92 --> DV
    P92 --> DR

    I -- dataset, compare, retrain --> P93
    P93 -- candidate --> DM
    P93 --> MF
    P93 --> DR

    I -- validate, activate --> P94
    DM <--> P94
    P94 -- switch command --> ML
    ML -- loaded model --> P94

    SCH -- every 2 min --> P95
    DT --> P95
    DV --> P95
    ML -- health --> P95
    P95 -- status --> I

    DL --> P96
    P96 -- list, CSV --> I

    DFS <--> P97
    I -- decision --> P97
```

---

## 4.4 Use Case Diagrams

Mermaid has no native use case notation; the diagrams below use actors as rectangles and use cases as rounded nodes within a system boundary.

### 4.4.1 Farmer

```mermaid
flowchart LR
    F[Farmer]
    subgraph iClimate
        U1([Register and sign in])
        U2([View dashboard])
        U3([Manage farms])
        U4([Manage planting schedules])
        U5([Record actual harvest])
        U6([Request yield prediction])
        U7([View forecast and weather analysis])
        U8([View Heat Map])
        U9([Read advisories and announcements])
        U10([Give feedback and answer surveys])
        U11([Respond to typhoon safety check-in])
        U12([Download My Reports])
        U13([Send and receive messages])
        U14([Read notifications])
        U15([Ask Climora])
        U16([Manage profile and language])
        U17([Browse rice varieties])
    end
    F --- U1 & U2 & U3 & U4 & U5 & U6 & U7 & U8 & U9
    F --- U10 & U11 & U12 & U13 & U14 & U15 & U16 & U17
    U5 -. includes .-> U4
    U4 -. includes .-> U17
    U6 -. extends .-> U2
```

### 4.4.2 MAO Personnel and Municipal Agriculturist

```mermaid
flowchart LR
    M[MAO Personnel]
    H[Municipal Agriculturist]
    subgraph iClimate
        M1([View MAO dashboard])
        M2([Approve or reject farmers])
        M3([Manage farmer profiles])
        M4([Review farm associations])
        M5([Manage climate and production records])
        M6([Import records by CSV])
        M7([Validate records])
        M8([Manage barangay profiles])
        M9([Manage advisories])
        M10([Send or schedule alerts])
        M11([View Heat Map and predictions])
        M12([Analyse weather impact])
        M13([Run yield forecast])
        M14([Generate and export reports])
        M15([Post announcements])
        M16([Maintain MAO Planner])
        M17([View activity log])
        M18([Messages, notifications, Climora])
        H1([Manage MAO personnel accounts])
        H2([Assign barangay coverage])
        H3([View all activity logs])
    end
    M --- M1 & M2 & M3 & M4 & M5 & M6 & M7 & M8 & M9
    M --- M10 & M11 & M12 & M13 & M14 & M15 & M16 & M17 & M18
    H --- H1 & H2 & H3
    H -. inherits .-> M
    M6 -. extends .-> M5
    M7 -. extends .-> M5
    M10 -. extends .-> M9
```

### 4.4.3 IT Personnel

```mermaid
flowchart LR
    I[IT Personnel]
    subgraph iClimate
        I1([View IT dashboard])
        I2([Manage user accounts])
        I3([Reset user password])
        I4([Delete and restore accounts])
        I5([Configure system settings])
        I6([Configure security policy])
        I7([Set roles and permissions])
        I8([Back up and restore database])
        I9([Keep maintenance records])
        I10([Compare and retrain models])
        I11([Validate and activate model])
        I12([View model evaluation])
        I13([Review model feedback])
        I14([Monitor system health])
        I15([View and export system logs])
        I16([Manage rice varieties])
        I17([Retry failed jobs])
        I18([Messages, notifications, Climora])
    end
    I --- I1 & I2 & I3 & I4 & I5 & I6 & I7 & I8 & I9
    I --- I10 & I11 & I12 & I13 & I14 & I15 & I16 & I17 & I18
    I3 -. extends .-> I2
    I4 -. extends .-> I2
    I11 -. includes .-> I10
    I17 -. extends .-> I1
```

---

## 4.5 Flowcharts

### 4.5.1 Sign-in

```mermaid
flowchart TD
    A([Start]) --> B[Enter e-mail and password]
    B --> C{Locked out?}
    C -- Yes --> C1[Show wait message] --> Z([End])
    C -- No --> D{CAPTCHA required?}
    D -- Yes --> E{CAPTCHA correct?}
    E -- No --> E1[Record failure, show error] --> B
    E -- Yes --> G
    D -- No --> G{Credentials valid?}
    G -- No --> H{Deleted account with<br/>correct password?}
    H -- Yes --> H1[Show reason for deletion] --> Z
    H -- No --> H2[Record failure, show error] --> B
    G -- Yes --> J{Status Active?}
    J -- No --> J1[Sign out, show Pending,<br/>Rejected, or Inactive message] --> Z
    J -- Yes --> K[Clear limiter, set last sign-in]
    K --> L{Must change password?}
    L -- Yes --> L1[Change password page] --> M
    L -- No --> M{Role}
    M -- Farmer --> N1[Farmer dashboard]
    M -- MAO Personnel --> N2[MAO dashboard]
    M -- IT Expert --> N3[IT dashboard]
    N1 & N2 & N3 --> Z
```

### 4.5.2 Farmer registration and approval

```mermaid
flowchart TD
    A([Start]) --> B[Visitor completes registration form]
    B --> C{Fields and CAPTCHA valid?}
    C -- No --> B
    C -- Yes --> D[Create user and farmer profile]
    D --> E{Approval required?}
    E -- No --> K[Status Active]
    E -- Yes --> F[Status Pending; notify MAO Personnel]
    F --> G[MAO Personnel opens Farmer Approvals]
    G --> H{Decision}
    H -- Approve --> K
    H -- Reject --> I[Status Rejected with reason]
    I --> J[Notify farmer] --> Z([End])
    K --> L[Notify farmer; e-mail when configured]
    L --> M[Farmer signs in] --> Z
```

### 4.5.3 Rice yield prediction

```mermaid
flowchart TD
    A([Start]) --> B[Receive farm, barangay, area, season, target month]
    B --> C[Read climate history and prepared monthly forecast]
    C --> D{All required inputs present?}
    D -- No --> D1[Return missing inputs;<br/>prediction unavailable] --> Z([End])
    D -- Yes --> E[Build model input]
    E --> F{ML service reachable?}
    F -- Yes --> G[POST features to /predict]
    F -- No --> H[Run model artefact locally]
    G --> I{Valid result?}
    H --> I
    I -- No --> I1[Store null yield and error] --> Z
    I -- Yes --> J[Round to two decimals]
    J --> K[Compare with historical average]
    K --> L[Classify: Favorable, Normal, At Risk]
    L --> M[Store result; broadcast PredictionChanged]
    M --> Z
```

### 4.5.4 Weekly risk rating of a barangay

```mermaid
flowchart TD
    A([Start]) --> B[Read stored daily forecast for the period]
    B --> C{Forecast days available?}
    C -- No --> C1[Level: No data] --> Z([End])
    C -- Yes --> D{Active typhoon advisory?}
    D -- Yes --> S[Level: Severe] --> Y
    D -- No --> E[Rate heavy rain]
    E --> F[Rate strong wind]
    F --> G[Rate heat]
    G --> H{Rainfed barangay?}
    H -- Yes --> I[Rate absence of rain]
    H -- No --> J
    I --> J[Take the worst of the hazard levels]
    J --> K{Any hazard?}
    K -- No --> L[Level: Low]
    K -- Yes --> M[Level: Moderate, High, or Severe;<br/>name the main factor]
    L --> Y[Mark as delayed when forecast is old]
    M --> Y
    Y --> Z
```

### 4.5.5 Advisory life cycle

```mermaid
flowchart TD
    A([Start]) --> B{Origin}
    B -- Rule engine --> C[Draft, requires review]
    B -- MAO Personnel --> C
    B -- PAGASA --> P[Published as official; expires in 6 h]
    B -- Send Alert --> Q{Send now?}
    Q -- Yes --> F
    Q -- No --> R[Scheduled] --> R1{Time reached<br/>and not cancelled?}
    R1 -- Yes --> F
    R1 -- No --> Z([End])
    C --> D{MAO decision}
    D -- Reject --> E[Rejected with reason] --> Z
    D -- Approve --> E2[Approved]
    E2 --> F[Published]
    F --> G[Notify farmers of the target barangay]
    G --> H[Farmers read and give feedback]
    H --> I{Past valid-until,<br/>or archived?}
    I -- Yes --> J[Expired or archived] --> Z
    I -- No --> H
    P --> Z
```

### 4.5.6 Climora answer routing

```mermaid
flowchart TD
    A([Question received]) --> B{Blocked by language filter?}
    B -- Yes --> B1[Return refusal; record event<br/>without the text] --> Z([End])
    B -- No --> C[Detect language; load conversation memory]
    C --> D[Classify intent by rules]
    D --> E{Answerable from<br/>approved knowledge?}
    E -- Yes --> E1[Return knowledge answer] --> S
    E -- No --> F{Weather, risk, advisory,<br/>or yield question?}
    F -- Yes --> G{Prepared data available?}
    G -- Yes --> G1[Answer from stored system data] --> S
    G -- No --> G2[Reply that data are being refreshed] --> S
    F -- No --> H{Groq enabled<br/>and available?}
    H -- Yes --> H1[Ask Groq with grounded context] --> S
    H -- No --> H2[Reply that the question<br/>cannot be answered now] --> S
    S[Store chat with source and response time] --> Z
```

### 4.5.7 Model activation

```mermaid
flowchart TD
    A([Start]) --> B[IT uploads or retrains a model]
    B --> C[Registered as candidate]
    C --> D[IT selects Validate]
    D --> E{File and metadata valid?}
    E -- No --> E1[Status: failed] --> Z([End])
    E -- Yes --> F[Status: validated]
    F --> G[IT selects Activate]
    G --> H{Runtime synchronisation on?}
    H -- No --> K
    H -- Yes --> I[Send switch command with admin token]
    I --> J{Service reports the same model?}
    J -- No --> J1[Activation refused; audit entry] --> Z
    J -- Yes --> K[Mark active; previous model deactivated]
    K --> L[Audit entry] --> Z
```

---

## 4.6 Entity Relationship Diagrams

The schema contains sixty-eight tables. For legibility, the ERD is presented in four subject areas. Attributes are abbreviated to keys and principal fields; the complete column list is in the migrations.

### 4.6.1 Users, farmers, and farms

```mermaid
erDiagram
    users ||--o| farmer_profiles : "has"
    users ||--o{ farms : "owns"
    users ||--o{ farmer_planting_schedules : "plans"
    users ||--o{ mao_user_barangays : "covers"
    users ||--o{ sessions : "opens"
    barangay_profiles ||--o{ mao_user_barangays : "assigned in"
    farmer_profiles ||--o{ farmer_organization_members : "belongs"
    farmer_organizations ||--o{ farmer_organization_members : "includes"
    farmer_organizations ||--o{ farmer_profiles : "primary of"
    farmer_organizations ||--o{ farms : "associated"
    farms ||--o| farm_boundaries : "located by"
    farms ||--o{ farmer_planting_schedules : "scheduled on"
    rice_varieties ||--o{ farmer_planting_schedules : "planted"
    farmer_planting_schedules ||--o{ yield_prediction_feedback : "yields"

    users {
        bigint id PK
        string name
        string email UK
        string password
        string role
        string status
        string barangay
        string preferred_language
        boolean is_mao_head
        boolean must_change_password
        timestamp last_login_at
        bigint approved_by FK
    }
    farmer_profiles {
        bigint id PK
        bigint user_id FK
        string full_name
        string barangay
        decimal farm_area
        string farm_type
        string rsbsa_no
        bigint farmer_organization_id FK
    }
    farms {
        bigint id PK
        bigint user_id FK
        string farm_name
        string barangay
        decimal farm_size_hectares
        string farm_type
        boolean is_primary
        timestamp archived_at
        bigint farmer_organization_id FK
        string association_status
    }
    farm_boundaries {
        bigint id PK
        bigint farm_id FK
        json boundary_coordinates
        decimal calculated_area_hectares
    }
    farmer_planting_schedules {
        bigint id PK
        bigint user_id FK
        bigint farm_id FK
        bigint rice_variety_id FK
        date planting_date
        date scheduled_harvest_date
        string season
        decimal predicted_yield
        decimal actual_yield
        date harvested_on
        string harvest_timing
    }
    farmer_organizations {
        bigint id PK
        string name
        string barangay
        string farm_type
    }
    farmer_organization_members {
        bigint id PK
        bigint farmer_profile_id FK
        bigint farmer_organization_id FK
        date date_joined
    }
    barangay_profiles {
        bigint id PK
        string barangay UK
        decimal farm_area_hectares
        string risk_classification
        boolean flood_prone
        boolean drought_prone
    }
    mao_user_barangays {
        bigint id PK
        bigint user_id FK
        bigint barangay_profile_id FK
        string sitio
    }
    rice_varieties {
        bigint id PK
        string nsic_reg_no
        string variety_name
        int maturity_typical
        int yield_typical
        boolean is_active
    }
    yield_prediction_feedback {
        bigint id PK
        bigint user_id FK
        bigint farm_id FK
        bigint farmer_planting_schedule_id FK
        decimal predicted_yield
        decimal actual_yield
    }
    sessions {
        string id PK
        bigint user_id FK
        int last_activity
    }
```

### 4.6.2 Climate, weather, risk, and prediction

```mermaid
erDiagram
    users ||--o{ yield_prediction_requests : "requests"
    users ||--o{ weather_impact_analyses : "saves"
    users ||--o{ weather_forecast_feedback : "rates"
    users ||--o{ analytics_runs : "starts"
    farms ||--o{ yield_predictions : "estimated for"
    monthly_weather_predictions ||--o{ weather_forecast_feedback : "rated by"
    external_weather_data ||--o{ planting_advisories : "supports"

    climate_records {
        bigint id PK
        date record_date
        decimal rainfall
        decimal temperature
        decimal humidity
        decimal wind_speed
        string season
        string source
        timestamp verified_at
        bigint verified_by FK
    }
    rice_productions {
        bigint id PK
        string barangay
        string season
        year year
        string irrigation_type
        decimal area_hectares
        decimal area_harvested_hectares
        decimal total_production
        decimal yield_per_hectare
        timestamp verified_at
    }
    historical_rice_productions {
        bigint id PK
        string barangay
        string season
        int season_year
        decimal area_planted_hectares
        decimal average_yield_t_per_hectare
        decimal total_production_tons
    }
    external_weather_data {
        bigint id PK
        string source
        string location_name
        date forecast_date
        decimal temperature_max
        decimal rainfall_mm
        decimal wind_speed
        json raw_response
        timestamp fetched_at
    }
    heatmap_areas {
        bigint id PK
        string barangay UK
        string risk_level
        decimal risk_score
        decimal predicted_yield
        json risk_components
        timestamp risk_computed_at
    }
    monthly_weather_predictions {
        bigint id PK
        int prediction_year
        int prediction_month
        decimal rainfall
        decimal tmax
        decimal tmin
        decimal humidity
        string weather_model
        string status
    }
    yield_predictions {
        bigint id PK
        string scope
        string subject_key
        bigint farm_id FK
        string barangay
        decimal predicted_yield
        string season
        string period
        string model_version
        json model_input
    }
    yield_prediction_requests {
        bigint id PK
        bigint user_id FK
        string scope
        string barangay
        string season
        decimal area_hectares
        string status
        decimal predicted_yield
        decimal historical_average
        string impact_level
    }
    municipal_yield_predictions {
        bigint id PK
        int year
        int month
        decimal predicted_yield
        decimal actual_yield
        string split
        string model
    }
    weather_impact_analyses {
        bigint id PK
        bigint created_by FK
        json filters
        json results
        string strongest_variable
        string impact_level
    }
    weather_forecast_feedback {
        bigint id PK
        bigint user_id FK
        bigint monthly_weather_prediction_id FK
        date forecast_date
        string accuracy
    }
    analytics_runs {
        bigint id PK
        uuid uuid
        string type
        string status
        bigint requested_by FK
        json input
        json result
    }
    ml_models {
        bigint id PK
        string purpose
        string name
        string version
        string file_path
        decimal mae
        decimal rmse
        string status
        boolean is_active
    }
    users {
        bigint id PK
    }
    farms {
        bigint id PK
    }
    planting_advisories {
        bigint id PK
    }
```

### 4.6.3 Advisories and communication

```mermaid
erDiagram
    users ||--o{ planting_advisories : "posts"
    users ||--o{ notifications : "receives"
    users ||--o{ advisory_feedback : "gives"
    users ||--o{ typhoon_safety_responses : "answers"
    users ||--o{ feed_posts : "writes"
    users ||--o{ feed_comments : "comments"
    users ||--o{ feed_reactions : "reacts"
    users ||--o{ conversation_messages : "sends"
    users ||--o{ a_i_chats : "asks"
    advisory_rules ||--o{ planting_advisories : "generates"
    planting_advisories ||--o{ advisory_feedback : "receives"
    planting_advisories ||--o{ notifications : "announced by"
    feed_posts ||--o{ feed_media : "contains"
    feed_posts ||--o{ feed_comments : "has"
    feed_posts ||--o{ feed_reactions : "has"
    conversations ||--o{ conversation_messages : "contains"
    users ||--o{ conversations : "participates"

    planting_advisories {
        bigint id PK
        string title
        string advisory_type
        string severity
        string target_barangay
        string status
        string source
        bigint posted_by FK
        bigint approved_by FK
        bigint advisory_rule_id FK
        bigint weather_data_id FK
        timestamp valid_from
        timestamp valid_until
        timestamp published_at
        timestamp scheduled_for
    }
    advisory_rules {
        bigint id PK
        string name
        string advisory_type
        json conditions
        text recommendation
        boolean is_active
    }
    advisory_feedback {
        bigint id PK
        bigint planting_advisory_id FK
        bigint user_id FK
        boolean is_helpful
        text comment
        text mao_response
    }
    notifications {
        bigint id PK
        bigint user_id FK
        string title
        string category
        boolean is_read
        timestamp read_at
        bigint planting_advisory_id FK
    }
    typhoon_safety_responses {
        bigint id PK
        bigint user_id FK
        string event_key
        string status
    }
    feed_posts {
        bigint id PK
        bigint user_id FK
        string title
        string category
        timestamp archived_at
        boolean show_on_calendar
    }
    feed_media {
        bigint id PK
        bigint feed_post_id FK
        string path
    }
    feed_comments {
        bigint id PK
        bigint feed_post_id FK
        bigint user_id FK
    }
    feed_reactions {
        bigint id PK
        bigint feed_post_id FK
        bigint user_id FK
        string type
    }
    conversations {
        bigint id PK
        bigint participant_one_id FK
        bigint participant_two_id FK
        timestamp last_message_at
    }
    conversation_messages {
        bigint id PK
        bigint conversation_id FK
        bigint sender_id FK
        text body
        string attachment_path
        timestamp read_at
    }
    a_i_chats {
        bigint id PK
        bigint user_id FK
        text question
        text answer
        string intent
        string source_type
        int response_time_ms
    }
    users {
        bigint id PK
    }
```

### 4.6.4 Administration, audit, and AI knowledge

```mermaid
erDiagram
    users ||--o{ system_logs : "performs"
    users ||--o{ mao_activity_logs : "performs"
    users ||--o{ mao_activities : "plans"
    users ||--o{ reports : "generates"
    users ||--o{ maintenance_records : "performs"
    users ||--o{ farmer_survey_responses : "answers"
    users ||--o{ model_feedback_decisions : "decides"
    users ||--o{ ai_moderation_events : "triggers"
    ai_knowledge_topics ||--o{ ai_terms : "groups"
    ai_knowledge_topics ||--o{ ai_faqs : "groups"
    ai_terms ||--o{ ai_synonyms : "has"
    planting_advisories ||--o{ farmer_survey_responses : "about"
    yield_prediction_requests ||--o{ farmer_survey_responses : "about"

    system_logs {
        bigint id PK
        bigint user_id FK
        string action
        text details
    }
    mao_activity_logs {
        bigint id PK
        bigint user_id FK
        string action
        string record_type
        bigint record_id
        string barangay
    }
    mao_activities {
        bigint id PK
        bigint user_id FK
        string activity_type
        date starts_on
        date ends_on
    }
    reports {
        bigint id PK
        string report_type
        bigint generated_by FK
        json payload
        string status
        int version
        string series_key
    }
    maintenance_records {
        bigint id PK
        string title
        string category
        string status
        bigint performed_by FK
    }
    backup_verifications {
        bigint id PK
        string backup_filename
        string status
        timestamp verified_at
    }
    scheduled_task_runs {
        bigint id PK
        string task_name
        string status
        timestamp started_at
    }
    weather_fetch_logs {
        bigint id PK
        string provider
        string status
        int duration_ms
    }
    farmer_survey_responses {
        bigint id PK
        bigint user_id FK
        string subject
        int useful
        int accurate
        int helpful
        string model_version
        bigint reviewed_by FK
    }
    model_feedback_decisions {
        bigint id PK
        string decision
        string model_version
        bigint decided_by FK
    }
    ai_knowledge_topics {
        bigint id PK
        string slug
        string title_en
        string title_fil
    }
    ai_terms {
        bigint id PK
        bigint topic_id FK
        string term_en
        string term_fil
    }
    ai_synonyms {
        bigint id PK
        bigint term_id FK
        string synonym
    }
    ai_faqs {
        bigint id PK
        bigint topic_id FK
        string question_en
        string question_fil
    }
    ai_blocked_terms {
        bigint id PK
        string term
        string language
    }
    ai_moderation_events {
        bigint id PK
        bigint user_id FK
        string category
        boolean blocked
    }
    users {
        bigint id PK
    }
    planting_advisories {
        bigint id PK
    }
    yield_prediction_requests {
        bigint id PK
    }
```

**Tables not drawn.** Framework tables (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`); legacy or retired tables (`announcements`, `usability_survey_responses`, `heatmap_area_forecasts`, `field_reports`, `rice_yield_model_comparisons`); and supporting tables (`knowledge_base`, `chatbot_responses`, `ai_blocked_patterns`, `rice_variety_imports`).

**Note on referential integrity.** Several relationships are held by barangay name (a string) and not by a foreign key: `farms.barangay`, `rice_productions.barangay`, `heatmap_areas.barangay`, `planting_advisories.target_barangay`, and `yield_predictions.barangay`. Consistency is maintained in the application by `App\Support\LianBarangays::canonicalName`.
