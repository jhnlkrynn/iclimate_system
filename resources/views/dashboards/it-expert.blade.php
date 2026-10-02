<x-app-layout>
    @php
        $glyph = ['ok' => '✓', 'warn' => '▲', 'crit' => '✕', 'unk' => '?'];
        $overall = $it['overall'];
        $tabs = ['overview' => 'Overview', 'security' => 'Security', 'users' => 'Users & Usage', 'resources' => 'Resources & Data', 'activity' => 'Activity Log'];
        // Section cards get a 3px top border in their status colour.
        $worstPipeline = collect($it['pipeline'])->contains('level', 'crit') ? 'crit' : (collect($it['pipeline'])->contains('level', 'warn') ? 'warn' : 'ok');
    @endphp

    {{-- Fonts (Sora, DM Sans, JetBrains Mono) are loaded in the main layout <head>; here they become CSS variables. --}}
    <style>
        .itd {
            --itd-ink: #0d1f18; --itd-ink-2: #26382f; --itd-muted: #55645c;
            --itd-line: #e1e8e3; --itd-line-strong: #c9d6ce; --itd-surface: #f3f6f4; --itd-card: #fff;
            --itd-accent: #1f5a46; --itd-accent-dark: #184a3a;
            --itd-ok: #2f9e6e; --itd-ok-bg: #e3f5ec; --itd-ok-ink: #14503a;
            --itd-warn: #d9a21b; --itd-warn-bg: #fff3cd; --itd-warn-ink: #6b4400;
            --itd-crit: #c8483a; --itd-crit-bg: #fde3df; --itd-crit-ink: #8a2a1c;
            --itd-unk: #8a958f; --itd-unk-bg: #eef1ef; --itd-unk-ink: #3d4a43;
            --itd-gap: 24px;
            /* type roles: display = headings, KPI numbers, tabs, buttons; sans = body; mono = data */
            --itd-display: 'Sora', 'DM Sans', system-ui, sans-serif;
            --itd-sans: 'DM Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --itd-mono-font: 'JetBrains Mono', ui-monospace, 'Cascadia Mono', Consolas, monospace;
            padding: 24px; border-radius: 16px; background: var(--itd-surface); color: var(--itd-ink);
            font-family: var(--itd-sans); font-size: 14px; font-weight: 400; line-height: 1.55;
        }
        .itd, .itd * { font-family: var(--itd-sans); box-sizing: border-box; font-variant-numeric: tabular-nums; }
        .itd .itd-mono, .itd .itd-mono * { font-family: var(--itd-mono-font); font-size: 12.5px; font-weight: 500; }
        .itd h1, .itd h2, .itd h3, .itd .itd-kpi-value, .itd .itd-btn, .itd .itd-tab, .itd .itd-pill { font-family: var(--itd-display); }
        .itd h1, .itd h2, .itd h3 { color: var(--itd-ink); margin: 0; }
        .itd p { margin: 0; color: var(--itd-ink-2); }
        .itd small, .itd .itd-muted { color: var(--itd-muted); font-size: 13px; }
        .itd .d-block { display: block; }
        .itd-num { text-align: right; }

        /* header */
        .itd-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: var(--itd-gap); }
        .itd-header h1 { font-size: 28px; font-weight: 800; letter-spacing: -0.02em; line-height: 1.2; }
        .itd-header p { margin-top: 4px; color: var(--itd-muted); font-size: 13px; }
        .itd-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px 12px; }
        .itd-stamps { display: flex; flex-direction: column; align-items: flex-end; color: var(--itd-muted); }
        .itd-stamps span { font-size: 12.5px; }
        .itd-pill { display: inline-flex; align-items: center; gap: 8px; padding: 5px 14px 5px 6px; border-radius: 999px; font-size: 14px; font-weight: 600; }
        .itd-pill.is-ok { background: var(--itd-ok-bg); color: var(--itd-ok-ink); } .itd-pill.is-warn { background: var(--itd-warn-bg); color: var(--itd-warn-ink); }
        .itd-pill.is-crit { background: var(--itd-crit-bg); color: var(--itd-crit-ink); } .itd-pill.is-unk { background: var(--itd-unk-bg); color: var(--itd-unk-ink); }
        .itd-btn { display: inline-flex; align-items: center; justify-content: center; padding: 7px 14px; border-radius: 9px; border: 1px solid var(--itd-accent); background: var(--itd-accent); color: #fff !important; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; white-space: nowrap; transition: background .12s, box-shadow .12s; }
        .itd-btn:hover { background: var(--itd-accent-dark); box-shadow: 0 2px 8px rgba(31,90,70,.25); }
        .itd-btn.is-quiet { background: #fff; color: var(--itd-accent) !important; } .itd-btn.is-quiet:hover { background: #e8f5ec; }
        .itd-btn.is-small { padding: 4px 11px; font-size: 13px; }
        .itd-btn:disabled { opacity: .6; cursor: progress; }
        .itd-btn:focus-visible, .itd a:focus-visible, .itd select:focus-visible, .itd input:focus-visible, .itd .itd-info:focus-visible, .itd .itd-tab:focus-visible { outline: 3px solid rgba(31,90,70,.4); outline-offset: 2px; }
        .itd-link { font-size: 13px; font-weight: 600; color: var(--itd-accent); }

        /* attention summary */
        .itd-summary { display: flex; gap: 16px; align-items: flex-start; padding: 20px; margin-bottom: var(--itd-gap); border-radius: 14px; border: 1px solid var(--itd-line); border-left: 6px solid var(--itd-ok); background: var(--itd-ok-bg); box-shadow: 0 1px 3px rgba(13,31,24,.06); }
        .itd-summary.is-warn { border-left-color: var(--itd-warn); background: var(--itd-warn-bg); } .itd-summary.is-crit { border-left-color: var(--itd-crit); background: var(--itd-crit-bg); }
        .itd-summary-icon { flex: none; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; color: #fff; font-weight: 700; background: var(--itd-ok); }
        .is-warn > .itd-summary-icon { background: var(--itd-warn); } .is-crit > .itd-summary-icon { background: var(--itd-crit); }
        .itd-summary h2 { font-size: 17px; font-weight: 700; }
        .itd-summary p { margin-top: 2px; font-size: 15px; color: var(--itd-ink) !important; }
        .itd-chips { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
        .itd-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; background: #fff; border: 1px solid var(--itd-line-strong); color: var(--itd-ink); font-size: 13px; font-weight: 600; text-decoration: none; }
        .itd-chip:hover { box-shadow: 0 2px 8px rgba(13,31,24,.15); border-color: var(--itd-accent); }
        .itd-chip.is-crit { border-color: var(--itd-crit); color: var(--itd-crit-ink); } .itd-chip.is-warn { border-color: var(--itd-warn); color: var(--itd-warn-ink); }

        /* layout */
        .itd-row { display: flex; flex-wrap: wrap; gap: var(--itd-gap); margin-bottom: var(--itd-gap); align-items: stretch; }
        .itd-row:last-child { margin-bottom: 0; }
        .itd-cell { display: flex; flex: 1 1 100%; min-width: 0; scroll-margin-top: 16px; }
        .itd-cell > .itd-card, .itd-cell > .itd-kpi-stack, .itd-cell > .itd-summary { flex: 1; min-width: 0; }
        .itd-cell.is-targeted > .itd-card { box-shadow: 0 0 0 3px rgba(31,90,70,.35); }
        /* the content column is narrow beside the sidebar, so two-up cards only on very wide screens */
        @media (min-width: 1750px) { .itd-row.r-50-50 > .itd-cell { flex: 1 1 0; } }
        .itd-row.is-in-panel { margin-bottom: var(--itd-gap); }

        /* cards: 14px radius, soft border, subtle shadow, 20px padding, 3px status top border */
        .itd-card { background: var(--itd-card); border: 1px solid var(--itd-line); border-top: 3px solid var(--itd-line-strong); border-radius: 14px; padding: 20px; box-shadow: 0 1px 3px rgba(13,31,24,.06); }
        .itd-card.is-ok { border-top-color: var(--itd-ok); } .itd-card.is-warn { border-top-color: var(--itd-warn); } .itd-card.is-crit { border-top-color: var(--itd-crit); }
        .itd-card-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 16px; }
        .itd-card-head h2 { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; }
        .itd-sub { margin-top: 2px !important; font-size: 13px !important; font-weight: 400; color: var(--itd-muted) !important; }
        .itd-updated { display: block; margin-top: 4px; color: var(--itd-muted); }
        .itd-updated, .itd-updated * { font-size: 12.5px !important; }
        .itd-foot { margin-top: 12px !important; font-size: 13px !important; color: var(--itd-muted) !important; }
        .itd-empty { padding: 16px; text-align: center; color: var(--itd-muted); background: var(--itd-unk-bg); border-radius: 10px; font-weight: 500; }
        .itd-error { padding: 10px 12px; border-radius: 10px; background: var(--itd-crit-bg); color: var(--itd-crit-ink); font-weight: 600; }
        .itd-warning { padding: 10px 12px; margin-bottom: 12px; border-radius: 10px; background: var(--itd-warn-bg); color: var(--itd-warn-ink) !important; border: 1px solid #ecd48a; }
        .itd-h3 { font-size: 14px; font-weight: 700; letter-spacing: -0.01em; margin: 20px 0 8px; padding-top: 16px; border-top: 1px solid var(--itd-line); }
        .itd-h3.is-first { margin-top: 0; padding-top: 0; border-top: 0; }
        .itd-period { font-family: var(--itd-sans); font-size: 13px; font-weight: 400; color: var(--itd-muted); margin-left: 6px; text-transform: none; letter-spacing: 0; }
        .itd-minor { margin: 12px 0 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--itd-muted) !important; }
        .itd-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .itd-list li { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; overflow-wrap: anywhere; }

        /* KPI rows: 6 across, 3 on tablets, 2 on phones; second row up to 4 */
        .itd-kpi-stack { display: grid; gap: 16px; align-content: start; }
        .itd-kpis { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: 1fr; }
        @media (min-width: 768px) { .itd-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } .itd-kpis.is-four { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .itd-kpis { grid-template-columns: repeat(6, minmax(0, 1fr)); } .itd-kpis.is-four { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .itd-kpi { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; padding: 20px 16px 16px; background: var(--itd-card); border: 1px solid var(--itd-line); border-top: 3px solid var(--itd-unk); border-radius: 14px; box-shadow: 0 1px 3px rgba(13,31,24,.06); min-width: 0; transition: box-shadow .12s, transform .12s; }
        .itd-kpi:hover { box-shadow: 0 6px 18px rgba(13,31,24,.12); transform: translateY(-1px); }
        .itd-kpi.is-ok { border-top-color: var(--itd-ok); } .itd-kpi.is-warn { border-top-color: var(--itd-warn); } .itd-kpi.is-crit { border-top-color: var(--itd-crit); }
        .itd-kpi-link { position: absolute; inset: 0; border-radius: 14px; }
        .itd-kpi-top { position: relative; z-index: 2; display: flex; align-items: center; gap: 6px; }
        .itd-kpi-label { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--itd-muted); }
        .itd-kpi-value { font-size: 32px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.15; overflow-wrap: normal; }
        .itd-kpi-value.is-text { font-size: 24px; }
        .itd-unit { margin-left: 5px; font-family: var(--itd-sans); font-size: 14px; font-weight: 400; letter-spacing: 0; color: var(--itd-muted); }
        .itd-compare { font-size: 13px; color: var(--itd-muted); overflow-wrap: anywhere; }
        .itd-compare.is-good { color: var(--itd-ok-ink); font-weight: 600; } .itd-compare.is-bad { color: var(--itd-crit-ink); font-weight: 600; }

        /* status badges: pill, dot icon, tinted background, same-hue dark text */
        .itd-badge { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px 2px 3px; border-radius: 999px; font-size: 13px; font-weight: 600; white-space: nowrap; font-family: var(--itd-sans); }
        .itd-badge.is-ok { background: var(--itd-ok-bg); color: var(--itd-ok-ink); } .itd-badge.is-warn { background: var(--itd-warn-bg); color: var(--itd-warn-ink); }
        .itd-badge.is-crit { background: var(--itd-crit-bg); color: var(--itd-crit-ink); } .itd-badge.is-unk { background: var(--itd-unk-bg); color: var(--itd-unk-ink); }
        .itd-dot { display: inline-grid; place-items: center; width: 18px; height: 18px; border-radius: 50%; font-size: 10px; line-height: 1; font-weight: 700; color: #fff; background: var(--itd-unk); flex: none; }
        .is-ok > .itd-dot { background: var(--itd-ok); } .is-warn > .itd-dot { background: var(--itd-warn); } .is-crit > .itd-dot { background: var(--itd-crit); }

        /* tooltips */
        .itd-info { position: relative; display: inline-grid; place-items: center; width: 17px; height: 17px; flex: none; border-radius: 50%; border: 1px solid #7d8c84; color: #3d4a43; font-size: 11px; font-weight: 700; cursor: help; text-transform: none; letter-spacing: 0; }
        .itd-info::after { content: attr(data-tip); position: absolute; z-index: 30; left: 0; top: calc(100% + 6px); width: max-content; max-width: min(270px, 70vw); padding: 8px 10px; border-radius: 9px; background: #0d1f18; color: #fff; font-size: 13px; font-weight: 400; line-height: 1.45; text-transform: none; letter-spacing: 0; text-align: left; white-space: normal; display: none; pointer-events: none; }
        /* display:none (not just transparent) so hidden tooltips never widen the page */
        .itd-info:hover::after, .itd-info:focus::after { display: block; }
        /* tooltips of right-hand cards open leftwards so they stay on screen */
        .itd-kpis.is-four .itd-kpi:nth-child(n+3) .itd-info::after { left: auto; right: 0; }
        @media (max-width: 767.98px) { .itd-kpi:nth-child(even) .itd-info::after { left: auto; right: 0; } }
        @media (min-width: 768px) and (max-width: 1279.98px) { .itd-kpis:not(.is-four) .itd-kpi:nth-child(3n) .itd-info::after { left: auto; right: 0; } }
        @media (min-width: 1280px) { .itd-kpis:not(.is-four) .itd-kpi:nth-child(n+5) .itd-info::after { left: auto; right: 0; } }

        /* tabs */
        .itd-tabs { display: flex; gap: 4px; overflow-x: auto; margin: 0 0 var(--itd-gap); padding: 0 0 0; border-bottom: 2px solid var(--itd-line-strong); scrollbar-width: thin; }
        .itd-tab { flex: none; padding: 10px 18px; margin-bottom: -2px; border: 0; border-bottom: 3px solid transparent; background: transparent; color: var(--itd-ink-2); font-size: 14px; font-weight: 600; cursor: pointer; border-radius: 9px 9px 0 0; transition: background .12s, color .12s; }
        .itd-tab:hover { background: #e8f0ea; }
        .itd-tab[aria-selected="true"] { color: var(--itd-accent); border-bottom-color: var(--itd-accent); background: #fff; }
        [data-panel][hidden] { display: none; }

        /* attention list */
        .itd-count { min-width: 26px; padding: 1px 8px; border-radius: 999px; text-align: center; font-size: 13px; font-weight: 700; background: var(--itd-ok-bg); color: var(--itd-ok-ink); }
        .itd-count.has-items { background: var(--itd-crit-bg); color: var(--itd-crit-ink); }
        .itd-allgood { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; background: var(--itd-ok-bg); color: var(--itd-ok-ink) !important; font-weight: 500; }
        .itd-alert-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .itd-alert { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--itd-line); border-left: 5px solid var(--itd-warn); background: #fffdf5; transition: box-shadow .12s; }
        .itd-alert:hover { box-shadow: 0 3px 12px rgba(13,31,24,.1); }
        .itd-alert.is-critical { border-left-color: var(--itd-crit); background: #fff7f5; }
        .itd-sev { display: inline-flex; align-items: center; gap: 5px; font-family: var(--itd-sans); font-size: 13px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: var(--itd-warn-bg); color: var(--itd-warn-ink); white-space: nowrap; }
        .is-critical .itd-sev { background: var(--itd-crit-bg); color: var(--itd-crit-ink); }
        .itd-alert-body strong { display: block; color: var(--itd-ink); font-weight: 600; }
        @media (max-width: 640px) { .itd-alert { grid-template-columns: minmax(0, 1fr); } .itd-alert .itd-btn, .itd-alert .itd-sev { justify-self: start; } }

        /* tables: scroll inside their own container */
        .itd-table-wrap { overflow-x: auto; max-width: 100%; }
        .itd-table { width: 100%; min-width: 480px; border-collapse: collapse; font-size: 14px; }
        .itd-table.is-wide { min-width: 820px; }
        .itd-table td:first-child { overflow-wrap: normal; }
        .itd-table th { padding: 8px 10px; text-align: left; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--itd-muted); border-bottom: 2px solid var(--itd-line); white-space: nowrap; }
        .itd-table th.itd-num { text-align: right; }
        .itd-table td { padding: 10px; border-bottom: 1px solid var(--itd-line); vertical-align: top; color: var(--itd-ink); }
        .itd-table tbody tr { transition: background .1s; } .itd-table tbody tr:hover { background: #f6faf7; }
        .itd-table tr:last-child td { border-bottom: 0; }
        .itd-table tr.itd-total td { border-top: 2px solid var(--itd-line); }
        .itd-table tr.is-flag-failed td { background: #fff7f5; } .itd-table tr.is-flag-privileged td { background: #fffdf5; }
        .itd-nowrap { white-space: nowrap; }

        /* label / value rows */
        .itd-rows { margin: 0; display: grid; }
        .itd-rows > div { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 9px 0; border-bottom: 1px solid var(--itd-line); }
        .itd-rows > div:last-child { border-bottom: 0; }
        .itd-rows dt { color: var(--itd-ink-2); font-weight: 500; } .itd-rows dd { margin: 0; font-weight: 600; text-align: right; display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .itd-meter { display: inline-block; width: 120px; height: 8px; border-radius: 999px; background: var(--itd-unk-bg); overflow: hidden; }
        .itd-meter > span { display: block; height: 100%; background: var(--itd-ok); } .itd-meter.is-warn > span { background: var(--itd-warn); } .itd-meter.is-crit > span { background: var(--itd-crit); }

        /* filters */
        .itd-filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
        .itd-filters label { display: flex; flex-direction: column; gap: 3px; font-size: 12px; font-weight: 600; color: var(--itd-muted); text-transform: uppercase; letter-spacing: .05em; }
        .itd-filters select, .itd-filters input { padding: 5px 8px; border: 1px solid var(--itd-line-strong); border-radius: 8px; background: #fff; color: var(--itd-ink); font-size: 14px; text-transform: none; letter-spacing: 0; }

        /* charts */
        .itd-range { display: inline-flex; border: 1px solid var(--itd-line-strong); border-radius: 9px; overflow: hidden; }
        .itd-range button { padding: 5px 14px; border: 0; background: #fff; color: var(--itd-ink-2); font-size: 13px; font-weight: 600; cursor: pointer; font-family: var(--itd-display); }
        .itd-range button + button { border-left: 1px solid var(--itd-line-strong); } .itd-range button:hover { background: #e8f5ec; }
        .itd-range button[aria-pressed="true"] { background: var(--itd-accent); color: #fff; }
        .itd-chart { position: relative; height: 320px; margin-bottom: 40px; }
        .itd-chart canvas { cursor: pointer; }
        .itd-chart-state { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; padding: 16px; color: var(--itd-muted); font-weight: 500; background: rgba(255,255,255,.88); }
        .itd-chart-state[hidden] { display: none; }
        .itd-spark { width: 100px; height: 24px; color: var(--itd-accent); display: block; } .itd-spark-cell { min-width: 120px; }
        .itd-spark-row { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; } .itd-spark-row .itd-spark { width: 160px; height: 32px; }
        .itd-two { display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--itd-gap); } @media (min-width: 1500px) { .itd-two { grid-template-columns: 3fr 2fr; } }
        .itd-heat { border-collapse: separate; border-spacing: 2px; min-width: 560px; width: 100%; }
        .itd-heat th { font-weight: 500; color: var(--itd-muted); text-align: center; padding: 0; font-size: 11px; } .itd-heat th[scope="row"] { text-align: right; padding-right: 6px; font-size: 12px; }
        .itd-heat td { height: 22px; min-width: 18px; text-align: center; border-radius: 3px; background: var(--itd-unk-bg); color: var(--itd-ink); font-size: 10px; padding: 0; border: 0; }
        .itd-heat td.has { background: color-mix(in srgb, #1f5a46 calc(var(--heat) * 85% + 15%), #ffffff); color: #fff; }
        .itd-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; font-size: 13px; color: var(--itd-muted); }
        .itd-legend-scale { width: 90px; height: 10px; border-radius: 3px; background: linear-gradient(90deg, #dbe9e1, #1f5a46); }
        .itd-axis { margin-left: auto; font-size: 13px; color: var(--itd-muted); }
        .itd-bars { display: flex; align-items: flex-end; gap: 8px; height: 160px; padding-top: 16px; }
        .itd-bar { flex: 1; min-width: 0; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 3px; }
        .itd-bar-fill { width: 100%; background: var(--itd-ok); border-radius: 4px 4px 0 0; } .itd-bar-value { font-size: 12px; font-weight: 600; } .itd-bar-label { font-size: 11px !important; color: var(--itd-muted); }
        .itd-flash { margin-bottom: var(--itd-gap); padding: 10px 14px; border-radius: 10px; background: var(--itd-ok-bg); color: var(--itd-ok-ink) !important; font-weight: 600; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* skeleton while refreshing */
        .itd.is-loading [data-slot] .itd-card, .itd.is-loading [data-slot] .itd-kpi, .itd.is-loading [data-slot] .itd-summary { position: relative; overflow: hidden; }
        .itd.is-loading [data-slot] .itd-card::after, .itd.is-loading [data-slot] .itd-kpi::after, .itd.is-loading [data-slot] .itd-summary::after, .itd-chart-state.is-skeleton::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(243,246,244,.9) 25%, rgba(221,230,224,.9) 50%, rgba(243,246,244,.9) 75%); background-size: 200% 100%; animation: itd-shimmer 1.2s infinite linear; border-radius: inherit; }
        @keyframes itd-shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
        @media (max-width: 575.98px) { .itd { padding: 16px; } .itd-header h1 { font-size: 24px; } .itd-stamps { align-items: flex-start; } .itd-tools { justify-content: flex-start; } .itd-summary { padding: 16px; } .itd-card { padding: 16px; } }
        @media (prefers-reduced-motion: reduce) { .itd * { transition: none !important; animation: none !important; } }
    </style>

    <div class="itd" data-it-dashboard data-live-url="{{ route('admin.dashboard.live') }}" data-activity-url="{{ route('admin.dashboard.activity') }}" data-recent-url="{{ route('admin.dashboard.recent') }}" data-logs-url="{{ route('system-logs.index') }}">
        {{-- Header --}}
        <header class="itd-header">
            <div>
                <h1>IT Dashboard</h1>
                <p>Monitor system health, data pipelines, security, and user activity</p>
            </div>
            <div class="itd-tools">
                <span class="itd-pill is-{{ $overall['level'] }}" data-it-status><span class="itd-dot" aria-hidden="true">{{ $glyph[$overall['level']] }}</span> <span data-it-status-text>{{ $overall['label'] }}</span></span>
                <div class="itd-stamps" role="status" aria-live="polite">
                    <span class="itd-mono">Page refreshed at <span data-it-refreshed>{{ $it['checked_at']->format('g:i:s A') }}</span></span>
                    <span class="itd-mono">Weather data synced at <span data-it-weather>{{ $it['weather_synced_at'] ? $it['weather_synced_at']->format('M j, g:i A') : 'Not available' }}</span></span>
                </div>
                <a class="itd-btn is-quiet is-small" href="{{ route('users.index') }}">Manage Users</a>
                <a class="itd-btn is-quiet is-small" href="{{ route('system-logs.index') }}">View System Logs</a>
                <button type="button" class="itd-btn" data-it-refresh>Refresh</button>
            </div>
        </header>

        @if (session('status'))<p class="itd-flash" role="status">{{ session('status') }}</p>@endif

        {{-- Needs your attention + KPI cards (always visible) --}}
        <div class="itd-row"><div class="itd-cell" id="itd-summary" data-slot="summary">@include('dashboards.it.summary')</div></div>
        <div class="itd-row"><div class="itd-cell" id="itd-kpis" data-slot="kpis">@include('dashboards.it.kpis')</div></div>

        {{-- Tabs --}}
        <div class="itd-tabs" role="tablist" aria-label="Dashboard sections">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" class="itd-tab" id="itd-tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="itd-panel-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" tabindex="{{ $tab === $key ? '0' : '-1' }}">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Overview --}}
        <div role="tabpanel" id="itd-panel-overview" data-panel="overview" aria-labelledby="itd-tab-overview" @if ($tab !== 'overview') hidden @endif>
            <div class="itd-row"><div class="itd-cell" id="itd-alerts" data-slot="alerts">@include('dashboards.it.alerts')</div></div>
            <div class="itd-row r-50-50">
                <div class="itd-cell" id="itd-health" data-slot="health">@include('dashboards.it.health')</div>
                <div class="itd-cell" id="itd-pipeline" data-slot="pipeline">@include('dashboards.it.pipeline')</div>
            </div>
            <div class="itd-row">
                <div class="itd-cell" id="itd-activity">
                    <section class="itd-card" aria-labelledby="itdActivityTitle" data-it-activity>
                        <div class="itd-card-head">
                            <div>
                                <h2 id="itdActivityTitle">System Activity <span class="itd-period" data-activity-range-label>Last 30 days</span></h2>
                                <p class="itd-sub">Logins, admin actions, processing, jobs and errors per day. Click a day to open its logs.</p>
                                <span class="itd-updated itd-mono">Last updated <span data-activity-updated>…</span></span>
                            </div>
                            <div class="itd-range" role="group" aria-label="Activity period">
                                <button type="button" data-range="7" aria-pressed="false">7 days</button>
                                <button type="button" data-range="30" aria-pressed="true">30 days</button>
                                <button type="button" data-range="90" aria-pressed="false">90 days</button>
                            </div>
                        </div>
                        <div class="itd-chart">
                            <canvas id="itSystemActivityTrendChart" data-chart-unit="events" aria-label="System activity per day. Click a day to open its logs."></canvas>
                            <div class="itd-chart-state is-skeleton" data-activity-state role="status">Loading system activity...</div>
                        </div>
                        <p class="itd-foot">Errors are failed logins, failed processing runs and failed jobs. Each line is a different kind of event, so read them by label, not only by colour.</p>
                    </section>
                </div>
            </div>
        </div>

        {{-- Security --}}
        <div role="tabpanel" id="itd-panel-security" data-panel="security" aria-labelledby="itd-tab-security" @if ($tab !== 'security') hidden @endif>
            <div class="itd-row"><div class="itd-cell" id="itd-security" data-slot="security">@include('dashboards.it.security')</div></div>
        </div>

        {{-- Users & Usage --}}
        <div role="tabpanel" id="itd-panel-users" data-panel="users" aria-labelledby="itd-tab-users" @if ($tab !== 'users') hidden @endif>
            <div class="itd-row r-50-50">
                <div class="itd-cell" id="itd-roles" data-slot="roles">@include('dashboards.it.roles')</div>
                <div class="itd-cell" id="itd-ai" data-slot="ai">@include('dashboards.it.ai')</div>
            </div>
            <div class="itd-row"><div class="itd-cell" id="itd-usage" data-slot="usage">@include('dashboards.it.usage')</div></div>
        </div>

        {{-- Resources & Data --}}
        <div role="tabpanel" id="itd-panel-resources" data-panel="resources" aria-labelledby="itd-tab-resources" @if ($tab !== 'resources') hidden @endif>
            <div class="itd-row r-50-50">
                <div class="itd-cell" id="itd-resources" data-slot="resources">@include('dashboards.it.resources')</div>
                <div class="itd-cell" id="itd-integrity" data-slot="integrity">@include('dashboards.it.integrity')</div>
            </div>
            <div class="itd-row"><div class="itd-cell" id="itd-ops" data-slot="ops">@include('dashboards.it.ops')</div></div>
        </div>

        {{-- Activity Log --}}
        <div role="tabpanel" id="itd-panel-activity" data-panel="activity" aria-labelledby="itd-tab-activity" @if ($tab !== 'activity') hidden @endif>
            <div class="itd-row"><div class="itd-cell" id="itd-recent" data-slot="recent">@include('dashboards.it.recent')</div></div>
        </div>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-it-dashboard]');
            if (!root) return;
            const $ = (s) => root.querySelector(s);
            let activity = null; let range = 30; let activityLoaded = false;

            const ready = async () => { try { await window.iClimateVisualsReady; } catch (e) { /* handled by callers */ } return !!window.Chart; };
            const longDate = (iso) => new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            const clock = () => new Date().toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', second: '2-digit', timeZone: 'Asia/Manila' });

            // ---- tabs (the selected tab lives in the URL as ?tab=...)
            const tabs = [...root.querySelectorAll('[role="tab"]')];
            function showTab(name, replaceUrl = true) {
                if (!root.querySelector('[data-panel="' + name + '"]')) name = 'overview';
                tabs.forEach((t) => { const on = t.dataset.tab === name; t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; });
                root.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== name; });
                if (replaceUrl) { const url = new URL(window.location.href); url.searchParams.set('tab', name); history.replaceState(null, '', url); }
                if (name === 'overview') loadActivity();
            }
            tabs.forEach((t, i) => {
                t.addEventListener('click', () => showTab(t.dataset.tab));
                t.addEventListener('keydown', (e) => {
                    const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
                    if (next === undefined) return;
                    e.preventDefault(); const target = tabs[(next + tabs.length) % tabs.length]; target.focus(); showTab(target.dataset.tab);
                });
            });
            function goTo(tab, anchor) {
                showTab(tab);
                const el = anchor ? document.getElementById(anchor) : null;
                if (!el) return;
                el.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
                el.classList.add('is-targeted'); setTimeout(() => el.classList.remove('is-targeted'), 2500);
            }
            // Chips and KPI cards that point at a section open its tab and scroll to it without a reload.
            root.addEventListener('click', (e) => {
                const link = e.target.closest('[data-tab-link]');
                if (!link || e.metaKey || e.ctrlKey) return;
                e.preventDefault(); goTo(link.dataset.tabLink, link.dataset.target);
                history.replaceState(null, '', link.getAttribute('href'));
            });
            // Retry and other confirm-first forms.
            root.addEventListener('submit', (e) => { const msg = e.target.dataset && e.target.dataset.confirm; if (msg && !window.confirm(msg)) e.preventDefault(); });

            // ---- System Activity chart
            async function loadActivity() {
                if (activityLoaded && activity) return;
                const state = $('[data-activity-state]');
                state.hidden = false; state.classList.add('is-skeleton'); state.textContent = 'Loading system activity...';
                $('[data-activity-range-label]').textContent = 'Last ' + range + ' days';
                root.querySelectorAll('.itd-range button').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.range) === range)));
                const message = (text) => { state.classList.remove('is-skeleton'); state.textContent = text; };
                try {
                    const res = await fetch(root.dataset.activityUrl + '?range=' + range, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    $('[data-activity-updated]').textContent = clock();
                    if (data.state === 'error') { message(data.message); return; }
                    if (data.state === 'empty') { if (activity) { activity.destroy(); activity = null; } message('No system activity was recorded in this period. Logins, admin actions and errors will appear here 👍'); return; }
                    if (!(await ready())) { message('The chart library could not be loaded.'); return; }
                    state.hidden = true;
                    const datasets = data.datasets.map((d) => ({ label: d.label, data: d.data, borderColor: d.color, backgroundColor: d.color, borderWidth: 2, tension: .3, pointRadius: data.days <= 30 ? 3 : 1, pointHoverRadius: 6 }));
                    if (activity) { activity.data.labels = data.labels; activity.data.datasets = datasets; activity.$dates = data.dates; activity.update(); activityLoaded = true; return; }
                    activity = new window.Chart($('#itSystemActivityTrendChart'), {
                        type: 'line', data: { labels: data.labels, datasets },
                        options: {
                            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                            onClick: (evt, _els, chart) => {
                                const hit = chart.getElementsAtEventForMode(evt, 'index', { intersect: false }, true)[0];
                                const day = hit && chart.$dates ? chart.$dates[hit.index] : null;
                                if (day) window.location.href = root.dataset.logsUrl + '?date=' + encodeURIComponent(day);
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Events per day' }, grid: { color: '#eef2ef' } },
                                x: { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 }, title: { display: true, text: 'Date' } },
                            },
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                                tooltip: { callbacks: {
                                    title: (items) => activity.$dates ? longDate(activity.$dates[items[0].dataIndex]) : items[0].label,
                                    label: (c) => c.dataset.label + ': ' + c.parsed.y,
                                    footer: () => 'Click to open this day\'s logs',
                                } },
                            },
                        },
                    });
                    activity.$dates = data.dates; activityLoaded = true;
                } catch (e) {
                    message('Unable to retrieve system activity.');
                }
            }
            root.querySelectorAll('.itd-range button').forEach((b) => b.addEventListener('click', () => { range = Number(b.dataset.range); activityLoaded = false; loadActivity(); }));

            // ---- Recent activity filters
            async function loadRecent() {
                const form = $('[data-recent-filters]'); const body = $('[data-recent-body]');
                if (!form || !body) return;
                body.style.opacity = '.5';
                try {
                    const res = await fetch(root.dataset.recentUrl + '?' + new URLSearchParams(new FormData(form)), { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    body.innerHTML = (await res.json()).html;
                } catch (e) {
                    body.innerHTML = '<p class="itd-error" role="alert">Unable to retrieve recent activity.</p>';
                } finally { body.style.opacity = ''; }
            }
            root.addEventListener('change', (e) => { if (e.target.closest('[data-recent-filters]')) loadRecent(); });

            // ---- Refresh
            const glyphs = { ok: '✓', warn: '▲', crit: '✕', unk: '?' };
            async function refresh() {
                const btn = $('[data-it-refresh]');
                btn.disabled = true; btn.textContent = 'Refreshing...'; root.classList.add('is-loading');
                try {
                    const res = await fetch(root.dataset.liveUrl, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('bad status');
                    const data = await res.json();
                    const filtering = [...new FormData($('[data-recent-filters]')).values()].some((v) => v !== '');
                    Object.entries(data.slots).forEach(([key, html]) => {
                        if (key === 'recent' && filtering) return;     // keep the table the person is filtering
                        const el = root.querySelector('[data-slot="' + key + '"]'); if (el) el.innerHTML = html;
                    });
                    $('[data-it-refreshed]').textContent = data.refreshed_at;
                    $('[data-it-weather]').textContent = data.weather_synced_at || 'Not available';
                    $('[data-it-status]').className = 'itd-pill is-' + data.level;
                    $('[data-it-status]').firstElementChild.textContent = glyphs[data.level];
                    $('[data-it-status-text]').textContent = data.status;
                    activityLoaded = false; if (!$('[data-panel="overview"]').hidden) loadActivity();
                } catch (e) {
                    $('[data-it-refreshed]').textContent = 'failed. Showing the last loaded data.';
                } finally {
                    btn.disabled = false; btn.textContent = 'Refresh'; root.classList.remove('is-loading');
                }
            }
            $('[data-it-refresh]').addEventListener('click', refresh);
            // Health changes slowly: refresh every 5 minutes, and only while the tab is visible.
            setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, 300000);

            const start = () => {
                const initial = new URL(window.location.href).searchParams.get('tab') || 'overview';
                showTab(initial, false);
                if (window.location.hash) setTimeout(() => goTo(initial, window.location.hash.slice(1)), 150);
            };
            document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
        })();
    </script>
</x-app-layout>
