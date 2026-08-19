<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DB Migration Tool — SQL Server → PostgreSQL</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f5fa;
            --bg-subtle: #ececf5;
            --surface: #ffffff;
            --surface-2: #fbfbff;
            --border: #e5e7f0;
            --border-strong: #d3d6e3;
            --text: #14151f;
            --text-muted: #6b7085;
            --text-faint: #9498ab;
            --primary: #5850ec;
            --primary-hover: #4740d4;
            --primary-soft: #eeecff;
            --primary-ring: rgba(88, 80, 236, .28);
            --ok: #17a568;
            --ok-bg: #eafbf3;
            --ok-border: #bdf0d7;
            --err: #e13a4b;
            --err-bg: #fdedef;
            --err-border: #f7c6cc;
            --warn: #b45309;
            --warn-bg: #fff8e9;
            --warn-border: #ffe4ab;
            --info: #2f6fed;
            --info-bg: #eef4ff;
            --info-border: #cbdcfc;
            --radius-sm: 8px;
            --radius: 12px;
            --radius-lg: 18px;
            --shadow-xs: 0 1px 2px rgba(20, 21, 31, .04);
            --shadow-sm: 0 2px 8px -2px rgba(20, 21, 31, .08), 0 1px 2px rgba(20, 21, 31, .04);
            --shadow: 0 8px 24px -8px rgba(20, 21, 31, .14), 0 2px 6px -2px rgba(20, 21, 31, .06);
            --shadow-lg: 0 24px 56px -16px rgba(20, 21, 31, .32);
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0c0d13;
                --bg-subtle: #121319;
                --surface: #171923;
                --surface-2: #1c1f2b;
                --border: #272b3a;
                --border-strong: #363b4e;
                --text: #eef0f8;
                --text-muted: #9599ad;
                --text-faint: #6d7288;
                --primary: #8b84ff;
                --primary-hover: #a29bff;
                --primary-soft: #24234a;
                --primary-ring: rgba(139, 132, 255, .35);
                --ok: #35d68f;
                --ok-bg: #0e2a20;
                --ok-border: #1c4a36;
                --err: #ff6b7a;
                --err-bg: #2f151a;
                --err-border: #4d232b;
                --warn: #fbbf24;
                --warn-bg: #2b2309;
                --warn-border: #493c12;
                --info: #6ba3ff;
                --info-bg: #13233d;
                --info-border: #21365a;
                --shadow-xs: 0 1px 2px rgba(0, 0, 0, .35);
                --shadow-sm: 0 2px 10px -2px rgba(0, 0, 0, .45);
                --shadow: 0 10px 30px -10px rgba(0, 0, 0, .55);
                --shadow-lg: 0 30px 70px -20px rgba(0, 0, 0, .7);
            }
        }

        * { box-sizing: border-box; }
        html { color-scheme: light dark; }
        body {
            margin: 0;
            font-family: var(--font-sans);
            background:
                radial-gradient(1200px 480px at 50% -140px, var(--primary-soft), transparent 65%),
                var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }
        ::selection { background: var(--primary-ring); }
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 999px; border: 2px solid var(--bg); }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-faint); }

        .app-shell { max-width: 1080px; margin: 0 auto; padding: 0 20px 90px; }

        .app-header { padding: 40px 0 28px; }
        .brand { display: flex; align-items: center; gap: 14px; }
        .brand-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            background: linear-gradient(155deg, var(--primary), #8f7bff);
            color: #fff;
            font-size: 1.25rem;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }
        h1 { font-size: 1.5rem; font-weight: 800; letter-spacing: -.01em; margin: 0 0 3px; }
        .subtitle { color: var(--text-muted); margin: 0; font-size: 0.9rem; }

        .creds-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        @media (max-width: 760px) { .creds-grid { grid-template-columns: 1fr; } }

        .cred-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow-xs);
            transition: box-shadow .2s ease, border-color .2s ease;
        }
        .cred-card:hover { box-shadow: var(--shadow-sm); border-color: var(--border-strong); }
        .cred-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .cred-icon {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px;
            border-radius: 10px;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .cred-icon.source-icon { background: #fef1e2; color: #b45309; }
        .cred-icon.target-icon { background: #e3f0ff; color: #1d5fd6; }
        @media (prefers-color-scheme: dark) {
            .cred-icon.source-icon { background: #33270f; color: #f5b357; }
            .cred-icon.target-icon { background: #10233f; color: #7cb0ff; }
        }
        .cred-title { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted); font-weight: 700; margin-bottom: 3px; }
        .badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 999px;
        }
        .badge.sqlserver { background: #fef1e2; color: #b45309; }
        .badge.postgres { background: #e3f0ff; color: #1d5fd6; }
        @media (prefers-color-scheme: dark) {
            .badge.sqlserver { background: #33270f; color: #f5b357; }
            .badge.postgres { background: #10233f; color: #7cb0ff; }
        }

        .field { margin-top: 14px; }
        .field:first-of-type { margin-top: 0; }
        .field-row { display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-top: 14px; }
        .field-row .field { margin-top: 0; }
        label { display: block; font-size: 0.78rem; font-weight: 600; margin: 0 0 6px; color: var(--text-muted); }
        input[type=text], input[type=number], input[type=password] {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            font-family: var(--font-sans);
            background: var(--surface-2);
            color: var(--text);
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        input::placeholder { color: var(--text-faint); }
        input:hover { border-color: var(--border-strong); }
        input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--surface);
            box-shadow: 0 0 0 3.5px var(--primary-ring);
        }

        .actions { display: flex; align-items: center; gap: 12px; margin: 22px 0; flex-wrap: wrap; }
        button {
            font-family: var(--font-sans);
            font-size: 0.86rem;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, box-shadow .15s ease, transform .1s ease, opacity .15s ease;
        }
        button:active:not(:disabled) { transform: translateY(1px); }
        button.primary { background: var(--primary); color: #fff; box-shadow: 0 2px 10px -3px var(--primary-ring); }
        button.primary:hover:not(:disabled) { background: var(--primary-hover); box-shadow: 0 4px 16px -3px var(--primary-ring); }
        button.secondary { background: var(--surface); color: var(--text); border-color: var(--border); }
        button.secondary:hover:not(:disabled) { background: var(--surface-2); border-color: var(--border-strong); }
        button.danger { background: var(--err); color: #fff; box-shadow: 0 2px 10px -3px rgba(225, 58, 75, .4); }
        button.danger:hover:not(:disabled) { background: #c62d3d; }
        button:disabled { opacity: 0.45; cursor: not-allowed; box-shadow: none; }
        button:focus-visible { outline: none; box-shadow: 0 0 0 3.5px var(--primary-ring); }

        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: var(--text-faint); }
        .dot.ok { background: var(--ok); }
        .dot.err { background: var(--err); }
        .conn-status { display: flex; gap: 18px; margin-top: 4px; font-size: 0.82rem; flex-wrap: wrap; }
        .conn-status .item { display: flex; align-items: center; gap: 7px; color: var(--text-muted); }
        .conn-status .msg { color: var(--err); }

        .conn-status-bar {
            display: flex;
            gap: 22px;
            margin-top: 18px;
            padding: 12px 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.85rem;
            flex-wrap: wrap;
            box-shadow: var(--shadow-xs);
        }
        .conn-status-item { display: flex; align-items: center; gap: 9px; }
        .conn-label { font-weight: 600; }
        .conn-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; background: var(--text-faint); flex-shrink: 0; }
        .conn-dot.unknown { background: var(--text-faint); }
        .conn-dot.ok { background: var(--ok); box-shadow: 0 0 0 4px var(--ok-bg); }
        .conn-dot.down { background: var(--err); box-shadow: 0 0 0 4px var(--err-bg); animation: pulse-down 1.4s ease-in-out infinite; }
        @keyframes pulse-down {
            0%, 100% { box-shadow: 0 0 0 4px var(--err-bg); }
            50% { box-shadow: 0 0 0 7px var(--err-bg); }
        }
        .reconnect-btn {
            font-size: 0.74rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 999px;
            border: 1px solid var(--err-border);
            background: var(--err-bg);
            color: var(--err);
            cursor: pointer;
            transition: background .15s ease, color .15s ease;
        }
        .reconnect-btn:hover:not(:disabled) { background: var(--err); color: #fff; }
        .reconnect-btn:disabled { opacity: 0.65; cursor: not-allowed; }

        .tabs {
            display: flex;
            gap: 4px;
            margin: 26px 0 0;
            padding: 5px;
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow-x: auto;
        }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            padding: 9px 14px;
            font-weight: 600;
            font-size: 0.84rem;
            color: var(--text-muted);
            white-space: nowrap;
            box-shadow: none;
        }
        .tab-btn:hover:not(.active) { color: var(--text); }
        .tab-btn.active {
            background: var(--surface);
            color: var(--primary);
            border-color: var(--border);
            box-shadow: var(--shadow-xs);
        }
        .tab-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: var(--bg-subtle);
            color: var(--text-faint);
            font-size: 0.68rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: background .15s ease, color .15s ease;
        }
        .tab-btn.active .tab-num { background: var(--primary); color: #fff; }

        .tab-panel { display: none; padding-top: 22px; animation: fade-in .18s ease; }
        .tab-panel.active { display: block; }
        @keyframes fade-in { from { opacity: 0; transform: translateY(3px); } to { opacity: 1; transform: none; } }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-xs);
        }
        .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .panel-head h2 { margin: 0; font-size: 1.05rem; font-weight: 700; letter-spacing: -.005em; }
        .panel-desc { font-size: 0.86rem; color: var(--text-muted); margin: 0 0 18px; line-height: 1.6; }
        .panel-desc code {
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            padding: 1px 6px;
            border-radius: 5px;
            font-family: var(--font-mono);
            font-size: 0.85em;
            color: var(--text);
        }

        .table-list { border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .table-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.87rem;
            background: var(--surface);
            transition: background .12s ease;
        }
        .table-row:hover { background: var(--surface-2); }
        .table-row:last-child { border-bottom: none; }
        .table-row .name { font-weight: 600; font-family: var(--font-mono); font-size: 0.85rem; }
        .table-row .count { color: var(--text-muted); font-size: 0.79rem; text-align: right; }
        .target-note { display: block; font-size: 0.73rem; color: var(--text-muted); margin-top: 2px; }
        .target-note.warn { color: var(--warn); font-weight: 700; }

        /* Daftar tabel di tab Database Migration bisa di-drag buat atur urutan
           (penting kalau ada FK — tabel induk harus dimigrasi lebih dulu). */
        #tableListMigrate .table-row { grid-template-columns: auto auto auto 1fr auto auto; cursor: default; }
        #tableListMigrate .table-row.dragging { opacity: 0.4; background: var(--bg-subtle); }
        #tableListMigrate.filter-mismatch-only .table-row[data-mismatch="false"] { display: none; }
        #tableListMigrate .table-row.drag-over { border-top: 2px solid var(--primary); }
        .drag-handle {
            cursor: grab;
            color: var(--text-faint);
            font-size: 1.1rem;
            line-height: 1;
            user-select: none;
            padding: 0 2px;
            transition: color .15s ease;
        }
        .drag-handle:hover { color: var(--text-muted); }
        .drag-handle:active { cursor: grabbing; }
        .order-badge {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            border-radius: 999px;
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .table-row .result { font-size: 0.78rem; font-weight: 700; min-width: 140px; text-align: right; }
        .result.pending { color: var(--text-faint); font-weight: 500; }
        .result.success { color: var(--ok); }
        .result.error { color: var(--err); cursor: pointer; text-underline-offset: 3px; }
        .result.error:hover { text-decoration: underline; }
        .result.skip { color: var(--warn); }

        .toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; font-size: 0.85rem; flex-wrap: wrap; }
        .toolbar label { margin: 0; display: flex; align-items: center; gap: 7px; font-weight: 500; color: var(--text); cursor: pointer; }
        .toolbar input[type=checkbox] { accent-color: var(--primary); width: 15px; height: 15px; cursor: pointer; }
        .table-count-total { color: var(--text-faint); font-weight: 400; }
        .empty-hint { color: var(--text-faint); font-size: 0.85rem; padding: 28px 16px; text-align: center; }

        .alert {
            padding: 11px 15px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            margin-bottom: 14px;
            border: 1px solid transparent;
            line-height: 1.55;
        }
        .alert.error { background: var(--err-bg); color: var(--err); border-color: var(--err-border); }
        .alert.info { background: var(--info-bg); color: var(--info); border-color: var(--info-border); }
        .alert.warn { background: var(--warn-bg); color: var(--warn); border-color: var(--warn-border); }
        .alert.ok { background: var(--ok-bg); color: var(--ok); border-color: var(--ok-border); }

        pre.sql {
            background: #10121c;
            color: #cfd6ff;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.76rem;
            font-family: var(--font-mono);
            overflow-x: auto;
            margin: 8px 0 0;
            white-space: pre-wrap;
        }
        details.sql-toggle summary { cursor: pointer; font-size: 0.76rem; color: var(--primary); font-weight: 600; }
        .proc-list { list-style: none; margin: 8px 0 0; padding: 0; font-size: 0.85rem; }
        .proc-list li { padding: 5px 0; font-family: var(--font-mono); font-size: 0.82rem; }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(12, 13, 19, 0.6);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity .18s ease;
        }
        .modal-overlay.visible { opacity: 1; pointer-events: auto; }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            max-width: 440px;
            width: 100%;
            padding: 26px;
            box-shadow: var(--shadow-lg);
            transform: translateY(10px) scale(.97);
            transition: transform .18s ease;
        }
        .modal-overlay.visible .modal-box { transform: translateY(0) scale(1); }
        .modal-icon {
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--err-bg);
            color: var(--err);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
            margin-bottom: 16px;
        }
        .modal-box h3 { margin: 0 0 8px; font-size: 1.05rem; font-weight: 700; }
        .modal-box p { margin: 0 0 4px; font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; }
        .modal-table-list {
            list-style: none;
            margin: 14px 0;
            padding: 10px 14px;
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            max-height: 160px;
            overflow-y: auto;
            font-size: 0.83rem;
            font-family: var(--font-mono);
        }
        .modal-table-list li { padding: 3px 0; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

        .elapsed-timer {
            font-size: 0.79rem;
            font-family: var(--font-mono);
            color: var(--text-muted);
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 4px 12px;
        }
    </style>
</head>
<body>
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-icon">⚠</div>
        <h3 id="confirmModalTitle">Konfirmasi</h3>
        <p id="confirmModalMessage"></p>
        <ul class="modal-table-list" id="confirmModalList"></ul>
        <div class="modal-actions">
            <button type="button" class="secondary" id="confirmModalCancel">Batal</button>
            <button type="button" class="danger" id="confirmModalOk">Ya, Lanjutkan</button>
        </div>
    </div>
</div>
<div class="app-shell">
    <header class="app-header">
        <div class="brand">
            <span class="brand-mark">⇄</span>
            <div>
                <h1>Database Migration Tool</h1>
                <p class="subtitle">SQL Server (source) → PostgreSQL (target) · kredensial hanya dipakai per-request, tidak disimpan</p>
            </div>
        </div>
    </header>

    <div class="creds-grid">
        <div class="cred-card">
            <div class="cred-card-head">
                <span class="cred-icon source-icon">⛁</span>
                <div>
                    <div class="cred-title">Source</div>
                    <span class="badge sqlserver">SQL Server</span>
                </div>
            </div>

            <div class="field">
                <label for="src_host">Host</label>
                <input type="text" id="src_host" value="{{ $defaults['source']['host'] }}" placeholder="mydb.database.windows.net">
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="src_database">Database</label>
                    <input type="text" id="src_database" value="{{ $defaults['source']['database'] }}">
                </div>
                <div class="field">
                    <label for="src_port">Port</label>
                    <input type="number" id="src_port" value="{{ $defaults['source']['port'] }}">
                </div>
            </div>

            <div class="field">
                <label for="src_schema">Schema</label>
                <input type="text" id="src_schema" value="{{ $defaults['source']['schema'] }}">
            </div>

            <div class="field">
                <label for="src_username">Username</label>
                <input type="text" id="src_username" value="{{ $defaults['source']['username'] }}">
            </div>

            <div class="field">
                <label for="src_password">Password</label>
                <input type="password" id="src_password" value="{{ $defaults['source']['password'] }}" placeholder="••••••••">
            </div>
        </div>

        <div class="cred-card">
            <div class="cred-card-head">
                <span class="cred-icon target-icon">⛃</span>
                <div>
                    <div class="cred-title">Target</div>
                    <span class="badge postgres">PostgreSQL</span>
                </div>
            </div>

            <div class="field">
                <label for="tgt_host">Host</label>
                <input type="text" id="tgt_host" value="{{ $defaults['target']['host'] }}" placeholder="localhost">
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="tgt_database">Database</label>
                    <input type="text" id="tgt_database" value="{{ $defaults['target']['database'] }}">
                </div>
                <div class="field">
                    <label for="tgt_port">Port</label>
                    <input type="number" id="tgt_port" value="{{ $defaults['target']['port'] }}">
                </div>
            </div>

            <div class="field">
                <label for="tgt_schema">Schema</label>
                <input type="text" id="tgt_schema" value="{{ $defaults['target']['schema'] }}">
            </div>

            <div class="field">
                <label for="tgt_username">Username</label>
                <input type="text" id="tgt_username" value="{{ $defaults['target']['username'] }}">
            </div>

            <div class="field">
                <label for="tgt_password">Password</label>
                <input type="password" id="tgt_password" value="{{ $defaults['target']['password'] }}" placeholder="••••••••">
            </div>
        </div>
    </div>

    <div class="conn-status-bar" id="connStatusBar">
        <div class="conn-status-item" id="connStatusSource" data-side="source">
            <span class="conn-dot unknown"></span>
            <span class="conn-label">Source: belum dicek</span>
        </div>
        <div class="conn-status-item" id="connStatusTarget" data-side="target">
            <span class="conn-dot unknown"></span>
            <span class="conn-label">Target: belum dicek</span>
        </div>
    </div>

    <div class="tabs" id="tabs">
        <button type="button" class="tab-btn active" data-tab="test"><span class="tab-num">1</span> Test Connection</button>
        <button type="button" class="tab-btn" data-tab="create-db"><span class="tab-num">2</span> Create Database</button>
        <button type="button" class="tab-btn" data-tab="create-schema"><span class="tab-num">3</span> Create Schema</button>
        <button type="button" class="tab-btn" data-tab="truncate"><span class="tab-num">4</span> Truncate Table</button>
        <button type="button" class="tab-btn" data-tab="migrate"><span class="tab-num">5</span> Database Migration</button>
    </div>

    {{-- Tab 1: Test Connection --}}
    <div class="tab-panel active" data-panel="test">
        <section class="panel">
            <div class="panel-head"><h2>Test Connection</h2></div>
            <p class="panel-desc">Menguji koneksi ke source &amp; target sekaligus, dan memuat daftar tabel di schema source (dipakai tab Create Schema &amp; Database Migration).</p>
            <div class="actions" style="margin-top:0;">
                <button type="button" class="primary" id="btnTest">🔌 Test Connection</button>
                <div class="conn-status" id="connStatus"></div>
            </div>
            <div id="testAlertBox"></div>
        </section>
    </div>

    {{-- Tab 2: Create Database --}}
    <div class="tab-panel" data-panel="create-db">
        <section class="panel">
            <div class="panel-head"><h2>Create Database</h2></div>
            <p class="panel-desc">Membuat database PostgreSQL tujuan (memakai kredensial Target di atas) kalau belum ada. Tersambung dulu ke database maintenance <code>postgres</code>, jadi tidak perlu database target sudah ada sebelumnya.</p>
            <div class="actions" style="margin-top:0;">
                <button type="button" class="primary" id="btnCreateDb">🗄️ Buat Database</button>
            </div>
            <div id="createDbAlertBox"></div>
        </section>
    </div>

    {{-- Tab 3: Create Schema --}}
    <div class="tab-panel" data-panel="create-schema">
        <section class="panel">
            <div class="panel-head">
                <h2>Create Schema</h2>
                <button type="button" class="secondary" id="btnLoadTablesSchema">Muat Daftar Tabel Source</button>
            </div>
            <p class="panel-desc">Membuat schema + tabel di target berdasarkan struktur kolom tabel source (tipe data diterjemahkan otomatis, kolom IDENTITY &amp; primary key ikut dibuat), lalu setelah semua tabel terpilih selesai dibuat, foreign key antar tabel juga otomatis dibuat kalau ada di source (dilewati kalau tabel yang direferensikan belum ada di target). Bersifat aman/idempotent — pakai <code>CREATE TABLE IF NOT EXISTS</code>, tidak akan menghapus tabel/data yang sudah ada. Default kolom berupa ekspresi T-SQL (mis. GETDATE()) dilewati, perlu ditambahkan manual.</p>
            <div id="schemaAlertBox"></div>
            <div class="toolbar">
                <label><input type="checkbox" id="chkAllSchema"> Pilih semua <span id="totalCountSchema" class="table-count-total"></span></label>
            </div>
            <div class="table-list" id="tableListSchema">
                <div class="empty-hint">Klik "Muat Daftar Tabel Source" dulu.</div>
            </div>
            <div class="actions">
                <button type="button" class="primary" id="btnCreateSchema" disabled>🏗️ Buat Schema &amp; Tabel</button>
                <button type="button" class="danger" id="btnStopSchema" style="display:none;">⏹ Stop Proses</button>
                <span id="schemaElapsed" class="elapsed-timer" style="display:none;"></span>
                <span id="schemaSummary" style="font-size:0.85rem;color:var(--text-muted);"></span>
            </div>
            <div id="fkBox"></div>
            <div id="procedureBox"></div>
        </section>
    </div>

    {{-- Tab 4: Truncate Table --}}
    <div class="tab-panel" data-panel="truncate">
        <section class="panel">
            <div class="panel-head">
                <h2>Truncate Table</h2>
                <button type="button" class="secondary" id="btnLoadTablesTruncate">Muat Daftar Tabel Target</button>
            </div>
            <p class="panel-desc">Mengosongkan tabel terpilih di database <strong>target</strong> (PostgreSQL). Tindakan ini menghapus seluruh isi tabel — pastikan tabel yang dicentang memang benar.</p>
            <div id="truncateAlertBox"></div>
            <div class="toolbar">
                <label><input type="checkbox" id="chkAllTruncate"> Pilih semua <span id="totalCountTruncate" class="table-count-total"></span></label>
            </div>
            <div class="table-list" id="tableListTruncate">
                <div class="empty-hint">Klik "Muat Daftar Tabel Target" dulu.</div>
            </div>
            <div class="actions">
                <button type="button" class="primary" id="btnTruncate" disabled>🧹 Truncate Tabel Terpilih</button>
                <button type="button" class="danger" id="btnStopTruncate" style="display:none;">⏹ Stop Proses</button>
                <span id="truncateElapsed" class="elapsed-timer" style="display:none;"></span>
                <span id="truncateSummary" style="font-size:0.85rem;color:var(--text-muted);"></span>
            </div>
        </section>
    </div>

    {{-- Tab 5: Database Migration --}}
    <div class="tab-panel" data-panel="migrate">
        <section class="panel">
            <div class="panel-head">
                <h2>Database Migration</h2>
                <button type="button" class="secondary" id="btnLoadTablesMigrate">Muat Daftar Tabel Source</button>
            </div>
            <p class="panel-desc">Menyalin data dari tabel source terpilih ke tabel target dengan nama yang sama (tabel tujuan harus sudah ada — lihat tab Create Schema). Seret ikon <strong>⠿</strong> di tiap baris untuk mengubah urutan migrasi — kalau ada foreign key antar tabel, pastikan tabel induk (yang direferensikan) diurutkan lebih dulu dari pada tabel anak.</p>
            <div id="migrateAlertBox"></div>
            <div class="toolbar">
                <label><input type="checkbox" id="chkAllMigrate"> Pilih semua <span id="totalCountMigrate" class="table-count-total"></span></label>
                <label><input type="checkbox" id="chkTruncate" checked> Kosongkan tabel tujuan sebelum migrasi</label>
                <label><input type="checkbox" id="chkFilterMismatch"> Hanya tampilkan yang belum sinkron (source ≠ target)</label>
            </div>
            <div class="table-list" id="tableListMigrate">
                <div class="empty-hint">Klik "Muat Daftar Tabel Source" dulu.</div>
            </div>
            <div class="actions">
                <button type="button" class="primary" id="btnMigrate" disabled>🚀 Mulai Migrasi</button>
                <button type="button" class="danger" id="btnStopMigrate" style="display:none;">⏹ Stop Proses</button>
                <span id="migrateElapsed" class="elapsed-timer" style="display:none;"></span>
                <span id="migrateSummary" style="font-size:0.85rem;color:var(--text-muted);"></span>
            </div>
        </section>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const $ = (id) => document.getElementById(id);
const state = { sourceTables: null, targetTables: null };

// Tiap tab proses panjang (Create Schema/Truncate/Migration) punya "run state"
// sendiri-sendiri, supaya tombol Stop di satu tab tidak ikut membatalkan
// proses tabel di tab lain kalau kebetulan dijalankan bersamaan.
function createRunState() {
    return { stopped: false, controller: null };
}

function stopRun(runState) {
    runState.stopped = true;
    runState.controller?.abort();
}

function formatDuration(seconds) {
    if (seconds < 60) {
        return `${seconds.toFixed(1)} detik`;
    }

    const minutes = Math.floor(seconds / 60);
    const rest = (seconds - minutes * 60).toFixed(1);

    return `${minutes} menit ${rest} detik`;
}

/**
 * Timer yang jalan tiap 200ms selagi proses berlangsung, supaya user tahu
 * sudah berapa lama proses ini jalan tanpa perlu tunggu sampai selesai.
 */
function startElapsedTimer(elId, startTime) {
    const el = $(elId);
    el.style.display = '';

    const tick = () => {
        el.textContent = `⏱ Sudah berjalan: ${formatDuration((performance.now() - startTime) / 1000)}`;
    };

    tick();
    return setInterval(tick, 200);
}

function stopElapsedTimer(intervalId, elId) {
    clearInterval(intervalId);
    $(elId).style.display = 'none';
}

const routes = {
    testConnection: '{{ route('migration.test-connection') }}',
    createDatabase: '{{ route('migration.create-database') }}',
    createSchema: '{{ route('migration.create-schema') }}',
    createForeignKeys: '{{ route('migration.create-foreign-keys') }}',
    targetTables: '{{ route('migration.target-tables') }}',
    truncate: '{{ route('migration.truncate') }}',
    migrate: '{{ route('migration.migrate') }}',
    migrateChunk: '{{ route('migration.migrate-chunk') }}',
};

function credentials() {
    return {
        source: {
            host: $('src_host').value.trim(),
            port: parseInt($('src_port').value, 10),
            database: $('src_database').value.trim(),
            username: $('src_username').value.trim(),
            password: $('src_password').value,
            schema: $('src_schema').value.trim(),
        },
        target: {
            host: $('tgt_host').value.trim(),
            port: parseInt($('tgt_port').value, 10),
            database: $('tgt_database').value.trim(),
            username: $('tgt_username').value.trim(),
            password: $('tgt_password').value,
            schema: $('tgt_schema').value.trim(),
        },
    };
}

function showAlert(boxId, message, type = 'error') {
    $(boxId).innerHTML = message ? `<div class="alert ${type}">${message}</div>` : '';
}

async function postJson(url, payload, signal) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(payload),
        signal,
    });

    const body = await res.json().catch(() => ({}));

    if (!res.ok) {
        const message = body?.message
            || (body?.errors ? Object.values(body.errors).flat().join(', ') : null)
            || `HTTP ${res.status}`;
        throw new Error(message);
    }

    return body;
}

