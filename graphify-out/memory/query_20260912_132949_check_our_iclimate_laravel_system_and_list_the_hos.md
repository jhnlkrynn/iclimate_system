---
type: "query"
date: "2026-09-12T13:29:49.002273+00:00"
question: "Check our iClimate Laravel system and list the hosting specs needed: PHP, Laravel, MySQL/MariaDB, Composer, Node/npm, PHP extensions, storage, cron jobs, queues, SSL, APIs, and Python/ML support if used. Then recommend which Hostinger plan is best: shared, cloud, or VPS. Keep it concise and give minimum and ideal specs."
contributor: "graphify"
outcome: "useful"
source_nodes: ["composer.json", "package.json", "PythonService", "console.php"]
---

# Q: Check our iClimate Laravel system and list the hosting specs needed: PHP, Laravel, MySQL/MariaDB, Composer, Node/npm, PHP extensions, storage, cron jobs, queues, SSL, APIs, and Python/ML support if used. Then recommend which Hostinger plan is best: shared, cloud, or VPS. Keep it concise and give minimum and ideal specs.

## Answer

Expanded from graph vocabulary: [php, laravel, mysql, composer, npm, queue, schedule, python, sklearn, storage, api, vite]. Repository audit found Laravel 12.62 on PHP 8.2+, Vite 7 requiring Node 20.19+ or 22.12+, MySQL configuration, sync queues, three Laravel schedules, public uploads, external weather/PAGASA/GeoRisk/Groq APIs, and a Python 3.11 FastAPI/scikit-learn Docker service. Whole-stack Hostinger deployment therefore needs VPS; KVM 1 is minimum and KVM 2 is ideal. Cloud is viable only if the ML API runs elsewhere; shared hosting is not recommended.

## Outcome

- Signal: useful

## Source Nodes

- composer.json
- package.json
- PythonService
- console.php