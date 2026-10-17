:root {
    --bg: #f6f7f9; --panel: #ffffff; --text: #1d2330; --muted: #6b7385; --border: #e3e6ec;
    --accent: #4f46e5; --accent-soft: #eef0ff;
    --ok: #15803d; --ok-soft: #e7f6ec; --warn: #b45309; --warn-soft: #fdf3e2;
    --bad: #c62828; --bad-soft: #fdecec; --info: #0369a1; --info-soft: #e6f3fb;
    --code: #f1f3f6;
}
:root.dark {
        --bg: #0f1218; --panel: #171b23; --text: #e6e9ef; --muted: #8a93a6; --border: #262c38;
        --accent: #8b85ff; --accent-soft: #23234a;
        --ok: #4ade80; --ok-soft: #12301f; --warn: #fbbf24; --warn-soft: #33270e;
        --bad: #f87171; --bad-soft: #3a1616; --info: #38bdf8; --info-soft: #0f2a3a;
        --code: #11151c;
    }
/* Without JavaScript, follow the system theme. */
@media (prefers-color-scheme: dark) {
    :root:not(.light) {
        --bg: #0f1218; --panel: #171b23; --text: #e6e9ef; --muted: #8a93a6; --border: #262c38;
        --accent: #8b85ff; --accent-soft: #23234a;
        --ok: #4ade80; --ok-soft: #12301f; --warn: #fbbf24; --warn-soft: #33270e;
        --bad: #f87171; --bad-soft: #3a1616; --info: #38bdf8; --info-soft: #0f2a3a;
        --code: #11151c;
    }
}
* { box-sizing: border-box; }
html { color-scheme: light; }
html.dark { color-scheme: dark; }
body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }
code, pre, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; }
.app { display: grid; grid-template-columns: 220px 1fr; min-height: 100vh; }
.sidebar { background: var(--panel); border-right: 1px solid var(--border); padding: 20px 14px; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; }
.brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 17px; color: var(--text); padding: 0 8px 18px; }
.brand:hover { text-decoration: none; }
.brand svg { color: var(--accent); }
nav a { display: block; padding: 7px 10px; border-radius: 7px; color: var(--text); }
nav a:hover { background: var(--bg); text-decoration: none; }
nav a.active { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
.nav-title { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); padding: 16px 10px 4px; }
.sidebar-foot { margin-top: auto; padding: 0 8px; color: var(--muted); font-size: 13px; display: grid; gap: 12px; }
.setting { display: grid; gap: 4px; margin: 0; }
.setting-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; }
.setting select { width: 100%; }
.segmented { display: flex; border: 1px solid var(--border); border-radius: 7px; overflow: hidden; }
.segmented button { flex: 1; background: var(--panel); color: var(--muted); border: 0; padding: 5px 4px; font: inherit; font-size: 12px; cursor: pointer; }
.segmented button + button { border-left: 1px solid var(--border); }
.segmented button[aria-pressed="true"] { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
.toggle { display: flex; gap: 6px; align-items: center; cursor: pointer; }
.main { padding: 28px 32px; min-width: 0; }
h1 { font-size: 20px; margin: 0 0 18px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
h2 { font-size: 15px; margin: 0; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
.card { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; }
.card .label { color: var(--muted); font-size: 12px; }
.card .value { font-size: 22px; font-weight: 650; margin-top: 4px; font-variant-numeric: tabular-nums; }
.card .sub { color: var(--muted); font-size: 12px; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.panel { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 20px; overflow: hidden; }
.panel-head { padding: 12px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.panel-body { padding: 16px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 9px 16px; border-bottom: 1px solid var(--border); white-space: nowrap; vertical-align: middle; }
th { font-size: 12px; font-weight: 600; color: var(--muted); background: var(--bg); }
tr:last-child td { border-bottom: 0; }
td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
td.wrap { white-space: normal; }
.empty { padding: 28px 16px; text-align: center; color: var(--muted); }
.muted { color: var(--muted); }
.badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; background: var(--bg); color: var(--muted); border: 1px solid var(--border); }
.badge.pending { background: var(--info-soft); color: var(--info); border-color: transparent; }
.badge.processing, .badge.paused, .badge.working { background: var(--warn-soft); color: var(--warn); border-color: transparent; }
.badge.completed, .badge.running, .badge.finished { background: var(--ok-soft); color: var(--ok); border-color: transparent; }
.badge.failed, .badge.inactive, .badge.cancelled { background: var(--bad-soft); color: var(--bad); border-color: transparent; }
.tag { display: inline-block; padding: 0 6px; margin: 1px 2px 1px 0; border-radius: 4px; background: var(--code); font-size: 11.5px; color: var(--muted); }
.tabs { display: flex; gap: 4px; flex-wrap: wrap; }
.tabs a { padding: 5px 10px; border-radius: 7px; color: var(--muted); }
.tabs a.active { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
.tabs a:hover { text-decoration: none; color: var(--text); }
form.inline { display: inline; }
.filters { display: flex; gap: 8px; flex-wrap: wrap; }
input[type=search], select { background: var(--bg); color: var(--text); border: 1px solid var(--border); border-radius: 7px; padding: 6px 10px; font: inherit; }
.btn { display: inline-block; background: var(--panel); color: var(--text); border: 1px solid var(--border); border-radius: 7px; padding: 6px 12px; font: inherit; cursor: pointer; }
.btn:hover { background: var(--bg); text-decoration: none; }
.btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.btn.danger { color: var(--bad); }
.flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
.flash.success { background: var(--ok-soft); color: var(--ok); }
.flash.error { background: var(--bad-soft); color: var(--bad); }
pre { background: var(--code); border-radius: 8px; padding: 14px; margin: 0; overflow: auto; max-height: 480px; white-space: pre-wrap; word-break: break-word; }
dl.meta { display: grid; grid-template-columns: 160px 1fr; margin: 0; }
dl.meta dt, dl.meta dd { padding: 8px 16px; border-bottom: 1px solid var(--border); margin: 0; }
dl.meta dt { color: var(--muted); }
.chart { width: 100%; height: 120px; display: block; }
.chart-legend { display: flex; justify-content: space-between; font-size: 12px; }
h1 .actions { margin-left: auto; }
.chart rect { fill: var(--accent); opacity: .85; }
.chart rect.zero { opacity: .15; }
.progress { height: 6px; background: var(--bg); border-radius: 99px; overflow: hidden; min-width: 120px; display: flex; }
.progress span { display: block; height: 100%; }
.progress .ok { background: var(--ok); } .progress .bad { background: var(--bad); }
.pagination { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; border-top: 1px solid var(--border); color: var(--muted); }
@media (max-width: 860px) {
    .app { grid-template-columns: 1fr; }
    .sidebar { position: static; height: auto; flex-direction: row; flex-wrap: wrap; align-items: center; padding: 10px 16px; gap: 6px; }
    .sidebar nav { display: flex; flex-wrap: wrap; gap: 2px; }
    .nav-title { display: none; }
    .sidebar-foot { width: 100%; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); align-items: end; padding: 8px 0 0; }
    .brand { padding: 0 8px 0 0; }
    .main { padding: 16px; }
    dl.meta { grid-template-columns: 1fr; }
    dl.meta dt { border-bottom: 0; padding-bottom: 0; }
}