// ---------- Custom confirm modal (pengganti confirm() bawaan browser) ----------
/**
 * mode 'confirm' (default) = dialog Batal/Ya Lanjutkan seperti biasa.
 * mode 'info' = popup satu tombol ("Tutup") buat sekadar nampilin info/detail,
 * dipakai buat popup detail error (lihat enableErrorDetailPopup()).
 */
function confirmModal({ title = 'Konfirmasi', message = '', items = [], mode = 'confirm' }) {
    return new Promise((resolve) => {
        const overlay = $('confirmModal');
        $('confirmModalTitle').textContent = title;
        $('confirmModalMessage').textContent = message;

        const list = $('confirmModalList');
        if (items.length) {
            list.innerHTML = items.map(i => `<li>${i}</li>`).join('');
            list.style.display = '';
        } else {
            list.innerHTML = '';
            list.style.display = 'none';
        }

        const okBtn = $('confirmModalOk');
        const cancelBtn = $('confirmModalCancel');
        const isInfo = mode === 'info';

        cancelBtn.style.display = isInfo ? 'none' : '';
        okBtn.textContent = isInfo ? 'Tutup' : 'Ya, Lanjutkan';
        okBtn.className = isInfo ? 'secondary' : 'danger';

        const close = (result) => {
            overlay.classList.remove('visible');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            overlay.removeEventListener('click', onOverlay);
            document.removeEventListener('keydown', onKey);
            resolve(result);
        };

        const onOk = () => close(true);
        const onCancel = () => close(false);
        const onOverlay = (e) => { if (e.target === overlay) close(false); };
        const onKey = (e) => { if (e.key === 'Escape') close(false); };

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
        overlay.addEventListener('click', onOverlay);
        document.addEventListener('keydown', onKey);

        overlay.classList.add('visible');
    });
}

