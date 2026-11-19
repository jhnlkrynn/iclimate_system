# iClimate Final Owner Checklist

This checklist covers evidence that cannot be confirmed from the local
repository or the authorized read-only database metadata. Do not disable,
delete, replay, retry, or modify anything while checking.

## Supabase Dashboard

1. **Edge Functions**
   - Open the Supabase project, then **Edge Functions**.
   - Record deployed function names, deployment status, last deployment time,
     invocation count/time, and any visible schedule.
   - An active deployment, recent invocation, schedule, or consumer URL is an
     active dependency until its owner confirms otherwise.
   - Record names and timestamps only; do not copy secrets, payloads, or tokens.
   - Decision needed: no required function, or identify the function and its
     local replacement/owner.

2. **Database Webhooks and Integrations**
   - Open **Database → Webhooks** and **Integrations**.
   - Record enabled/disabled state, source table/event, a redacted endpoint
     label, and last delivery status/time.
   - An enabled hook with recent successful delivery or an owned endpoint is an
     active dependency.
   - Do not send a test event, retry delivery, disable a hook, or copy tokens.
   - Decision needed: no required hook, or identify the consumer and migration
     owner.

3. **Authentication and OAuth**
   - Open **Authentication → Providers** and **Authentication → Configuration**.
   - Record enabled provider names, redirect-host names, and whether any
     provider has recent users or sign-ins. Do not record emails, client
     secrets, access tokens, or identity payloads.
   - An enabled provider with active users or an external login flow is an
     active dependency.
   - Decision needed: confirm Laravel authentication replaces it, or identify
     the application still using the provider.

4. **Scheduled cloud work**
   - Open **Database → Cron** (if shown), **Edge Functions**, and any scheduled
     integrations.
   - Record job/function name, enabled state, schedule, last run, and last
     result.
   - A recent enabled job that writes data, calls an external endpoint, or
     invokes a function is an active dependency.
   - Do not run, retry, pause, or delete a job.
   - Decision needed: confirm local Laravel scheduler replaces it, or identify
     the required local equivalent.

5. **Database objects**
   - Open **Database → Publications/Replication, Policies, Roles, Functions,
     Triggers, and Extensions**.
   - Record object names, schemas, counts, and external references only.
   - Default Supabase service functions are not proof of an iClimate dependency;
     an application-schema object or external reference requires review.
   - Decision needed: retain as historical platform metadata or identify a
     required consumer. Do not alter any object.

6. **Legacy deployments and external consumers**
   - Review **Project Settings → General/API**, deployment integrations, and
     any linked hosting account. Then check known legacy websites, mobile apps,
     dashboards, CI/CD projects, cron hosts, webhook receivers, and API clients.
   - Search configuration variable names and hostnames for `SUPABASE_`,
     `supabase.co`, `createClient`, Auth, Storage, and Realtime references.
   - Record application/service name, environment, last observed activity, and
     owner confirmation. Never copy secret values.
   - An external client that still authenticates, reads, writes, subscribes, or
     receives webhooks from Supabase is an active dependency.
   - Decision needed: owner confirms inactive, or names the migration/retirement
     action required.

## Business-owner decisions

Complete `docs/SUPABASE_FINAL_OWNER_REVIEW.md` for:

- 3,227 additional yield predictions.
- 7 additional weather records.
- 6 additional planting advisories.
- User ID 10 security flags.

Notification ID 96 is already recorded as **ACCEPT LOCAL ENGLISH VERSION —
APPROVED** and should not be reopened without new material evidence.
