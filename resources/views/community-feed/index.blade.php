<x-app-layout>
    <style>
        .feed-wrap { --feed-ink:#0d1f18; --feed-muted:#6b8f71; --feed-line:#e8e0d0; --feed-green:#2d6a4f; --feed-mint:#52b788; --feed-blue:#2f6f8f; --feed-gold:#e8a73d; }
        .feed-hero { position:relative; overflow:hidden; border-radius:32px; padding:1.75rem 1.85rem; color:#fff; background:linear-gradient(145deg,#0d1f18 0%,#1a3a2a 62%,#163324 100%); box-shadow:0 1rem 2.3rem rgba(13,31,24,.16); }
        .feed-hero::before {
            content:"";
            position:absolute; inset:0;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E"),
                radial-gradient(ellipse at 88% -10%, rgba(82,183,136,.16) 0%, transparent 60%);
            pointer-events:none;
        }
        .feed-hero > * { position:relative; z-index:1; }
        .feed-hero h1 { font-family:'DM Serif Display', Georgia, serif; font-weight:400; letter-spacing:-0.01em; color:#fff; }
        .feed-eyebrow { display:inline-flex; align-items:center; gap:8px; font-family:'DM Mono', monospace; font-size:.7rem; font-weight:500; text-transform:uppercase; letter-spacing:.12em; color:#74c69d; margin-bottom:.4rem; }
        .feed-eyebrow::before { content:''; display:block; width:18px; height:1px; background:#74c69d; }
        .feed-chip { display:inline-flex; align-items:center; gap:.5rem; border:1px solid rgba(255,255,255,.18); border-radius:999px; padding:.5rem .85rem; background:rgba(255,255,255,.06); color:rgba(255,255,255,.85); font-family:'DM Mono', monospace; font-size:.76rem; font-weight:500; letter-spacing:.02em; }
        .feed-pulse { width:8px; height:8px; border-radius:999px; background:#74c69d; box-shadow:0 0 0 5px rgba(116,198,157,.2); flex-shrink:0; }
        .feed-grid { display:grid; grid-template-columns:minmax(0,1fr) 330px; gap:1rem; align-items:start; margin-top:1rem; }
        .feed-card { border:1.5px solid #e8e0d0; border-radius:18px; background:#fff; box-shadow:0 .7rem 1.6rem rgba(20,32,51,.07); overflow:hidden; }
        .feed-post-card { overflow:visible; }
        .feed-post-card > .feed-card-body:first-child { border-top-left-radius:18px; border-top-right-radius:18px; background:#fff; }
        .feed-post-card > .feed-card-body:last-child { border-bottom-left-radius:18px; border-bottom-right-radius:18px; background:#fff; }
        .feed-card-body { padding:1rem; }
        .feed-composer { background:linear-gradient(135deg,#f5f0e8,#edf7e7); border-color:rgba(82,183,136,.24); box-shadow:0 1rem 2.2rem rgba(13,31,24,.1); }
        .feed-composer .feed-card-body { background:linear-gradient(145deg,#fff,#f6fbf7); border-radius:16px; margin:.15rem; padding:1.1rem; }
        .feed-composer h2 { display:flex; align-items:center; gap:.55rem; margin-bottom:1rem !important; }
        .feed-composer h2::before { content:""; width:.75rem; height:.75rem; border-radius:999px; background:#52b788; box-shadow:0 0 0 5px rgba(82,183,136,.14); }
        .composer-layout { display:block; }
        .composer-option { cursor:pointer; transition:border-color .16s ease, background .16s ease, transform .16s ease, box-shadow .16s ease; }
        .composer-option:hover { transform:translateY(-1px); border-color:#95d5b2; background:#f7fbf8; box-shadow:0 .55rem 1rem rgba(13,31,24,.06); }
        .composer-option:has(input:checked) { border-color:#52b788 !important; background:#edf8f1 !important; box-shadow:inset 0 0 0 1px rgba(82,183,136,.16); }
        .composer-option .form-check-input { width:1rem; height:1rem; flex:0 0 auto; }
        .composer-layout .row { position:relative; }
        .composer-layout .row::before { content:""; position:absolute; inset:-.45rem; z-index:0; border-radius:18px; background:linear-gradient(135deg, rgba(82,183,136,.08), rgba(232,167,61,.08)); pointer-events:none; }
        .composer-layout .row > * { position:relative; z-index:1; }
        .composer-file-list { display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.55rem; }
        .composer-file-chip { border:1px solid #d4edda; border-radius:999px; background:#f7fbf8; color:#274438; padding:.22rem .55rem; font-size:.74rem; font-weight:800; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .composer-submit-row { display:flex; justify-content:flex-end; padding-top:.25rem; }
        .composer-submit-row .btn { min-width:170px; box-shadow:0 .8rem 1.5rem rgba(13,31,24,.16); }
        .field-box { background:#fff; border:1px solid #e1eadf; border-radius:12px; padding:.7rem .8rem .6rem; height:100%; transition:border-color .16s ease, box-shadow .16s ease, transform .16s ease; }
        .field-box:focus-within { border-color:#52b788 !important; box-shadow:0 0 0 4px rgba(82,183,136,.13), 0 .65rem 1rem rgba(13,31,24,.06); transform:translateY(-1px); }
        .field-box-label { display:block; font-family:'DM Mono', monospace; font-size:.64rem; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:#6b8f71; margin-bottom:.35rem; }
        .field-box .form-control, .field-box .form-select { border:0; padding:0; background:transparent; }
        .field-box textarea.form-control { min-height:128px; resize:vertical; }
        .field-box .form-control:focus, .field-box .form-select:focus { box-shadow:none; }
        .feed-post-head { margin:-1rem -1rem 1rem; padding:1rem; border-top-left-radius:18px; border-top-right-radius:18px; background:#0d1f18; }
        .feed-author { display:flex; gap:.75rem; align-items:center; }
        .avatar { width:42px; height:42px; border-radius:50%; display:grid; place-items:center; background:#f0f7f4; border:1px solid #d8f3dc; color:#2d6a4f; font-family:'DM Mono', monospace; font-weight:700; flex:0 0 auto; }
        .feed-title { color:var(--feed-ink); margin:0; }
        .feed-post-heading { color:var(--feed-ink); font-weight:900; font-size:1.05rem; line-height:1.32; margin:1rem 0 .35rem; }
        .feed-meta { color:var(--feed-muted); font-family:'DM Mono', monospace; font-size:.72rem; letter-spacing:.02em; }
        .feed-badge { display:inline-flex; border-radius:999px; padding:.32rem .62rem; background:#d8f3dc; color:#2d6a4f; font-family:'DM Mono', monospace; font-size:.64rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
        .feed-badge.archived { background:#f1f3f2; color:#6b8f71; border:1px solid #e8e0d0; }
        .post-actions { display:flex; flex-wrap:wrap; gap:.45rem; justify-content:flex-end; align-items:center; }
        .manage-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:flex-start; margin-top:1rem; padding-top:1rem; border-top:1px solid rgba(153,185,160,.4); }
        .manage-panel { flex:0 1 auto; border:1px solid rgba(153,185,160,.55); border-radius:10px; background:#f7fbf8; overflow:hidden; }
        .manage-panel[open] { flex-basis:100%; }
        .manage-panel summary { display:inline-flex; cursor:pointer; list-style:none; margin:.75rem .85rem; border:1.5px solid #2d6a4f; border-radius:999px; background:#fff; color:#2d6a4f; padding:.48rem .85rem; font-family:'DM Mono', monospace; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; transition:transform .18s ease, box-shadow .18s ease; }
        .manage-panel summary:hover { transform:translateY(-1px); box-shadow:0 .5rem 1rem rgba(45,106,79,.18); }
        .manage-panel summary::-webkit-details-marker { display:none; }
        .manage-panel-body { padding:.85rem; border-top:1px solid rgba(153,185,160,.55); }
        .media-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-top:.85rem; }
        .media-grid img, .media-grid video { width:100%; border-radius:8px; border:1px solid rgba(153,185,160,.55); background:#0d1f18; max-height:330px; object-fit:cover; }
        .file-tile { display:flex; align-items:center; gap:.7rem; padding:.85rem; border:1px solid rgba(153,185,160,.55); border-radius:8px; background:#f7fbf8; color:inherit; text-decoration:none; font-weight:800; }
        .reaction-row { display:flex; flex-wrap:wrap; gap:.45rem; padding:.8rem 1rem; border-top:1px solid rgba(153,185,160,.4); border-bottom:1px solid rgba(153,185,160,.4); background:#fbfdfb; }
        .manage-actions { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-top:1rem; padding-top:1rem; border-top:1px solid #edf3ee; }
        .manage-actions > form { margin:0; }
        .manage-panel { flex:0 0 auto; border:0; border-radius:8px; background:transparent; overflow:visible; }
        .manage-panel[open] { flex-basis:100%; }
        .manage-panel summary { display:inline-flex; align-items:center; justify-content:center; min-height:40px; cursor:pointer; list-style:none; margin:0; border:1px solid #1f6f4a; border-radius:8px; background:#fff; color:#1f6f4a; padding:.48rem .75rem; font-weight:900; line-height:1.2; }
        .manage-panel summary:hover, .manage-panel[open] summary { background:#f0f7f4; }
        .manage-panel summary::-webkit-details-marker { display:none; }
        .manage-panel-body { margin-top:.75rem; padding:.85rem; border:1px solid #d4edda; border-radius:8px; background:#f7fbf8; }
        .media-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-top:.85rem; }
        .media-grid img, .media-grid video { width:100%; border-radius:8px; border:1px solid #d4edda; background:#0d1f18; max-height:330px; object-fit:cover; }
        .file-tile { display:flex; align-items:center; gap:.7rem; padding:.85rem; border:1px solid #d4edda; border-radius:8px; background:#f7fbf8; color:inherit; text-decoration:none; font-weight:800; }
        .reaction-row { position:relative; z-index:5; display:flex; flex-wrap:wrap; gap:.55rem; align-items:center; justify-content:space-between; padding:.8rem 1rem; border-top:1px solid #edf3ee; border-bottom:1px solid #edf3ee; background:#fbfdfb; overflow:visible; }
        .reaction-picker { position:relative; z-index:30; display:inline-flex; align-items:center; flex:0 0 auto; }
        .reaction-trigger { border:1px solid #d4edda; border-radius:999px; background:#fff; color:var(--feed-ink); padding:.42rem .8rem; font-size:.86rem; font-weight:900; min-width:86px; }
        .reaction-trigger.active { color:#1f6f4a; background:#f0f7f4; border-color:#95d5b2; }
        .reaction-menu {
            position:absolute;
            left:0;
            top:auto;
            bottom:calc(100% + .5rem);
            display:flex;
            gap:.42rem;
            padding:.42rem .5rem;
            border:1px solid rgba(13,31,24,.35);
            border-radius:999px;
            background:#23272d;
            box-shadow:0 .75rem 1.7rem rgba(13,31,24,.28);
            opacity:0;
            visibility:hidden;
            transform:translateY(.25rem) scale(.96);
            transform-origin:bottom left;
            transition:opacity .15s ease, transform .15s ease, visibility .15s ease;
            z-index:25;
            white-space:nowrap;
        }
        .reaction-picker:hover .reaction-menu,
        .reaction-picker:focus-within .reaction-menu {
            opacity:1;
            visibility:visible;
            transform:translateY(0) scale(1);
        }
        .reaction-row:has(.reaction-picker:hover),
        .reaction-row:has(.reaction-picker:focus-within) {
            padding-top:4.35rem;
        }
        .reaction-picker:hover .reaction-option,
        .reaction-picker:focus-within .reaction-option {
            animation: reactionFloat .52s ease both;
        }
        .reaction-picker:hover .reaction-menu form:nth-child(2) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(2) .reaction-option { animation-delay:.03s; }
        .reaction-picker:hover .reaction-menu form:nth-child(3) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(3) .reaction-option { animation-delay:.06s; }
        .reaction-picker:hover .reaction-menu form:nth-child(4) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(4) .reaction-option { animation-delay:.09s; }
        .reaction-picker:hover .reaction-menu form:nth-child(5) .reaction-option,
        .reaction-picker:focus-within .reaction-menu form:nth-child(5) .reaction-option { animation-delay:.12s; }
        .reaction-menu::after {
            content:"";
            position:absolute;
            left:1.1rem;
            top:auto;
            bottom:-.38rem;
            width:.7rem;
            height:.7rem;
            background:#23272d;
            border-right:1px solid rgba(13,31,24,.35);
            border-bottom:1px solid rgba(13,31,24,.35);
            transform:rotate(45deg);
        }
        .reaction-option { position:relative; z-index:1; width:42px; height:42px; display:grid; place-items:center; border:0; border-radius:999px; background:var(--reaction-bg,#2f80ed); padding:0; color:#fff; font-size:0; font-weight:900; box-shadow:inset 0 -5px 10px rgba(0,0,0,.16), 0 .28rem .65rem rgba(0,0,0,.2); transition:transform .14s ease, filter .14s ease; }
        .reaction-option::before { content:attr(data-icon); font-size:1.45rem; line-height:1; }
        .reaction-option:hover, .reaction-option:focus { transform:translateY(-.48rem) scale(1.16); filter:saturate(1.08) brightness(1.04); }
        .reaction-option.like { --reaction-bg:linear-gradient(145deg,#53a5ff,#145bd8); }
        .reaction-option.love { --reaction-bg:linear-gradient(145deg,#ff5d82,#d81342); }
        .reaction-option.care { --reaction-bg:linear-gradient(145deg,#ffd86a,#f28d24); }
        .reaction-option.wow { --reaction-bg:linear-gradient(145deg,#ffd98a,#ff9f43); }
        .reaction-option.helpful { --reaction-bg:linear-gradient(145deg,#63d7a2,#168a5a); }
        .reaction-option.text-success { outline:3px solid rgba(255,255,255,.72); outline-offset:2px; }
        .reaction-display-icon { display:inline-grid; place-items:center; width:1.35rem; height:1.35rem; border-radius:999px; margin-right:.28rem; background:#f0f7f4; font-size:.95rem; line-height:1; vertical-align:-.18rem; }
        .reaction-counts { display:flex; flex:1 1 220px; flex-wrap:wrap; gap:.35rem; align-items:center; justify-content:flex-end; min-width:0; color:var(--feed-muted); font-size:.8rem; font-weight:800; }
        .reaction-count-pill { display:inline-flex; align-items:center; border:1px solid #d4edda; border-radius:999px; background:#fff; padding:.25rem .55rem .25rem .35rem; }
        @keyframes reactionFloat {
            0% { transform:translateY(.42rem) scale(.72); opacity:0; }
            58% { transform:translateY(-.34rem) scale(1.12); opacity:1; }
            100% { transform:translateY(0) scale(1); opacity:1; }
        }
        .comment { display:flex; gap:.65rem; margin-top:.75rem; }
        .comment-bubble { background:#f0f7f4; border:1px solid rgba(153,185,160,.55); border-radius:8px; padding:.6rem .75rem; flex:1; }
        .side-panel { border:1.5px solid #e8e0d0; border-radius:18px; background:linear-gradient(145deg,#f7fbf8,#edf7e7); padding:1.1rem; position:sticky; top:88px; transition:box-shadow .2s ease, border-color .2s ease; }
        .feed-empty { text-align:center; padding:2.75rem 1.25rem; }
        .feed-empty-icon { width:56px; height:56px; border-radius:50%; background:#f0f7f4; border:1px solid #d8f3dc; color:#2d6a4f; display:grid; place-items:center; margin:0 auto 1rem; }
        .feed-empty strong { display:block; font-family:'DM Serif Display', serif; font-weight:400; font-size:1.15rem; color:var(--feed-ink); margin-bottom:.4rem; }
        .feed-empty p { color:var(--feed-muted); font-size:.88rem; margin:0; }
        .side-panel:hover { box-shadow:0 1rem 2rem rgba(13,31,24,.1); border-color:#b7e4c7; }
        .side-panel h2 { margin-bottom:.5rem; }
        @media (max-width:1199.98px) { .feed-grid { grid-template-columns:1fr; } }
        @media (max-width:767.98px) {
            .feed-hero { padding:1rem; border-radius:18px; }
            .media-grid { grid-template-columns:1fr; }
            .reaction-row { align-items:flex-start; flex-direction:column; }
            .reaction-counts { justify-content:flex-start; width:100%; }
            .reaction-menu { left:0; right:auto; max-width:calc(100vw - 2rem); border-radius:18px; padding:.5rem; }
            .comment { align-items:flex-start; }
            .comment form,
            .comment .input-group { min-width:0; width:100%; }
        }
        @media (max-width:575.98px) {
            .feed-card-body { padding:.85rem; }
            .feed-post-head { margin:-.85rem -.85rem .85rem; padding:.85rem; }
            .post-actions,
            .manage-actions { width:100%; }
            .post-actions .btn,
            .manage-actions .btn,
            .manage-panel,
            .manage-panel summary { width:100%; justify-content:center; }
            .reaction-picker { width:100%; }
            .reaction-trigger { width:100%; }
            .reaction-menu {
                position:absolute;
                left:50%;
                right:auto;
                top:auto;
                bottom:calc(100% + .5rem);
                width:max-content;
                max-width:100%;
                justify-content:center;
                transform:translate(-50%, .25rem) scale(.98);
                transform-origin:center bottom;
                z-index:40;
            }
            .reaction-picker:hover .reaction-menu,
            .reaction-picker:focus-within .reaction-menu {
                transform:translate(-50%, 0) scale(1);
            }
            .reaction-menu::after { left:50%; transform:translateX(-50%) rotate(45deg); }
            .reaction-row:has(.reaction-picker:hover),
            .reaction-row:has(.reaction-picker:focus-within) {
                padding-top:4.7rem;
            }
        }
        @media (max-width:420px) {
            .reaction-option { width:36px; height:36px; }
            .reaction-option::before { font-size:1.2rem; }
            .reaction-menu { gap:.28rem; padding:.42rem; }
        }

        /* -- dark theme overrides -- */
        .feed-wrap { color: rgba(255,255,255,.85) !important; }
        .feed-card { border-color: rgba(255,255,255,.1) !important; background: #0D1F18 !important; }
        .feed-composer {
            background: linear-gradient(135deg,#f5f0e8,#edf7e7) !important;
        }
        .feed-composer .feed-card-body {
            background: linear-gradient(145deg,#fff,#f2f8f3) !important;
            border-radius: 16px;
            margin: .15rem;
            color: #0d1f18 !important;
        }
        .feed-composer h2 { color: #0d1f18 !important; }
        .field-box { background: #fff !important; border-color: #e8e0d0 !important; }
        .field-box-label { color: #0d1f18 !important; }
        .field-box .form-control, .field-box .form-select { color: #0d1f18 !important; background: transparent !important; }
        .field-box .form-select option { color: #0d1f18; }
        .feed-wrap .feed-composer {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(82,183,136,.3) !important;
            background:
                linear-gradient(145deg, rgba(255,255,255,.96), rgba(237,248,241,.96)),
                repeating-linear-gradient(90deg, rgba(82,183,136,.08) 0 1px, transparent 1px 32px) !important;
            box-shadow: 0 1rem 2.4rem rgba(13,31,24,.12);
        }
        .feed-wrap .feed-composer::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(120deg, rgba(82,183,136,.11), transparent 36%),
                radial-gradient(circle at 94% 12%, rgba(232,167,61,.16), transparent 28%);
            pointer-events: none;
        }
        .feed-wrap .feed-composer .feed-card-body {
            position: relative;
            z-index: 1;
            margin: .2rem;
            padding: 1rem;
            border-radius: 16px;
            background: rgba(255,255,255,.78) !important;
            backdrop-filter: blur(8px);
        }
        .composer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: .75rem 0 1rem;
            padding: .9rem 1rem;
            border: 1px solid rgba(13,31,24,.12);
            border-radius: 16px;
            color: #fff;
            background: linear-gradient(135deg, #0d1f18, #18452f 68%, #1f6f4a);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.08), 0 .9rem 1.8rem rgba(13,31,24,.14);
        }
        .composer-head-main { display: flex; align-items: center; gap: .85rem; min-width: 0; }
        .composer-head-dot {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.22);
            background: rgba(255,255,255,.1);
            display: grid;
            place-items: center;
            flex: 0 0 auto;
        }
        .composer-head-dot::before {
            content: "";
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #74c69d;
            box-shadow: 0 0 0 7px rgba(116,198,157,.16);
        }
        .composer-head-title {
            color: #fff;
            font-weight: 900;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .composer-head-sub {
            margin-top: .12rem;
            color: rgba(255,255,255,.7);
            font-size: .88rem;
        }
        .composer-head-tags { display: flex; flex-wrap: wrap; gap: .45rem; justify-content: flex-end; }
        .composer-head-tag {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: .35rem .65rem;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.84);
            font-family: 'DM Mono', monospace;
            font-size: .66rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .composer-surface {
            padding: 1rem;
            border: 1px solid rgba(82,183,136,.18);
            border-radius: 18px;
            background: linear-gradient(145deg, rgba(255,255,255,.95), rgba(246,251,248,.95));
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.72);
        }
        .composer-layout .row::before { display: none; }
        .feed-wrap .field-box {
            border: 1px solid #dbe9dc !important;
            border-radius: 14px;
            background: linear-gradient(180deg, #fff, #fbfdfb) !important;
            box-shadow: 0 .35rem .8rem rgba(13,31,24,.035);
        }
        .feed-wrap .field-box:hover {
            border-color: #95d5b2 !important;
            box-shadow: 0 .65rem 1rem rgba(13,31,24,.06);
        }
        .feed-wrap .field-box:focus-within {
            border-color: #52b788 !important;
            box-shadow: 0 0 0 4px rgba(82,183,136,.12), 0 .7rem 1.25rem rgba(13,31,24,.08);
        }
        .feed-wrap .field-box-label {
            color: #2d6a4f !important;
            letter-spacing: .1em;
        }
        .feed-wrap .field-box textarea.form-control { min-height: 154px; }
        .feed-wrap .composer-option {
            min-height: 74px;
            align-items: center !important;
            border-style: dashed !important;
            background: linear-gradient(135deg, #fff, #f4fbf6) !important;
        }
        .composer-option .text-muted { color: #5f7669 !important; }
        .feed-wrap .composer-option:has(input:checked) {
            border-color: #52b788 !important;
            background: linear-gradient(135deg, #edf8f1, #fff9eb) !important;
        }
        .composer-file-list { margin-top: .75rem; }
        .composer-file-chip {
            border-color: #b7e4c7;
            background: #edf8f1;
            color: #18452f;
            box-shadow: 0 .3rem .7rem rgba(13,31,24,.04);
        }
        .composer-submit-row {
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem !important;
            padding: .8rem .9rem;
            border-radius: 16px;
            background: #0d1f18;
        }
        .composer-submit-row::before {
            content: "Post will appear in the community feed after publishing.";
            color: rgba(255,255,255,.68);
            font-size: .85rem;
            font-weight: 700;
        }
        .composer-submit-row .btn {
            border: 0;
            background: #52b788;
            color: #0d1f18;
            box-shadow: 0 .75rem 1.5rem rgba(82,183,136,.28);
        }
        .composer-submit-row .btn:hover {
            background: #74c69d;
            color: #0d1f18;
        }
        .feed-post-card .reaction-row:has(.reaction-picker:hover),
        .feed-post-card .reaction-row:has(.reaction-picker:focus-within) {
            padding-top: .8rem !important;
        }
        @media (max-width: 767.98px) {
            .composer-head {
                align-items: flex-start;
                flex-direction: column;
                border-radius: 14px;
            }
            .composer-head-title { white-space: normal; }
            .composer-head-tags { justify-content: flex-start; }
            .composer-surface { padding: .8rem; }
            .composer-submit-row {
                align-items: stretch;
                flex-direction: column;
            }
            .composer-submit-row .btn {
                width: 100%;
                min-width: 0;
            }
        }
        @media (max-width: 575.98px) {
            .feed-wrap .feed-composer .feed-card-body { padding: .8rem; }
            .composer-head-main { align-items: flex-start; }
            .composer-head-dot {
                width: 36px;
                height: 36px;
                border-radius: 12px;
            }
            .composer-submit-row::before { font-size: .78rem; }
        }
        .avatar { background: rgba(82,183,136,.16); border-color: rgba(149,213,178,.3); color: #74c69d; }
        .feed-title { color: #fff !important; }
        .feed-body-text { color: rgba(255,255,255,.85) !important; }
        .feed-meta { color: rgba(255,255,255,.5); }
        .feed-badge { background: rgba(82,183,136,.16); color: #74c69d; }
        .feed-badge.archived { background: rgba(255,255,255,.06); color: rgba(255,255,255,.5); border-color: rgba(255,255,255,.15); }
        .manage-actions { border-top-color: rgba(255,255,255,.1); }
        .manage-panel summary { border-color: #74c69d; background: rgba(82,183,136,.12); color: #74c69d; }
        .manage-panel summary:hover, .manage-panel[open] summary { background: rgba(82,183,136,.2); }
        .manage-panel-body { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.03); }
        .file-tile { border-color: rgba(255,255,255,.12); background: rgba(255,255,255,.03); color: rgba(255,255,255,.85); }
        .reaction-row { border-color: #edf3ee; background: #fbfdfb; }
        .reaction-trigger { border-color: rgba(255,255,255,.16); background: rgba(255,255,255,.04); color: rgba(255,255,255,.85); }
        .reaction-trigger.active { color: #74c69d; background: rgba(82,183,136,.16); border-color: rgba(149,213,178,.4); }
        .reaction-count-pill { border-color: rgba(255,255,255,.14); background: rgba(255,255,255,.04); color: rgba(255,255,255,.7); }
        .comment-bubble { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.1); color: rgba(255,255,255,.85); }
        .side-panel { border-color: rgba(255,255,255,.1); background: var(--ic-green-950); position: sticky; }
        .feed-empty-icon { background: rgba(82,183,136,.16); border-color: rgba(149,213,178,.3); color: #74c69d; }
        .feed-empty strong { color: #fff; }
        .feed-empty p { color: rgba(255,255,255,.55); }
        .feed-wrap .text-muted { color: rgba(255,255,255,.5) !important; }
        .feed-wrap .alert-success { background: rgba(82,183,136,.14); border-color: rgba(116,198,157,.35); color: #d8f3dc; }
        .feed-wrap .btn-outline-primary { color: #74c69d; border-color: #74c69d; }
        .feed-wrap .btn-outline-primary:hover { background: #2d6a4f; border-color: #2d6a4f; color: #fff; }
        .feed-wrap .btn-outline-secondary { color: rgba(255,255,255,.8); border-color: rgba(255,255,255,.28); }
        .feed-wrap .btn-outline-secondary:hover { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.6); color: #fff; }

        .feed-post-card > .feed-card-body {
            color: #0d1f18 !important;
        }
        .feed-post-card .feed-post-head {
            color: rgba(255,255,255,.85) !important;
            background: #0d1f18;
        }
        .feed-post-card .feed-title {
            color: #fff !important;
        }
        .feed-post-card .feed-post-heading {
            color: #0d1f18 !important;
        }
        .feed-post-card .feed-body-text {
            color: #243a30 !important;
        }
        .feed-post-card .feed-meta {
            color: rgba(183,228,199,.72) !important;
        }
        .feed-post-card .feed-badge {
            background: rgba(82,183,136,.16);
            color: #74c69d;
        }
        .feed-post-card .feed-badge.archived {
            background: rgba(255,255,255,.06);
            color: rgba(255,255,255,.65);
            border-color: rgba(255,255,255,.15);
        }
        .feed-post-card .reaction-row {
            border-color: #edf3ee !important;
            background: #fbfdfb !important;
        }
        .feed-post-card .reaction-trigger {
            border-color: #d4edda;
            background: #fff;
            color: #0d1f18;
        }
        .feed-post-card .reaction-trigger.active {
            color: #1f6f4a;
            background: #f0f7f4;
            border-color: #95d5b2;
        }
        .feed-post-card .reaction-count-pill {
            border-color: #d4edda;
            background: #fff;
            color: #3d5a48;
        }
        .feed-post-card .comment-bubble {
            background: #f0f7f4;
            border-color: rgba(153,185,160,.55);
            color: #0d1f18;
        }
        .feed-post-card .comment-bubble .fw-bold {
            color: #0d1f18;
        }
        .feed-post-card .feed-card-body .form-control {
            background-color: #fff;
            border-color: #e8e0d0;
            color: #0d1f18;
        }
        .feed-post-card .feed-card-body .form-control::placeholder {
            color: #6c757d;
        }
        .feed-post-card .feed-card-body .text-muted {
            color: #6b8f71 !important;
        }

        /* Community feed final layout pass */
        .feed-wrap .feed-grid {
            grid-template-columns: minmax(0, 1fr) minmax(280px, 330px);
            gap: 1rem;
        }
        .feed-wrap main.d-grid {
            align-content: start;
        }
        .feed-wrap .feed-post-card {
            overflow: visible;
            position: relative;
            background: #fff !important;
            border: 1.5px solid rgba(13,31,24,.34) !important;
            border-radius: 18px;
            box-shadow: 0 .55rem 1.15rem rgba(13,31,24,.06);
        }
        .feed-wrap .feed-post-card:has(.reaction-viewer.show) {
            z-index: 40;
        }
        .feed-wrap .feed-post-card:hover {
            transform: none;
            box-shadow: 0 .75rem 1.55rem rgba(13,31,24,.09);
        }
        .feed-post-card > .feed-card-body {
            background: #fff !important;
            padding: 1rem;
        }
        .feed-post-card > .feed-card-body:first-child,
        .feed-post-card > .feed-card-body:last-child {
            border-radius: 0;
        }
        .feed-post-card .feed-post-head {
            margin: -1rem -1rem 1rem;
            padding: 1rem;
            border-radius: 16px 16px 0 0;
            background: linear-gradient(135deg, #0d1f18, #183c2b) !important;
            color: #fff !important;
            align-items: flex-start;
        }
        .feed-post-card .feed-author {
            min-width: 0;
            align-items: center;
        }
        .feed-post-card .avatar {
            background: rgba(116,198,157,.18);
            border-color: rgba(183,228,199,.42);
            color: #b7e4c7;
        }
        .feed-post-card .feed-title {
            color: #fff !important;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }
        .feed-post-card .feed-meta {
            color: rgba(216,243,220,.72) !important;
            line-height: 1.35;
            margin-top: .18rem;
        }
        .feed-post-card .post-actions {
            flex: 0 0 auto;
            padding-top: .15rem;
        }
        .feed-post-card .feed-badge {
            background: rgba(116,198,157,.17);
            border: 1px solid rgba(183,228,199,.22);
            color: #95d5b2;
        }
        .feed-post-card .feed-post-heading {
            margin: 0 0 .35rem;
            color: #0d1f18 !important;
            font-family: 'Inter', sans-serif;
            font-size: 1.02rem;
            font-weight: 850;
            line-height: 1.3;
        }
        .feed-post-card .feed-body-text {
            color: #1c3027 !important;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }
        .feed-post-card .reaction-row {
            min-height: 0;
            margin: 0;
            padding: .6rem 1rem;
            gap: .5rem;
            justify-content: flex-start;
            border-top: 0 !important;
            border-bottom: 1px solid #e4efe8 !important;
            background: #fff !important;
            position: relative;
            overflow: visible;
            z-index: 12;
        }
        .feed-post-card .reaction-row:has(.reaction-picker:hover),
        .feed-post-card .reaction-row:has(.reaction-picker:focus-within) {
            padding-top: .6rem;
            padding-bottom: .6rem;
        }
        .feed-post-card .reaction-picker {
            flex: 0 0 auto;
        }
        .feed-post-card .reaction-trigger {
            min-height: 38px;
            border: 1px solid #cfe8d7;
            background: #fff;
            color: #0d1f18;
            box-shadow: none;
        }
        .feed-post-card .reaction-trigger.active {
            background: #edf8f1;
            color: #1f6f4a;
            border-color: #95d5b2;
        }
        .feed-post-card .reaction-counts {
            flex: 1 1 auto;
            justify-content: flex-start;
            color: #3d5a48;
        }
        .feed-post-card .reaction-count-pill {
            min-height: 28px;
            background: #f5faf7;
            border-color: #dcefe4;
            color: #274438;
            font-size: .78rem;
        }
        .feed-post-card .manage-actions {
            margin: 0;
            padding: .45rem 1rem;
            border-top: 0;
            border-bottom: 1px solid #e4efe8;
            background: #fff;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .35rem;
            align-items: start;
        }
        .feed-post-card .manage-actions > form,
        .feed-post-card .manage-panel {
            min-width: 0;
            width: 100%;
        }
        .feed-post-card .manage-panel[open] {
            grid-column: 1 / -1;
        }
        .feed-post-card .manage-panel summary,
        .feed-post-card .manage-actions .btn {
            width: 100%;
            min-height: 38px;
            border: 0;
            border-radius: 8px;
            background: #f4faf6;
            color: #274438;
            font-family: 'Inter', sans-serif;
            font-size: .86rem;
            font-weight: 850;
            text-transform: none;
            letter-spacing: 0;
        }
        .feed-post-card .manage-panel summary:hover,
        .feed-post-card .manage-panel[open] summary,
        .feed-post-card .manage-actions .btn:hover {
            background: #eaf6ee;
            color: #1f6f4a;
        }
        .feed-post-card .manage-actions .btn-outline-danger {
            color: #9f2f25;
        }
        .feed-post-card .manage-actions .btn-outline-danger:hover {
            background: #fdebea;
            color: #9f2f25;
        }
        .feed-post-card .manage-panel-body {
            margin-top: .55rem;
            background: #f8fbf9;
        }
        .feed-post-card button.reaction-count-pill {
            cursor: pointer;
        }
        .feed-post-card button.reaction-count-pill:hover {
            background: #eaf6ee;
            border-color: #b7e4c7;
            color: #1f6f4a;
        }
        .reaction-viewer {
            position: absolute;
            z-index: 2080;
            display: none;
            width: min(360px, calc(100vw - 2rem));
            padding: 0;
            background: transparent;
            bottom: calc(100% + .45rem);
            left: var(--reaction-popover-left, 0);
        }
        .reaction-viewer.show {
            display: block;
        }
        .reaction-viewer-card {
            max-height: min(420px, calc(100dvh - 2rem));
            overflow: hidden;
            border: 1px solid #d4edda;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 1rem 2.4rem rgba(13,31,24,.24);
            position: relative;
        }
        .reaction-viewer-card::before {
            content: "";
            position: absolute;
            top: -7px;
            left: var(--reaction-caret-left, 24px);
            width: 14px;
            height: 14px;
            transform: rotate(45deg);
            background: #fff;
            border-left: 1px solid #d4edda;
            border-top: 1px solid #d4edda;
        }
        .reaction-viewer .reaction-viewer-card::before {
            top: auto;
            bottom: -7px;
            border-left: 0;
            border-top: 0;
            border-right: 1px solid #d4edda;
            border-bottom: 1px solid #d4edda;
        }
        .reaction-viewer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1rem;
            border-bottom: 1px solid #e4efe8;
        }
        .reaction-viewer-title {
            color: #0d1f18;
            font-weight: 900;
        }
        .reaction-viewer-close {
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 999px;
            background: #f4faf6;
            color: #274438;
            font-weight: 900;
        }
        .reaction-viewer-body {
            max-height: 340px;
            overflow: auto;
            padding: .75rem 1rem 1rem;
        }
        .reaction-viewer-group {
            display: grid;
            gap: .4rem;
        }
        .reaction-viewer-group[hidden] {
            display: none;
        }
        .reaction-viewer-user {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .55rem .35rem;
            border-bottom: 1px solid #f0f5f2;
            color: #0d1f18;
            font-weight: 800;
        }
        .reaction-viewer-avatar {
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            display: inline-grid;
            place-items: center;
            border-radius: 999px;
            background: #edf8f1;
            border: 1px solid #cfe8d7;
            color: #2d6a4f;
            font-weight: 900;
        }
        .feed-post-card .feed-card-body.pt-0 {
            padding-top: .9rem !important;
        }
        .feed-post-card .comment {
            align-items: flex-start;
            margin-top: .65rem;
        }
        .feed-post-card .comment .avatar {
            background: #edf8f1;
            border-color: #cfe8d7;
            color: #2d6a4f;
        }
        .feed-post-card .comment-bubble {
            background: #f4faf6;
            border-color: #d4edda;
            color: #0d1f18;
            min-width: 0;
        }
        .feed-post-card form.d-flex {
            align-items: stretch;
        }
        .feed-post-card form.d-flex .form-control {
            min-height: 44px;
        }
        .feed-post-card form.d-flex .btn {
            min-width: 104px;
            white-space: normal;
        }
        .feed-wrap .side-panel {
            background: #0d1f18;
            border-color: rgba(255,255,255,.1);
            box-shadow: 0 .7rem 1.4rem rgba(13,31,24,.08);
            position: sticky;
            top: 1rem;
            align-self: start;
            min-height: calc(100vh - 2rem);
            min-height: calc(100dvh - 2rem);
            max-height: calc(100vh - 2rem);
            max-height: calc(100dvh - 2rem);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .toolkit-list {
            display: grid;
            gap: .55rem;
            margin: .75rem 0 .75rem;
        }
        .toolkit-item {
            border: 1px solid rgba(183,228,199,.14);
            border-radius: 8px;
            background: rgba(255,255,255,.04);
            padding: .62rem .7rem;
        }
        .toolkit-item strong {
            display: block;
            color: #fff;
            font-size: .84rem;
            line-height: 1.25;
            margin-bottom: .2rem;
        }
        .toolkit-item span {
            display: block;
            color: rgba(255,255,255,.58);
            font-size: .76rem;
            line-height: 1.38;
        }
        .toolkit-note {
            border-top: 1px solid rgba(183,228,199,.14);
            padding-top: .7rem;
            margin-top: auto;
            color: rgba(255,255,255,.62);
            font-size: .76rem;
            line-height: 1.4;
        }
        @media (max-width: 1199.98px) {
            .feed-wrap .feed-grid {
                grid-template-columns: 1fr;
            }
            .feed-wrap .side-panel {
                position: static;
                min-height: auto;
                max-height: none;
                overflow: visible;
            }
        }
        @media (max-width: 767.98px) {
            .feed-hero {
                border-radius: 20px;
                padding: 1.1rem;
            }
            .feed-post-card .feed-post-head {
                flex-direction: column;
                gap: .75rem;
            }
            .feed-post-card .post-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .feed-post-card .reaction-row {
                min-height: auto;
                align-items: flex-start;
            }
            .feed-post-card .reaction-row:has(.reaction-picker:hover),
            .feed-post-card .reaction-row:has(.reaction-picker:focus-within) {
                padding-top: .8rem;
            }
            .feed-post-card .reaction-counts {
                justify-content: flex-start;
            }
            .feed-post-card .manage-actions {
                grid-template-columns: 1fr;
                padding-inline: .85rem;
            }
            .feed-post-card form.d-flex {
                flex-direction: column;
            }
            .feed-post-card form.d-flex .btn {
                width: 100%;
            }
        }
    </style>

    <div class="feed-wrap">
        <section class="feed-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
                <div>
                    <div class="feed-eyebrow">Community Feed</div>
                    <h1 class="h3 mb-1">MAO updates, programs, activities, and farmer discussions</h1>
                    <p class="mb-0 text-white-50">Farmers can react and comment on official MAO posts with photos, videos, and files.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="feed-chip"><span class="feed-pulse"></span> Live MAO Board</span>
                    <a href="{{ route('messages.index') }}" class="btn btn-light fw-bold">Open Messages</a>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif

        <div class="feed-grid">
            <main class="d-grid gap-3">
                @if($canPost)
                    <section class="feed-card feed-composer">
                        <div class="feed-card-body">
                            <div class="feed-eyebrow" style="color:#0d1f18;">New Post</div>
                            <h2 class="h5 mb-3">Create MAO Post</h2>
                            <div class="composer-head">
                                <div class="composer-head-main">
                                    <span class="composer-head-dot" aria-hidden="true"></span>
                                    <div>
                                        <div class="composer-head-title">Official community update</div>
                                        <div class="composer-head-sub">Post advisories, schedules, programs, and farmer reminders.</div>
                                    </div>
                                </div>
                                <div class="composer-head-tags" aria-hidden="true">
                                    <span class="composer-head-tag">MAO Post</span>
                                    <span class="composer-head-tag">Calendar Ready</span>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('community-feed.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="composer-layout">
                                    <div class="composer-surface">
                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerTitle">Post Title</label>
                                                    <input id="composerTitle" name="title" class="form-control" placeholder="Post title" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerCategory">Category</label>
                                                    <select id="composerCategory" name="category" class="form-select" required>
                                                        @foreach(['Update','Program','Activity','Training','Advisory','Announcement'] as $category)
                                                            <option value="{{ $category }}">{{ $category }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerBody">Post Details</label>
                                                    <textarea id="composerBody" name="body" class="form-control" rows="5" placeholder="Share details, schedules, instructions, or reminders..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerEventDate">Event Date</label>
                                                    <input id="composerEventDate" name="event_date" type="datetime-local" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerVisibility">Visibility</label>
                                                    <select id="composerVisibility" name="visibility" class="form-select" required>
                                                        <option value="All Farmers">All Farmers</option>
                                                        <option value="All Users">All Users</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="field-box">
                                                    <label class="field-box-label" for="composerAttachments">Attachments</label>
                                                    <input id="composerAttachments" name="attachments[]" type="file" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="field-box composer-option d-flex gap-3 align-items-start" for="composerShowOnCalendar">
                                                    <input id="composerShowOnCalendar" name="show_on_calendar" value="1" type="checkbox" class="form-check-input mt-1">
                                                    <span>
                                                        <span class="field-box-label d-block mb-1">Important event</span>
                                                        <span class="small text-muted">Show this post on the Calendar using the event date.</span>
                                                    </span>
                                                </label>
                                                <div id="composerFileList" class="composer-file-list"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3 composer-submit-row"><button class="btn btn-primary fw-bold" type="submit">Publish Post</button></div>
                                    </div>
                                    </div>
                            </form>
                        </div>
                    </section>
                @endif

                @forelse($posts as $post)
                    @php
                        $userReaction = $post->reactions->firstWhere('user_id', auth()->id());
                        $canManagePost = $canPost;
                        $isArchived = $post->archived_at !== null;
                        $visibleReactions = $canManagePost
                            ? $post->reactions->reject(fn ($reaction) => $reaction->user_id === $post->user_id)
                            : $post->reactions;
                        $reactionGroups = $visibleReactions->groupBy('type');
                        $counts = $reactionGroups->map->count()->filter();
                    @endphp
                    <article id="post-{{ $post->id }}" class="feed-card feed-post-card">
                        <div class="feed-card-body">
                            <div class="feed-post-head d-flex justify-content-between gap-3">
                                <div class="feed-author">
                                    <div class="avatar">{{ str($post->author?->name ?? 'M')->substr(0, 1)->upper() }}</div>
                                    <div>
                                        <h2 class="feed-title h5">{{ $post->author?->name ?? 'MAO' }}</h2>
                                        <div class="feed-meta">{{ $post->created_at?->diffForHumans() }} @if($post->event_date) | Event: {{ $post->event_date->format('M d, Y h:i A') }} @endif @if($post->show_on_calendar) | Calendar @endif</div>
                                    </div>
                                </div>
                                <div class="post-actions">
                                    @if($isArchived)
                                        <span class="feed-badge archived">Archived</span>
                                    @endif
                                    <span class="feed-badge">{{ $post->category }}</span>
                                    @if($post->show_on_calendar)
                                        <span class="feed-badge">Calendar</span>
                                    @endif
                                </div>
                            </div>
                            <div class="feed-post-heading">{{ $post->title }}</div>
                            <p class="feed-body-text mt-1 mb-0" style="white-space:pre-line;">{{ $post->body }}</p>

                            @if($post->media->isNotEmpty())
                                <div class="media-grid">
                                    @foreach($post->media as $media)
                                        @if($media->media_type === 'image')
                                            <img src="{{ asset('storage/'.$media->path) }}" alt="{{ $media->original_name }}">
                                        @elseif($media->media_type === 'video')
                                            <video controls src="{{ asset('storage/'.$media->path) }}"></video>
                                        @else
                                            <a class="file-tile" href="{{ asset('storage/'.$media->path) }}" target="_blank">File: {{ $media->original_name }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                        </div>

                        @unless($isArchived)
                            @php
                                $reactionIcons = [
                                    'Like' => '👍',
                                    'Love' => '❤',
                                    'Care' => '🥰',
                                    'Wow' => '😮',
                                    'Helpful' => '💡',
                                ];
                            @endphp
                            <div class="reaction-row">
                                @unless($canManagePost)
                                <div class="reaction-picker">
                                    <button class="reaction-trigger {{ $userReaction ? 'active' : '' }}" type="button" aria-haspopup="true" aria-expanded="false">
                                        @if($userReaction)
                                            <span class="reaction-display-icon">{{ $reactionIcons[$userReaction->type] ?? '👍' }}</span>{{ $userReaction->type }}
                                        @else
                                            React
                                        @endif
                                    </button>
                                    <div class="reaction-menu" role="menu" aria-label="Choose a reaction">
                                        @foreach($reactionTypes as $reaction)
                                            <form method="POST" action="{{ route('community-feed.reactions.store', $post) }}">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $reaction }}">
                                                @php
                                                    $reactionClass = strtolower($reaction);
                                                    $reactionIcon = $reactionIcons[$reaction] ?? '👍';
                                                @endphp
                                                <button type="submit" class="reaction-option {{ $reactionClass }} {{ $userReaction?->type === $reaction ? 'text-success' : '' }}" data-icon="{{ $reactionIcon }}" title="{{ $reaction }}" aria-label="{{ $reaction }}" role="menuitem">{{ $reaction }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                                @endunless
                                @if($counts->isNotEmpty())
                                    <div class="reaction-counts">
                                        @foreach($counts as $type => $count)
                                            <button class="reaction-count-pill" type="button" data-reaction-viewer-open="reactionViewer{{ $post->id }}" data-reaction-type="{{ $type }}">
                                                <span class="reaction-display-icon">{{ $reactionIcons[$type] ?? '👍' }}</span>{{ $type }} {{ $count }}
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif($canManagePost)
                                    <div class="reaction-counts">
                                        <span class="reaction-count-pill">No reactions yet</span>
                                    </div>
                                @endif
                            </div>
                        @endunless

                        @if($counts->isNotEmpty())
                            <div id="reactionViewer{{ $post->id }}" class="reaction-viewer" role="dialog" aria-modal="true" aria-labelledby="reactionViewerTitle{{ $post->id }}">
                                <div class="reaction-viewer-card">
                                    <div class="reaction-viewer-head">
                                        <div id="reactionViewerTitle{{ $post->id }}" class="reaction-viewer-title">Reactions</div>
                                        <button class="reaction-viewer-close" type="button" aria-label="Close reactions" data-reaction-viewer-close>&times;</button>
                                    </div>
                                    <div class="reaction-viewer-body">
                                        @foreach($reactionTypes as $reaction)
                                            @php($users = $reactionGroups->get($reaction, collect()))
                                            <div class="reaction-viewer-group" data-reaction-group="{{ $reaction }}" @if($users->isEmpty()) hidden @endif>
                                                @foreach($users as $reactionRecord)
                                                    @php($name = $reactionRecord->user?->name ?? 'User')
                                                    <div class="reaction-viewer-user">
                                                        <span class="reaction-viewer-avatar">{{ str($name)->substr(0, 1)->upper() }}</span>
                                                        <span>{{ $name }}</span>
                                                        <span class="ms-auto reaction-display-icon">{{ $reactionIcons[$reaction] ?? '👍' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($canManagePost)
                            <div class="manage-actions">
                                <details class="manage-panel">
                                    <summary>Edit</summary>
                                    <div class="manage-panel-body">
                                        <form method="POST" action="{{ route('community-feed.update', $post) }}" enctype="multipart/form-data">
                                            @csrf
                                            @method('PATCH')
                                            <div class="row g-3">
                                                <div class="col-md-8">
                                                    <label class="form-label fw-semibold">Title</label>
                                                    <input name="title" class="form-control" value="{{ old('title', $post->title) }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Category</label>
                                                    <select name="category" class="form-select" required>
                                                        @foreach(['Update','Program','Activity','Training','Advisory','Announcement'] as $category)
                                                            <option value="{{ $category }}" @selected(old('category', $post->category) === $category)>{{ $category }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label fw-semibold">Post Details</label>
                                                    <textarea name="body" class="form-control" rows="4" required>{{ old('body', $post->body) }}</textarea>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Event Date</label>
                                                    <input name="event_date" type="datetime-local" class="form-control" value="{{ old('event_date', $post->event_date?->format('Y-m-d\TH:i')) }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Visibility</label>
                                                    <select name="visibility" class="form-select" required>
                                                        <option value="All Farmers" @selected(old('visibility', $post->visibility) === 'All Farmers')>All Farmers</option>
                                                        <option value="All Users" @selected(old('visibility', $post->visibility) === 'All Users')>All Users</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Add Attachments</label>
                                                    <input name="attachments[]" type="file" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-check d-flex gap-2 align-items-start">
                                                        <input name="show_on_calendar" value="1" type="checkbox" class="form-check-input mt-1" @checked(old('show_on_calendar', $post->show_on_calendar))>
                                                        <span>
                                                            <span class="fw-semibold d-block">Important event</span>
                                                            <span class="small text-muted">Show this post on the Calendar using the event date.</span>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <button class="btn btn-primary fw-bold" type="submit">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                                @if(! $isArchived)
                                    <form method="POST" action="{{ route('community-feed.archive', $post) }}" onsubmit="return confirm('Archive this post? Farmers will no longer see it.');">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-secondary fw-bold" type="submit">Archive</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('community-feed.destroy', $post) }}" onsubmit="return confirm('Delete this post permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger fw-bold" type="submit">Delete</button>
                                </form>
                            </div>
                        @endif

                        <div class="feed-card-body pt-0">
                            @foreach($post->comments as $comment)
                                <div class="comment">
                                    <div class="avatar" style="width:34px;height:34px;font-size:.8rem;">{{ str($comment->user?->name ?? 'U')->substr(0, 1)->upper() }}</div>
                                    <div class="comment-bubble">
                                        <div class="fw-bold">{{ $comment->user?->name ?? 'User' }} <span class="feed-meta fw-normal">{{ $comment->created_at?->diffForHumans() }}</span></div>
                                        <div>{{ $comment->body }}</div>
                                    </div>
                                </div>
                            @endforeach
                            @unless($isArchived)
                                <form method="POST" action="{{ route('community-feed.comments.store', $post) }}" class="d-flex gap-2 mt-3">
                                    @csrf
                                    <input name="body" class="form-control" placeholder="Write a comment..." required>
                                    <button class="btn btn-outline-primary fw-bold" type="submit">Comment</button>
                                </form>
                            @else
                                <div class="text-muted small mt-3">This post is archived. Reactions and comments are closed.</div>
                            @endunless
                        </div>
                    </article>
                @empty
                    <div class="feed-card">
                        <div class="feed-card-body">
                            <div class="feed-empty">
                                <div class="feed-empty-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                </div>
                                <strong>No community posts yet.</strong>
                                <p>Once the MAO shares an update, it will appear here for farmers to view and discuss.</p>
                            </div>
                        </div>
                    </div>
                @endforelse

                {{ $posts->links() }}
            </main>

            <aside class="side-panel">
                <div class="feed-eyebrow" style="color:#74c69d;">Farmer Toolkit</div>
                <h2 class="h5 mb-2">What Farmers Can Do</h2>
                <p class="text-muted mb-3">Read MAO updates, react to useful posts, ask questions in comments, and message MAO directly for private concerns.</p>
                <div class="toolkit-list">
                    <div class="toolkit-item">
                        <strong>Check Official Updates</strong>
                        <span>Review posts for schedules, program requirements, advisory changes, and activity reminders from MAO personnel.</span>
                    </div>
                    <div class="toolkit-item">
                        <strong>Use Reactions for Quick Feedback</strong>
                        <span>Mark posts as helpful, liked, or important so the MAO can see which updates farmers are using most.</span>
                    </div>
                    <div class="toolkit-item">
                        <strong>Ask Public Questions</strong>
                        <span>Comment when your question can help other farmers, such as clarification about dates, locations, or instructions.</span>
                    </div>
                    <div class="toolkit-item">
                        <strong>Message Private Concerns</strong>
                        <span>Use messages for farm-specific issues, personal records, crop damage details, or concerns that need direct assistance.</span>
                    </div>
                </div>
                <div class="toolkit-note mb-3">
                    Before joining an activity, confirm the event date, prepare any listed documents, and follow the latest advisory if weather conditions change.
                    For urgent crop or weather concerns, send a direct message with your barangay, crop stage, and photos when available.
                </div>
                <div class="d-grid gap-2">
                    <a class="btn btn-primary fw-bold" href="{{ route('messages.index') }}">Start Conversation</a>
                    <a class="btn btn-outline-primary fw-bold" href="{{ route('planting-advisories.index') }}">Planting Advisories</a>
                </div>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const composerAttachments = document.getElementById('composerAttachments');
            const composerFileList = document.getElementById('composerFileList');

            composerAttachments?.addEventListener('change', () => {
                if (!composerFileList) return;
                composerFileList.innerHTML = '';
                Array.from(composerAttachments.files || []).slice(0, 6).forEach((file) => {
                    const chip = document.createElement('span');
                    chip.className = 'composer-file-chip';
                    chip.textContent = file.name;
                    composerFileList.appendChild(chip);
                });
            });
            const closeViewer = (viewer) => {
                viewer?.classList.remove('show');
            };

            const closeAllViewers = (except = null) => {
                document.querySelectorAll('.reaction-viewer.show').forEach((viewer) => {
                    if (viewer !== except) closeViewer(viewer);
                });
            };

            const positionViewer = (viewer, button) => {
                const row = button.closest('.reaction-row');
                if (!row) return;

                row.appendChild(viewer);

                const rowRect = row.getBoundingClientRect();
                const buttonRect = button.getBoundingClientRect();
                const width = Math.min(360, rowRect.width - 32);
                let left = (buttonRect.left - rowRect.left) + (buttonRect.width / 2) - (width / 2);

                if (left + width > rowRect.width - 16) {
                    left = rowRect.width - width - 16;
                }

                if (left < 16) {
                    left = 16;
                }

                viewer.style.width = `${width}px`;
                viewer.style.setProperty('--reaction-popover-left', `${left}px`);
                viewer.querySelector('.reaction-viewer-card')?.style.setProperty('--reaction-caret-left', `${Math.max(18, buttonRect.left - rowRect.left - left + (buttonRect.width / 2) - 7)}px`);
            };

            document.querySelectorAll('[data-reaction-viewer-open]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const viewer = document.getElementById(button.dataset.reactionViewerOpen);
                    if (!viewer) return;

                    const alreadyOpen = viewer.classList.contains('show');
                    closeAllViewers(viewer);
                    if (alreadyOpen && viewer.dataset.activeReactionType === button.dataset.reactionType) {
                        closeViewer(viewer);
                        return;
                    }

                    const selectedType = button.dataset.reactionType;
                    viewer.dataset.activeReactionType = selectedType;
                    viewer.querySelectorAll('[data-reaction-group]').forEach((group) => {
                        group.hidden = group.dataset.reactionGroup !== selectedType;
                    });

                    const title = viewer.querySelector('.reaction-viewer-title');
                    if (title) title.textContent = `${selectedType} reactions`;

                    viewer.classList.add('show');
                    positionViewer(viewer, button);
                });
            });

            document.querySelectorAll('[data-reaction-viewer-close]').forEach((button) => {
                button.addEventListener('click', () => closeViewer(button.closest('.reaction-viewer')));
            });

            document.querySelectorAll('.reaction-viewer').forEach((viewer) => {
                viewer.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
            });

            document.addEventListener('click', () => closeAllViewers());
            window.addEventListener('resize', () => closeAllViewers());

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') return;
                closeAllViewers();
            });
        });
    </script>
</x-app-layout>