/**
 * Klik label "Gagal" (merah) di daftar tabel → munculin popup detail error-nya
 * (sebelumnya cuma bisa dilihat lewat hover tooltip/title attribute).
 */
function enableErrorDetailPopup(containerId) {
    $(containerId).addEventListener('click', (e) => {
        const resultEl = e.target.closest('.result.error');
        if (!resultEl) return;

        const table = resultEl.closest('.table-row')?.dataset.table || '';

        confirmModal({
            title: `Detail Error — ${table}`,
            message: resultEl.dataset.errorMessage || 'Tidak ada detail error yang tercatat.',
            mode: 'info',
        });
    });
}

// ---------- Tabs ----------
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.querySelector(`.tab-panel[data-panel="${btn.dataset.tab}"]`).classList.add('active');
    });
});

// ---------- Tab 1: Test Connection ----------
function renderConnStatus({ source, target }) {
    const item = (label, ok, message) => `
        <div class="item">
            <span class="dot ${ok ? 'ok' : 'err'}"></span>
            <span>${label}: ${ok ? 'Terhubung' : 'Gagal'}</span>
            ${!ok && message ? `<span class="msg" title="${message}">(${message})</span>` : ''}
        </div>`;

    $('connStatus').innerHTML =
        item('Source', source.ok, source.message) +
        item('Target', target.ok, target.message);
}

