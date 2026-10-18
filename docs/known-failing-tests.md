# Known failing tests

37 PHPUnit tests fail on `main` at `d57a05ee` (merge of PR #60, "ui update" for login, farmer, MAO and IT pages).
They were left alone on the `landing-redesign` branch, to be fixed on a separate branch.

**How this list was checked (2026-10-17):** `php artisan test` was run on a clean worktree of `d57a05ee` and on
`landing-redesign`. Both fail exactly these 37 tests, so the landing page work did not cause any of them.
An earlier run of the clean worktree showed 38 failures. The extra one did not happen again, so it is probably a
time-dependent or flaky test.

**Likely cause for almost all of them:** the PR #60 UI update changed page wording, markup hooks (`data-*`
attributes, CSS classes) and sidebars, but the tests that check them were not updated. For each one, decide whether
the test or the view is right before fixing it.

| File | Test | What it expected vs. what the page has now (likely cause) |
|---|---|---|
| `tests/Feature/AdvisoriesListTest.php:85` | `each_item_has_severity_barangay_valid_dates_one_recommendation_and_one_what_to_do` | Expects the item to start "High Prenza…"; the list now says "High Risk Prenza…" (severity wording changed). |
| `tests/Feature/AdvisoriesListTest.php:106` | `the_severity_colours_are_green_yellow_and_red_and_the_most_severe_comes_first` | `adv-item is-low" data-adv-item="1"` not rendered; the advisory list markup was rebuilt. |
| `tests/Feature/AdvisoriesListTest.php:121` | `planting_is_shown_first_and_other_kinds_are_chips_only_when_in_use` | `data-adv-item` hook missing (same markup change). |
| `tests/Feature/AdvisoriesListTest.php:143` | `without_a_planting_advisory_the_page_opens_on_the_kind_that_has_one` | `data-adv-item` hook missing (same markup change). |
| `tests/Feature/AdvisoriesListTest.php:166` | `a_farmer_reads_their_barangay_and_lian_wide_advice_in_their_language` | `data-adv-item` hook missing (same markup change). |
| `tests/Feature/AdvisoriesListTest.php:189` | `staff_see_what_waits_for_approval_and_can_pick_a_barangay` | `data-adv-item` hook missing (same markup change). |
| `tests/Feature/BarangayRiskMapTest.php:806` | `farmer_summary_card_matches_the_heat_maps_this_week_rating` | Expects `<p class="fh-risk-badge is-danger">High</p>` on the farmer home; badge markup or wording changed. |
| `tests/Feature/ChatbotKnowledgeHiddenTest.php:148` | `setting_the_flag_to_true_brings_the_tab_back_for_it_only` | "Chatbot knowledge" no longer appears in the IT sidebar when the flag is on; the IT sidebar was rebuilt. |
| `tests/Feature/DashboardKpiTest.php:178` | `each_role_dashboard_renders_its_kpi_strip` | `data-kpi="total-users"` missing; the KPI strip markup changed in the dashboard redesign. |
| `tests/Feature/DashboardOverviewTest.php:421` | `dashboards_render_charts_sources_and_year_filter_only_for_the_right_roles` | Expects "System Health" on the IT dashboard; it was renamed or moved. |
| `tests/Feature/FarmerHomeTest.php:309` | `risk_is_one_badge_with_the_heat_maps_week_rating` | `fh-risk-badge is-good">Low` badge not found; farmer home badge markup or wording changed. |
| `tests/Feature/FarmerNaicelFeaturesTest.php:291` | `the_calendar_records_planted_and_harvested_on_the_existing_planting_records` | My Farm calendar cell markup (`fmf-cal-num` / `fmf-cal-event is-harvested`) changed. |
| `tests/Feature/FarmerOrganizationChartRelocationTest.php:64` | `mao_labels_farmer_profiles_and_farmer_accounts_separately_with_the_same_counts_as_it` | Counts a label once and finds it twice; the new MAO dashboard repeats the label. |
| `tests/Feature/HeatMapPageTest.php:125` | `the_toggle_is_this_week_and_2_weeks_and_the_legend_is_green_yellow_red` | Legend expected "Farm risk Low Moderate High"; page now says "Weather risk to crops Low Risk Moderate Risk High Risk Severe Risk". |
| `tests/Feature/HeatMapPageTest.php:171` | `a_barangay_view_has_name_level_meaning_and_action` | Expects "Moderate risk"; panel now says "Moderate Risk" (capitalisation and wording). |
| `tests/Feature/HeatMapPageTest.php:192` | `two_weeks_can_rate_a_barangay_higher_than_this_week` | Expects "Low risk"; panel now says "Low Risk". |
| `tests/Feature/HeatMapPageTest.php:209` | `staff_see_the_forecast_numbers_and_farmers_read_plain_words` | Expects "Moderate risk"; panel now says "Moderate Risk". |
| `tests/Feature/HeatMapPageTest.php:226` | `a_farmer_reads_the_panel_in_their_language_and_sees_their_own_barangay` | Expects Filipino "Mataas ang panganib"; panel now says "Mataas na Panganib". |
| `tests/Feature/HeatMapPageTest.php:337` | `the_map_shows_the_forecast_rating_and_never_the_current_risk_row` | Expects "Low risk"; panel now says "Low Risk". |
| `tests/Feature/HeatMapPageTest.php:364` | `the_farmer_home_badge_is_the_heat_maps_this_week_rating` | Farmer home `fh-risk-badge is-danger">High` badge not found (same as `BarangayRiskMapTest`). |
| `tests/Feature/HeatMapTerminologyTest.php:77` | `heat_map_page_names_the_feature_heat_map_and_keeps_risk_labels` | Expects the "Farm risk" label on the Heat Map page; it was replaced by "Weather risk to crops". |
| `tests/Feature/ItDashboardInsightsTest.php:69` | `dashboard_shows_the_new_sections_and_loads_the_three_fonts` | "Background Jobs to Retry" section no longer on the IT dashboard. |
| `tests/Feature/ItDashboardInsightsTest.php:112` | `refresh_endpoint_includes_the_usage_section` | Error: `Undefined array key "usage"`; the IT dashboard refresh JSON no longer returns a `usage` section. |
| `tests/Feature/ItPagesDesignTest.php:46` | `it_pages_share_the_it_dashboard_design_system` | Expects `<h1>System Health</h1>`; the System Health heading changed. |
| `tests/Feature/ItPagesDesignTest.php:107` | `system_health_page_summarises_the_checks` | "Overall Status" summary no longer on System Health. |
| `tests/Feature/ItPhaseEightTest.php:60` | `the_it_dashboard_is_four_sections_and_the_rest_is_on_system_health` | Expects sections `status, users, errors, security`; the IT dashboard now has a single `work` section. |
| `tests/Feature/ItPhaseEightTest.php:80` | `system_health_never_builds_the_overview_itself` | `data-health-section="not-prepared"` hook missing on System Health. |
| `tests/Feature/ItPhaseEightTest.php:93` | `the_it_sidebar_hides_chatbot_knowledge_and_mao_and_farmers_cannot_open_it_pages` | "System health" no longer in the IT sidebar. |
| `tests/Feature/MaoDashboardFiguresTest.php:147` | `the_registered_farmers_chart_has_one_bar_per_barangay_most_first` | Tile expected to start "Registered farmers 6"; it now says "Total farmers 6 All registered farmer accounts…". |
| `tests/Feature/MaoDashboardHomeTest.php:137` | `the_dashboard_is_four_tiles_the_action_list_the_map_preview_and_the_charts` | `data-mao-impact` must not appear, but the new MAO dashboard renders it. |
| `tests/Feature/MaoDashboardHomeTest.php:164` | `the_page_reads_saved_data_only_and_empty_figures_are_left_out_never_zero` | Expects "0 Farmer profiles on file"; tile now says "Total farmers 0…". |
| `tests/Feature/MaoDashboardHomeTest.php:189` | `registered_farmers_counts_farmer_profiles_and_opens_farmer_profiles` | Expects "Registered farmers 2" (farmer profiles); tile now shows "Total farmers 3" (farmer accounts), a change of meaning as well as wording. |
| `tests/Feature/MaoDashboardHomeTest.php:346` | `the_mao_sidebar_is_the_ten_pages_in_order` | MAO sidebar now has an 11th item, "MAO Office". |
| `tests/Feature/PanelRevisionsTest.php:246` | `total_farmers_counts_every_registered_farmer_account_and_is_labelled_when_filtered` | "Total farmers (filtered)" label missing when a filter is applied. |
| `tests/Feature/PanelRevisionsTest.php:300` | `the_header_names_the_signed_in_mao_user_and_their_coverage` | MAO header no longer lists the user's coverage barangays ("Bungahan, Kapito, Malaruhatan, San Diego"). |
| `tests/Feature/PrintListsTest.php:144` | `farmer_profiles_print_with_their_columns` | Farmer profiles print now has an extra "Organizations" column. |
| `tests/Feature/ResponsiveLayoutTest.php:86` | `shared_sidebar_docks_controls_and_leaves_scrolling_to_the_content_column` | Layout no longer contains the dark theme script `setAttribute('data-theme','dark')`. |

Two of these are more than wording and need a product decision, not only a test update:
- `MaoDashboardHomeTest:189`: is the MAO tile meant to count farmer profiles or farmer accounts?
- `ItDashboardInsightsTest:112`: the refresh endpoint dropped its `usage` data.
