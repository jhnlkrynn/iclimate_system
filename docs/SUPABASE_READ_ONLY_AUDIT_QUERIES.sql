-- iClimate Supabase read-only audit packet.
-- Project ref: ilwbgawaalodtrwuargp; application schema: iclimate_laravel.
-- SELECT-only; no persistent objects or data writes.

-- Exact counts for every application table (not pg_class estimates).
WITH app_tables AS (
  SELECT table_schema, table_name FROM information_schema.tables
  WHERE table_schema = 'iclimate_laravel' AND table_type = 'BASE TABLE'
)
SELECT table_name,
       COALESCE((xpath('//row/row_count/text()', query_to_xml(
         format('SELECT count(*) AS row_count FROM %I.%I', table_schema, table_name),
         true, false, '')))[1]::text::bigint, 0) AS row_count
FROM app_tables ORDER BY table_name;

SELECT count(*) AS application_table_count FROM information_schema.tables
WHERE table_schema = 'iclimate_laravel' AND table_type = 'BASE TABLE';

SELECT table_name FROM information_schema.tables
WHERE table_schema = 'iclimate_laravel' AND table_type = 'BASE TABLE'
ORDER BY table_name;

-- Primary keys and tables without a primary key.
SELECT tc.table_name,
       string_agg(kcu.column_name, ',' ORDER BY kcu.ordinal_position) AS primary_key_columns
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
  ON kcu.constraint_schema = tc.constraint_schema
 AND kcu.constraint_name = tc.constraint_name
 AND kcu.table_schema = tc.table_schema AND kcu.table_name = tc.table_name
WHERE tc.table_schema = 'iclimate_laravel' AND tc.constraint_type = 'PRIMARY KEY'
GROUP BY tc.table_name ORDER BY tc.table_name;

SELECT t.table_name FROM information_schema.tables t
LEFT JOIN information_schema.table_constraints tc
  ON tc.table_schema = t.table_schema AND tc.table_name = t.table_name
 AND tc.constraint_type = 'PRIMARY KEY'
WHERE t.table_schema = 'iclimate_laravel' AND t.table_type = 'BASE TABLE'
  AND tc.table_name IS NULL ORDER BY t.table_name;

-- Foreign keys and referential actions.
SELECT tc.table_name, kcu.column_name, ccu.table_name AS referenced_table,
       ccu.column_name AS referenced_column, rc.delete_rule, rc.update_rule
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
  ON kcu.constraint_schema = tc.constraint_schema AND kcu.constraint_name = tc.constraint_name
 AND kcu.table_schema = tc.table_schema AND kcu.table_name = tc.table_name
JOIN information_schema.constraint_column_usage ccu
  ON ccu.constraint_schema = tc.constraint_schema AND ccu.constraint_name = tc.constraint_name
JOIN information_schema.referential_constraints rc
  ON rc.constraint_schema = tc.constraint_schema AND rc.constraint_name = tc.constraint_name
WHERE tc.table_schema = 'iclimate_laravel' AND tc.constraint_type = 'FOREIGN KEY'
ORDER BY tc.table_name, kcu.ordinal_position;

-- Yield-prediction evidence; do not classify the 3,227-row difference here.
SELECT count(*) AS row_count, min(id) AS min_id, max(id) AS max_id,
       count(*) FILTER (WHERE id > 3271) AS rows_after_historical_source_max,
       min(generated_at) AS earliest_generated_at, max(generated_at) AS latest_generated_at
FROM iclimate_laravel.yield_predictions;

SELECT scope, basis, period, season, season_start, model_version, count(*) AS row_count,
       min(id) AS min_id, max(id) AS max_id
FROM iclimate_laravel.yield_predictions
GROUP BY scope, basis, period, season, season_start, model_version
ORDER BY season_start, scope, basis, period, model_version;

SELECT id, scope, subject_key, farm_id, user_id, barangay, season_start, season,
       period, model_version, generated_at, created_at, updated_at
FROM iclimate_laravel.yield_predictions WHERE id > 3271 ORDER BY id;

-- Optional managed-service checks. Run separately; permission errors are
-- UNVERIFIED and must not be solved by broadening the audit role.
SELECT count(*) AS auth_users FROM auth.users;
SELECT count(*) AS auth_identities FROM auth.identities;
SELECT count(*) AS auth_sessions FROM auth.sessions;
SELECT count(*) AS auth_mfa_factors FROM auth.mfa_factors;
SELECT provider, count(*) AS identity_count FROM auth.identities GROUP BY provider ORDER BY provider;

SELECT count(*) AS storage_buckets FROM storage.buckets;
SELECT id, name, public, file_size_limit, allowed_mime_types FROM storage.buckets ORDER BY id;
SELECT bucket_id, count(*) AS object_count, min(created_at) AS earliest_created_at,
       max(updated_at) AS latest_updated_at FROM storage.objects
GROUP BY bucket_id ORDER BY bucket_id;

SELECT pubname, puballtables, pubinsert, pubupdate, pubdelete, pubtruncate
FROM pg_publication ORDER BY pubname;
SELECT schemaname, tablename, rowsecurity, forcrowsecurity FROM pg_catalog.pg_tables
WHERE schemaname = 'iclimate_laravel' ORDER BY tablename;
SELECT schemaname, tablename, policyname, permissive, roles, cmd, qual, with_check
FROM pg_policies WHERE schemaname = 'iclimate_laravel' ORDER BY tablename, policyname;
SELECT n.nspname AS function_schema, p.proname AS function_name,
       pg_get_function_identity_arguments(p.oid) AS arguments,
       pg_get_function_result(p.oid) AS return_type, l.lanname AS function_language
FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
JOIN pg_language l ON l.oid = p.prolang
WHERE n.nspname NOT IN ('pg_catalog', 'information_schema')
ORDER BY n.nspname, p.proname, arguments;
SELECT event_object_schema, event_object_table, trigger_name, action_timing,
       event_manipulation, action_statement FROM information_schema.triggers
WHERE event_object_schema IN ('iclimate_laravel', 'auth', 'storage')
ORDER BY event_object_schema, event_object_table, trigger_name;
SELECT schema_name FROM information_schema.schemata
WHERE schema_name IN ('auth', 'storage', 'realtime', 'supabase_functions', 'net') ORDER BY schema_name;
SELECT table_schema, table_name FROM information_schema.tables
WHERE table_schema IN ('auth', 'storage', 'realtime', 'supabase_functions', 'net')
ORDER BY table_schema, table_name;

-- Session safety evidence.
SELECT current_database() AS database_name, current_schema() AS current_schema,
       current_user AS database_role, current_setting('transaction_read_only') AS transaction_read_only,
       (SELECT ssl FROM pg_stat_ssl WHERE pid = pg_backend_pid()) AS ssl_enabled;