// Bar status koneksi yang selalu kelihatan di semua tab (bukan cuma tab 1),
// supaya begitu koneksi putus di tengah proses lain, user langsung sadar
// tanpa harus pindah ke tab Test Connection dulu.
function renderPersistentConnStatus(side, ok, message) {
    const el = $(side === 'source' ? 'connStatusSource' : 'connStatusTarget');
    const label = side === 'source' ? 'Source' : 'Target';
    const dotClass = ok ? 'ok' : 'down';

    el.innerHTML = `
        <span class="conn-dot ${dotClass}"></span>
        <span class="conn-label">${label}: ${ok ? 'terhubung' : 'terputus'}</span>
        ${!ok ? `<button type="button" class="reconnect-btn" data-side="${side}" title="${message ? message.replace(/"/g, '&quot;') : ''}">🔄 Reconnect</button>` : ''}
    `;
}

$('connStatusBar').addEventListener('click', async (e) => {
    const btn = e.target.closest('.reconnect-btn');
    if (!btn) return;

    btn.disabled = true;
    btn.textContent = '⏳ Menyambung…';

    try {
        await runTestConnection();
    } catch {
        // error sudah ditampilkan lewat testAlertBox & bar status di dalam runTestConnection()
    }
});

async function runTestConnection(silent = false) {
    if (!silent) {
        showAlert('testAlertBox', '');
        $('connStatus').innerHTML = '';
    }

    const btn = $('btnTest');
    if (!silent) {
        btn.disabled = true;
        btn.textContent = 'Menguji koneksi…';
    }

    try {
        const result = await postJson(routes.testConnection, credentials());

        renderPersistentConnStatus('source', result.source.ok, result.source.message);
        renderPersistentConnStatus('target', result.target.ok, result.target.message);

        if (!silent) {
            renderConnStatus(result);
        }

        if (result.source.ok) {
            state.sourceTables = result.tables;
        }

        if (!silent && (!result.source.ok || !result.target.ok)) {
            showAlert('testAlertBox', 'Salah satu atau kedua koneksi gagal. Periksa kredensial di atas.', 'error');
        }

        return result;
    } catch (err) {
        if (silent) {
            throw err;
        }

        showAlert('testAlertBox', err.message, 'error');
        throw err;
    } finally {
        if (!silent) {
            btn.disabled = false;
            btn.textContent = 'Test Connection';
        }
    }
}

$('btnTest').addEventListener('click', () => runTestConnection());

// Cek koneksi otomatis begitu halaman dimuat (kalau kredensial sudah terisi dari .env),
// supaya bar status tidak "belum dicek" terus padahal sebenarnya sudah bisa dicek.
if ($('src_host').value && $('tgt_host').value) {
    runTestConnection(true).catch(() => {});
}

// ---------- Tab 2: Create Database ----------
$('btnCreateDb').addEventListener('click', async () => {
    showAlert('createDbAlertBox', '');
    const btn = $('btnCreateDb');
    btn.disabled = true;
    btn.textContent = 'Membuat database…';

    try {
        const result = await postJson(routes.createDatabase, credentials());
        showAlert('createDbAlertBox', result.message, result.created ? 'ok' : 'info');
    } catch (err) {
        showAlert('createDbAlertBox', err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Buat Database';
    }
});

// ---------- Shared: generic checklist renderer ----------
function renderCheckList(containerId, chkClass, tables, extraColumnFn, countElId, draggable = false, rowDataFn = null) {
    const list = $(containerId);

    if (countElId && $(countElId)) {
        $(countElId).textContent = `(${tables.length} tabel)`;
    }

    if (!tables.length) {
        list.innerHTML = '<div class="empty-hint">Tidak ada tabel ditemukan.</div>';
        return;
    }

    list.innerHTML = tables.map((t, i) => `
        <div class="table-row" data-table="${t.name}" ${rowDataFn ? rowDataFn(t) : ''} ${draggable ? 'draggable="true"' : ''}>
            ${draggable ? `<span class="drag-handle" title="Seret untuk ubah urutan migrasi">⠿</span><span class="order-badge">${i + 1}</span>` : ''}
            <input type="checkbox" class="${chkClass}" value="${t.name}">
            <span class="name">${t.name}</span>
            <span class="count">${extraColumnFn ? extraColumnFn(t) : ''}</span>
            <span class="result pending">—</span>
        </div>
    `).join('');

    if (draggable) {
        renumberDragRows(containerId);
    }
}

function renumberDragRows(containerId) {
    $(containerId).querySelectorAll('.table-row').forEach((row, i) => {
        const badge = row.querySelector('.order-badge');
        if (badge) {
            badge.textContent = i + 1;
        }
    });
}

/**
 * Drag-and-drop native (tanpa library) buat urutkan baris tabel — penting
 * kalau ada FK, tabel induk harus dimigrasi lebih dulu dari pada tabel anak.
 * Urutan hasil drag ini otomatis kepakai karena selected tables dibaca dari
 * urutan DOM (lihat document.querySelectorAll('.chk-migrate:checked')).
 */
function enableDragReorder(containerId) {
    const container = $(containerId);
    let draggedEl = null;

    const getDragAfterElement = (y) => {
        const rows = [...container.querySelectorAll('.table-row:not(.dragging)')];

        return rows.reduce((closest, row) => {
            const box = row.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset, element: row };
            }

            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    };

    container.addEventListener('dragstart', (e) => {
        const row = e.target.closest('.table-row');
        if (!row) return;

        draggedEl = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragend', () => {
        draggedEl?.classList.remove('dragging');
        draggedEl = null;
        renumberDragRows(containerId);
    });

    container.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (!draggedEl) return;

        const afterEl = getDragAfterElement(e.clientY);

        if (afterEl == null) {
            container.appendChild(draggedEl);
        } else {
            container.insertBefore(draggedEl, afterEl);
        }
    });
}

function bindSelectAll(chkAllId, chkClass, onChange) {
    $(chkAllId).addEventListener('change', (e) => {
        document.querySelectorAll('.' + chkClass).forEach(cb => {
            // offsetParent null = baris lagi disembunyikan (mis. oleh filter) — jangan ikut dipilih.
            if (cb.closest('.table-row')?.offsetParent === null) {
                return;
            }
            cb.checked = e.target.checked;
        });
        onChange();
    });
}

function bindRowCheckboxes(containerId, chkClass, onChange) {
    $(containerId).querySelectorAll('.' + chkClass).forEach(cb => cb.addEventListener('change', onChange));
    onChange();
}

/**
 * Proses tabel satu-per-satu (bukan sekaligus dalam satu request), supaya
 * label "Sedang diproses…" cuma nyala di tabel yang lagi jalan, dan tiap
 * tabel dapat durasi eksekusinya sendiri. Dicek juga runState.stopped di
 * setiap giliran — begitu tombol "Stop Proses" diklik, tabel yang belum
 * kebagian giliran langsung ditandai "Dibatalkan" tanpa dikirim requestnya.
 *
 * processFn(table, rowWrapEl) harus resolve ke { ok: bool, text?: string, message?: string }.
 */
async function processTablesSequentially(selected, listContainerId, summaryElId, processFn, runState) {
    let success = 0, failed = 0, cancelled = 0;

    for (const table of selected) {
        const rowWrap = document.querySelector(`#${listContainerId} .table-row[data-table="${CSS.escape(table)}"]`);
        const resultEl = rowWrap.querySelector('.result');

        if (runState.stopped) {
            resultEl.textContent = 'Dibatalkan';
            resultEl.className = 'result skip';
            cancelled++;
            continue;
        }

        resultEl.textContent = 'Sedang diproses…';
        resultEl.className = 'result pending';

        if (summaryElId) {
            $(summaryElId).textContent = `Memproses "${table}"… (${success + failed}/${selected.length} selesai)`;
        }

        const start = performance.now();

        let outcome;
        try {
            outcome = await processFn(table, rowWrap);
        } catch (err) {
            outcome = err.name === 'AbortError'
                ? { ok: false, stopped: true }
                : { ok: false, message: err.message };
        }

        const duration = ((performance.now() - start) / 1000).toFixed(1);

        if (outcome.ok) {
            resultEl.textContent = `${outcome.text} (${duration}s) ✓`;
            resultEl.className = 'result success';
            success++;
        } else if (outcome.stopped) {
            resultEl.textContent = outcome.text ? `${outcome.text} (dihentikan)` : 'Dihentikan';
            resultEl.className = 'result skip';
            cancelled++;
        } else {
            // outcome.text (kalau ada) = progres yang sempat kepenuhi sebelum gagal, mis. "3200 / 10000 baris"
            resultEl.textContent = outcome.text ? `${outcome.text} (${duration}s) ✗` : 'Gagal';
            resultEl.className = 'result error';
            resultEl.title = 'Klik untuk lihat detail error';
            resultEl.dataset.errorMessage = outcome.message || 'Tidak ada detail error yang tercatat.';
            failed++;
        }
    }

    return { success, failed, cancelled };
}

function renderForeignKeyResults(fkResults) {
    if (!fkResults.length) {
        $('fkBox').innerHTML = `<div class="alert info">Tidak ada foreign key di source untuk tabel-tabel ini.</div>`;
        return;
    }

    const created = fkResults.filter(f => f.status === 'success');
    const existing = fkResults.filter(f => f.status === 'exists');
    const skipped = fkResults.filter(f => f.status === 'skipped');
    const errored = fkResults.filter(f => f.status === 'error');

    const line = (f, icon) => `<li><strong>${f.fk}</strong> (${f.table})${f.message ? ` — ${f.message}` : ''} ${icon}</li>`;

    let html = `<div class="alert ${errored.length ? 'warn' : 'ok'}">`;
    html += `Foreign key: ${created.length} dibuat, ${existing.length} sudah ada, ${skipped.length} dilewati, ${errored.length} gagal.`;
    html += `<ul class="proc-list">`;
    html += created.map(f => line(f, '✓')).join('');
    html += existing.map(f => line(f, '(sudah ada)')).join('');
    html += skipped.map(f => line(f, '(dilewati)')).join('');
    html += errored.map(f => line(f, '✗')).join('');
    html += `</ul></div>`;

    $('fkBox').innerHTML = html;
}

// ---------- Tab 3: Create Schema ----------
async function loadTablesForSchema() {
    const btn = $('btnLoadTablesSchema');
    btn.disabled = true;
    btn.textContent = 'Memuat…';
    showAlert('schemaAlertBox', '');

    try {
        if (!state.sourceTables) {
            await runTestConnection();
        }
        renderCheckList('tableListSchema', 'chk-schema', state.sourceTables || [], t => `${t.row_count.toLocaleString('id-ID')} baris`, 'totalCountSchema');
        bindRowCheckboxes('tableListSchema', 'chk-schema', updateSchemaButtonState);
    } catch (err) {
        showAlert('schemaAlertBox', err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Muat Daftar Tabel Source';
    }
}

function updateSchemaButtonState() {
    $('btnCreateSchema').disabled = document.querySelectorAll('.chk-schema:checked').length === 0;
}

$('btnLoadTablesSchema').addEventListener('click', loadTablesForSchema);
bindSelectAll('chkAllSchema', 'chk-schema', updateSchemaButtonState);
enableErrorDetailPopup('tableListSchema');

$('btnCreateSchema').addEventListener('click', async () => {
    const selected = Array.from(document.querySelectorAll('.chk-schema:checked')).map(cb => cb.value);
    if (!selected.length) return;

    const btn = $('btnCreateSchema');
    const stopBtn = $('btnStopSchema');
    btn.disabled = true;
    btn.textContent = 'Membuat…';
    stopBtn.style.display = '';
    showAlert('schemaAlertBox', '');
    $('procedureBox').innerHTML = '';
    $('fkBox').innerHTML = '';
    $('schemaSummary').textContent = '';

    let procedures = [];
    const runState = createRunState();
    runState.controller = new AbortController();
    const onStop = () => stopRun(runState);
    stopBtn.addEventListener('click', onStop);
    const batchStart = performance.now();
    const elapsedTimerId = startElapsedTimer('schemaElapsed', batchStart);

    try {
        const { success, failed, cancelled } = await processTablesSequentially(selected, 'tableListSchema', 'schemaSummary', async (table, rowWrap) => {
            const payload = { ...credentials(), tables: [table] };
            const { results, stored_procedures } = await postJson(routes.createSchema, payload, runState.controller.signal);
            const r = results[0];

            if (stored_procedures && stored_procedures.length) {
                procedures = stored_procedures;
            }

            if (r.sql) {
                const details = document.createElement('details');
                details.className = 'sql-toggle';
                details.style.gridColumn = '1 / -1';
                details.innerHTML = `<summary>Lihat DDL</summary><pre class="sql">${r.sql.replace(/</g, '&lt;')}</pre>`;
                rowWrap.appendChild(details);
            }

            return r.status === 'success'
                ? { ok: true, text: 'Berhasil' }
                : { ok: false, message: r.message };
        }, runState);

        // FK dibuat SEKALI untuk semua tabel terpilih, setelah loop CREATE TABLE di atas
        // selesai — supaya urutan tabel yang dicentang tidak memengaruhi apakah tabel
        // yang direferensikan sudah ada atau belum.
        if (success > 0 && !runState.stopped) {
            $('fkBox').innerHTML = `<div class="alert info">Membuat foreign key…</div>`;

            try {
                const { results: fkResults } = await postJson(routes.createForeignKeys, { ...credentials(), tables: selected });
                renderForeignKeyResults(fkResults);
            } catch (err) {
                $('fkBox').innerHTML = `<div class="alert error">Gagal membuat foreign key: ${err.message}</div>`;
            }
        }

        const totalDuration = formatDuration((performance.now() - batchStart) / 1000);
        $('schemaSummary').textContent = `${success} tabel berhasil${failed ? `, ${failed} gagal` : ''}${cancelled ? `, ${cancelled} dibatalkan` : ''}. Total durasi: ${totalDuration}.`;

        if (failed > 0) {
            runTestConnection(true).catch(() => {});
        }

        if (procedures.length) {
            $('procedureBox').innerHTML = `
                <div class="alert warn">
                    Ditemukan ${procedures.length} stored procedure di source. T-SQL tidak bisa diterjemahkan otomatis
                    ke PL/pgSQL — perlu ditulis ulang manual di PostgreSQL.
                    <ul class="proc-list">${procedures.map(p => `<li>${p}</li>`).join('')}</ul>
                </div>`;
        }
    } catch (err) {
        showAlert('schemaAlertBox', err.message, 'error');
    } finally {
        stopElapsedTimer(elapsedTimerId, 'schemaElapsed');
        stopBtn.removeEventListener('click', onStop);
        stopBtn.style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Buat Schema & Tabel';
    }
});

// ---------- Tab 4: Truncate Table ----------
async function loadTablesForTruncate() {
    const btn = $('btnLoadTablesTruncate');
    btn.disabled = true;
    btn.textContent = 'Memuat…';
    showAlert('truncateAlertBox', '');

    try {
        const { tables } = await postJson(routes.targetTables, credentials());
        state.targetTables = tables;
        renderCheckList('tableListTruncate', 'chk-truncate', tables, t => t.row_count === null ? '' : `${t.row_count.toLocaleString('id-ID')} baris`, 'totalCountTruncate');
        bindRowCheckboxes('tableListTruncate', 'chk-truncate', updateTruncateButtonState);
    } catch (err) {
        showAlert('truncateAlertBox', err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Muat Daftar Tabel Target';
    }
}

function updateTruncateButtonState() {
    $('btnTruncate').disabled = document.querySelectorAll('.chk-truncate:checked').length === 0;
}

$('btnLoadTablesTruncate').addEventListener('click', loadTablesForTruncate);
bindSelectAll('chkAllTruncate', 'chk-truncate', updateTruncateButtonState);
enableErrorDetailPopup('tableListTruncate');

$('btnTruncate').addEventListener('click', async () => {
    const selected = Array.from(document.querySelectorAll('.chk-truncate:checked')).map(cb => cb.value);
    if (!selected.length) return;

    const confirmed = await confirmModal({
        title: `Kosongkan ${selected.length} tabel di database TARGET?`,
        message: 'Seluruh isi tabel di bawah ini akan dihapus permanen.',
        items: selected,
    });
    if (!confirmed) return;

    const btn = $('btnTruncate');
    const stopBtn = $('btnStopTruncate');
    btn.disabled = true;
    btn.textContent = 'Menghapus data…';
    stopBtn.style.display = '';
    showAlert('truncateAlertBox', '');
    $('truncateSummary').textContent = '';

    const runState = createRunState();
    runState.controller = new AbortController();
    const onStop = () => stopRun(runState);
    stopBtn.addEventListener('click', onStop);
    const batchStart = performance.now();
    const elapsedTimerId = startElapsedTimer('truncateElapsed', batchStart);

    try {
        const { success, failed, cancelled } = await processTablesSequentially(selected, 'tableListTruncate', 'truncateSummary', async (table, rowWrap) => {
            const payload = { ...credentials(), tables: [table] };
            const { results } = await postJson(routes.truncate, payload, runState.controller.signal);
            const r = results[0];

            if (r.status === 'success') {
                rowWrap.querySelector('.count').textContent = '0 baris';
                return { ok: true, text: 'Kosong' };
            }

            return { ok: false, message: r.message };
        }, runState);

        const totalDuration = formatDuration((performance.now() - batchStart) / 1000);
        $('truncateSummary').textContent = `${success} tabel berhasil dikosongkan${failed ? `, ${failed} gagal` : ''}${cancelled ? `, ${cancelled} dibatalkan` : ''}. Total durasi: ${totalDuration}.`;

        if (failed > 0) {
            runTestConnection(true).catch(() => {});
        }
    } catch (err) {
        showAlert('truncateAlertBox', err.message, 'error');
    } finally {
        stopElapsedTimer(elapsedTimerId, 'truncateElapsed');
        stopBtn.removeEventListener('click', onStop);
        stopBtn.style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Truncate Tabel Terpilih';
    }
});

// ---------- Tab 5: Database Migration ----------
async function loadTablesForMigrate() {
    const btn = $('btnLoadTablesMigrate');
    btn.disabled = true;
    btn.textContent = 'Memuat…';
    showAlert('migrateAlertBox', '');

    try {
        if (!state.sourceTables) {
            await runTestConnection();
        }

        // Dimuat ulang tiap klik (bukan dari cache) supaya jumlah baris target
        // selalu yang terbaru — itu yang dipakai untuk memutuskan perlu migrasi
        // ulang atau tidak.
        let targetCountByName = {};
        try {
            const { tables: targetTables } = await postJson(routes.targetTables, credentials());
            state.targetTables = targetTables;
            targetCountByName = Object.fromEntries(targetTables.map(t => [t.name, t.row_count]));
        } catch {
            // Tabel target gagal dimuat (mis. schema belum dibuat) — tetap tampilkan
            // daftar source-nya, cuma tanpa info jumlah baris target.
        }

        const hasTargetInfo = t => Object.prototype.hasOwnProperty.call(targetCountByName, t.name);
        const isMismatch = t => !hasTargetInfo(t) || targetCountByName[t.name] !== t.row_count;

        renderCheckList('tableListMigrate', 'chk-migrate', state.sourceTables || [], t => {
            const sourceText = `${t.row_count.toLocaleString('id-ID')} baris`;

            let targetText = 'kosong';
            let isWarn = false;

            if (!hasTargetInfo(t)) {
                targetText = 'tabel belum dibuat';
                isWarn = true;
            } else if (targetCountByName[t.name] > 0) {
                targetText = `sudah ada ${targetCountByName[t.name].toLocaleString('id-ID')} baris`;
                isWarn = true;
            }

            return `${sourceText}<span class="target-note${isWarn ? ' warn' : ''}">target: ${targetText}</span>`;
        }, 'totalCountMigrate', true, t => `data-mismatch="${isMismatch(t)}"`);
        bindRowCheckboxes('tableListMigrate', 'chk-migrate', updateMigrateButtonState);
        updateMigrateVisibleCount();
    } catch (err) {
        showAlert('migrateAlertBox', err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Muat Daftar Tabel Source';
    }
}

function updateMigrateButtonState() {
    $('btnMigrate').disabled = document.querySelectorAll('.chk-migrate:checked').length === 0;
}

function updateMigrateVisibleCount() {
    const rows = [...document.querySelectorAll('#tableListMigrate .table-row')];
    if (!rows.length) return;

    if ($('chkFilterMismatch').checked) {
        const visible = rows.filter(r => r.offsetParent !== null).length;
        $('totalCountMigrate').textContent = `(${visible} dari ${rows.length} tabel belum sinkron)`;
    } else {
        $('totalCountMigrate').textContent = `(${rows.length} tabel)`;
    }
}

$('chkFilterMismatch').addEventListener('change', (e) => {
    $('tableListMigrate').classList.toggle('filter-mismatch-only', e.target.checked);
    updateMigrateVisibleCount();
});

$('btnLoadTablesMigrate').addEventListener('click', loadTablesForMigrate);
bindSelectAll('chkAllMigrate', 'chk-migrate', updateMigrateButtonState);
enableDragReorder('tableListMigrate');
enableErrorDetailPopup('tableListMigrate');

$('btnMigrate').addEventListener('click', async () => {
    const selected = Array.from(document.querySelectorAll('.chk-migrate:checked')).map(cb => cb.value);
    if (!selected.length) return;

    const btn = $('btnMigrate');
    const stopBtn = $('btnStopMigrate');
    btn.disabled = true;
    btn.textContent = 'Memigrasi…';
    stopBtn.style.display = '';
    $('migrateSummary').textContent = '';
    showAlert('migrateAlertBox', '');

    const truncateFirst = $('chkTruncate').checked;
    const MIGRATE_CHUNK_SIZE = 1000;

    const runState = createRunState();
    runState.controller = new AbortController();
    const onStop = () => stopRun(runState);
    stopBtn.addEventListener('click', onStop);
    const batchStart = performance.now();
    const elapsedTimerId = startElapsedTimer('migrateElapsed', batchStart);

    try {
        const { success, failed, cancelled } = await processTablesSequentially(selected, 'tableListMigrate', 'migrateSummary', async (table, rowWrap) => {
            const totalRows = (state.sourceTables || []).find(t => t.name === table)?.row_count || 0;
            const resultEl = rowWrap.querySelector('.result');

            let offset = 0;
            let migrated = 0;
            let done = false;
            let isFirstChunk = true;

            const progressText = () => totalRows
                ? `${migrated.toLocaleString('id-ID')} / ${totalRows.toLocaleString('id-ID')} baris…`
                : `${migrated.toLocaleString('id-ID')} baris…`;

            try {
                while (!done) {
                    // Dicek di sini juga (bukan cuma andalkan AbortSignal) supaya begitu
                    // Stop diklik, chunk BERIKUTNYA tidak usah dikirim sama sekali.
                    if (runState.stopped) {
                        const abortErr = new Error('Dihentikan oleh user');
                        abortErr.name = 'AbortError';
                        throw abortErr;
                    }

                    resultEl.textContent = progressText();

                    const payload = {
                        ...credentials(),
                        table,
                        offset,
                        chunk_size: MIGRATE_CHUNK_SIZE,
                        truncate: isFirstChunk && truncateFirst,
                    };

                    const chunk = await postJson(routes.migrateChunk, payload, runState.controller.signal);

                    migrated += chunk.migrated;
                    offset = chunk.next_offset;
                    done = chunk.done;
                    isFirstChunk = false;
                }

                return { ok: true, text: `${migrated.toLocaleString('id-ID')} baris` };
            } catch (err) {
                // migrated tetap kepegang di sini — user tahu persis sampai baris berapa
                // sebelum gagal/dihentikan, baik karena error maupun klik Stop Proses.
                const stopped = err.name === 'AbortError';

                return {
                    ok: false,
                    stopped,
                    text: totalRows ? `${migrated.toLocaleString('id-ID')} / ${totalRows.toLocaleString('id-ID')} baris` : `${migrated.toLocaleString('id-ID')} baris`,
                    message: stopped ? undefined : err.message,
                };
            }
        }, runState);

        const totalDuration = formatDuration((performance.now() - batchStart) / 1000);
        $('migrateSummary').textContent = `${success} tabel berhasil${failed ? `, ${failed} gagal` : ''}${cancelled ? `, ${cancelled} dibatalkan` : ''}. Total durasi: ${totalDuration}.`;

        if (failed > 0) {
            runTestConnection(true).catch(() => {});
        }
    } catch (err) {
        showAlert('migrateAlertBox', err.message, 'error');
    } finally {
        stopElapsedTimer(elapsedTimerId, 'migrateElapsed');
        stopBtn.removeEventListener('click', onStop);
        stopBtn.style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Mulai Migrasi';
    }
});
</script>
</body>
</html>
