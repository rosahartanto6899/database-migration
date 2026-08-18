<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DB Migration Tool — SQL Server → PostgreSQL</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --bg: #f5f6f8;
            --card: #ffffff;
            --border: #e2e5ea;
            --text: #1b1f24;
            --text-muted: #6b7280;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --ok: #16a34a;
            --ok-bg: #ecfdf3;
            --err: #dc2626;
            --err-bg: #fef2f2;
            --warn: #b45309;
            --warn-bg: #fffbeb;
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 32px 20px 80px; }
        h1 { font-size: 1.4rem; margin: 0 0 4px; }
        .subtitle { color: var(--text-muted); margin: 0 0 28px; font-size: 0.92rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
        }
        .card h2 {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            margin: 0 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .badge {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
            text-transform: none;
            letter-spacing: 0;
        }
        .badge.sqlserver { background: #fef3e2; color: #b45309; }
        .badge.postgres { background: #e0f2fe; color: #0369a1; }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 12px 0 4px;
            color: var(--text);
        }
        label:first-of-type { margin-top: 0; }
        input[type=text], input[type=number], input[type=password] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.88rem;
            background: #fff;
            color: var(--text);
        }
        input:focus { outline: 2px solid var(--primary); outline-offset: -1px; }
        .row-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 10px; }
        .actions { display: flex; align-items: center; gap: 12px; margin: 20px 0; flex-wrap: wrap; }
        button {
            font-family: inherit;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 9px 18px;
            border-radius: 7px;
            border: 1px solid transparent;
            cursor: pointer;
        }
        button.primary { background: var(--primary); color: #fff; }
        button.primary:hover:not(:disabled) { background: var(--primary-hover); }
        button.secondary { background: #fff; color: var(--text); border-color: var(--border); }
        button.secondary:hover:not(:disabled) { background: #f9fafb; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: #d1d5db; }
        .dot.ok { background: var(--ok); }
        .dot.err { background: var(--err); }
        .conn-status { display: flex; gap: 20px; margin-top: 10px; font-size: 0.82rem; flex-wrap: wrap; }
        .conn-status .item { display: flex; align-items: center; gap: 6px; }
        .conn-status .msg { color: var(--err); }

        .tabs {
            display: flex;
            gap: 6px;
            margin: 28px 0 0;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
        }
        .tab-btn {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            white-space: nowrap;
        }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab-btn:hover:not(.active) { color: var(--text); }

        .tab-panel { display: none; padding-top: 20px; }
        .tab-panel.active { display: block; }

        section.panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
        }
        .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .panel-head h2 { margin: 0; font-size: 1rem; }
        .panel-desc { font-size: 0.85rem; color: var(--text-muted); margin: 0 0 16px; }

        .table-list { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .table-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 0.88rem;
        }
        .table-row:last-child { border-bottom: none; }
        .table-row .name { font-weight: 500; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .table-row .count { color: var(--text-muted); font-size: 0.8rem; }
        .table-row .result { font-size: 0.78rem; font-weight: 600; min-width: 130px; text-align: right; }
        .result.pending { color: var(--text-muted); }
        .result.success { color: var(--ok); }
        .result.error { color: var(--err); }
        .result.skip { color: var(--warn); }

        .toolbar { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; font-size: 0.85rem; flex-wrap: wrap; }
        .toolbar label { margin: 0; display: flex; align-items: center; gap: 6px; font-weight: 400; }
        .table-count-total { color: var(--text-muted); font-weight: 400; }
        .empty-hint { color: var(--text-muted); font-size: 0.85rem; padding: 16px; text-align: center; }
        .alert { padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 14px; }
        .alert.error { background: var(--err-bg); color: var(--err); }
        .alert.info { background: #eff6ff; color: #1d4ed8; }
        .alert.warn { background: var(--warn-bg); color: var(--warn); }
        .alert.ok { background: var(--ok-bg); color: var(--ok); }
        pre.sql {
            background: #0b1220;
            color: #d6e0ff;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 0.76rem;
            overflow-x: auto;
            margin: 6px 0 0;
            white-space: pre-wrap;
        }
        details.sql-toggle summary { cursor: pointer; font-size: 0.76rem; color: var(--primary); }
        .proc-list { list-style: none; margin: 8px 0 0; padding: 0; font-size: 0.85rem; }
        .proc-list li { padding: 4px 0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 18, 25, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity .15s ease;
        }
        .modal-overlay.visible { opacity: 1; pointer-events: auto; }
        .modal-box {
            background: var(--card);
            border-radius: 14px;
            max-width: 440px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            transform: translateY(8px) scale(.98);
            transition: transform .15s ease;
        }
        .modal-overlay.visible .modal-box { transform: translateY(0) scale(1); }
        .modal-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--err-bg);
            color: var(--err);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 14px;
        }
        .modal-box h3 { margin: 0 0 8px; font-size: 1.05rem; }
        .modal-box p { margin: 0 0 4px; font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; }
        .modal-table-list {
            list-style: none;
            margin: 12px 0;
            padding: 10px 12px;
            background: var(--bg);
            border-radius: 8px;
            max-height: 160px;
            overflow-y: auto;
            font-size: 0.84rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .modal-table-list li { padding: 3px 0; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        button.danger { background: var(--err); color: #fff; }
        button.danger:hover:not(:disabled) { background: #b91c1c; }
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
<div class="wrap">
    <h1>Database Migration Tool</h1>
    <p class="subtitle">SQL Server (source) → PostgreSQL (target). Kredensial hanya dipakai per-request, tidak disimpan.</p>

    <div class="grid">
        <div class="card">
            <h2>Source <span class="badge sqlserver">SQL Server</span></h2>
            <label for="src_host">Host</label>
            <input type="text" id="src_host" value="{{ $defaults['source']['host'] }}" placeholder="mydb.database.windows.net">

            <div class="row-2">
                <div>
                    <label for="src_database">Database</label>
                    <input type="text" id="src_database" value="{{ $defaults['source']['database'] }}">
                </div>
                <div>
                    <label for="src_port">Port</label>
                    <input type="number" id="src_port" value="{{ $defaults['source']['port'] }}">
                </div>
            </div>

            <label for="src_schema">Schema</label>
            <input type="text" id="src_schema" value="{{ $defaults['source']['schema'] }}">

            <label for="src_username">Username</label>
            <input type="text" id="src_username" value="{{ $defaults['source']['username'] }}">

            <label for="src_password">Password</label>
            <input type="password" id="src_password" value="{{ $defaults['source']['password'] }}" placeholder="••••••••">
        </div>

        <div class="card">
            <h2>Target <span class="badge postgres">PostgreSQL</span></h2>
            <label for="tgt_host">Host</label>
            <input type="text" id="tgt_host" value="{{ $defaults['target']['host'] }}" placeholder="localhost">

            <div class="row-2">
                <div>
                    <label for="tgt_database">Database</label>
                    <input type="text" id="tgt_database" value="{{ $defaults['target']['database'] }}">
                </div>
                <div>
                    <label for="tgt_port">Port</label>
                    <input type="number" id="tgt_port" value="{{ $defaults['target']['port'] }}">
                </div>
            </div>

            <label for="tgt_schema">Schema</label>
            <input type="text" id="tgt_schema" value="{{ $defaults['target']['schema'] }}">

            <label for="tgt_username">Username</label>
            <input type="text" id="tgt_username" value="{{ $defaults['target']['username'] }}">

            <label for="tgt_password">Password</label>
            <input type="password" id="tgt_password" value="{{ $defaults['target']['password'] }}" placeholder="••••••••">
        </div>
    </div>

    <div class="tabs" id="tabs">
        <button type="button" class="tab-btn active" data-tab="test">1. Test Connection</button>
        <button type="button" class="tab-btn" data-tab="create-db">2. Create Database</button>
        <button type="button" class="tab-btn" data-tab="create-schema">3. Create Schema</button>
        <button type="button" class="tab-btn" data-tab="truncate">4. Truncate Table</button>
        <button type="button" class="tab-btn" data-tab="migrate">5. Database Migration</button>
    </div>

    {{-- Tab 1: Test Connection --}}
    <div class="tab-panel active" data-panel="test">
        <section class="panel">
            <div class="panel-head"><h2>Test Connection</h2></div>
            <p class="panel-desc">Menguji koneksi ke source & target sekaligus, dan memuat daftar tabel di schema source (dipakai tab Create Schema & Database Migration).</p>
            <div class="actions" style="margin-top:0;">
                <button type="button" class="primary" id="btnTest">Test Connection</button>
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
                <button type="button" class="primary" id="btnCreateDb">Buat Database</button>
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
            <p class="panel-desc">Membuat schema + tabel di target berdasarkan struktur kolom tabel source (tipe data diterjemahkan otomatis, kolom IDENTITY & primary key ikut dibuat). Bersifat aman/idempotent — pakai <code>CREATE TABLE IF NOT EXISTS</code>, tidak akan menghapus tabel/data yang sudah ada. Default kolom berupa ekspresi T-SQL (mis. GETDATE()) dilewati, perlu ditambahkan manual.</p>
            <div id="schemaAlertBox"></div>
            <div class="toolbar">
                <label><input type="checkbox" id="chkAllSchema"> Pilih semua <span id="totalCountSchema" class="table-count-total"></span></label>
            </div>
            <div class="table-list" id="tableListSchema">
                <div class="empty-hint">Klik "Muat Daftar Tabel Source" dulu.</div>
            </div>
            <div class="actions">
                <button type="button" class="primary" id="btnCreateSchema" disabled>Buat Schema &amp; Tabel</button>
                <button type="button" class="danger" id="btnStopSchema" style="display:none;">Stop Proses</button>
                <span id="schemaSummary" style="font-size:0.85rem;color:var(--text-muted);"></span>
            </div>
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
                <button type="button" class="primary" id="btnTruncate" disabled>Truncate Tabel Terpilih</button>
                <button type="button" class="danger" id="btnStopTruncate" style="display:none;">Stop Proses</button>
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
            <p class="panel-desc">Menyalin data dari tabel source terpilih ke tabel target dengan nama yang sama (tabel tujuan harus sudah ada — lihat tab Create Schema).</p>
            <div id="migrateAlertBox"></div>
            <div class="toolbar">
                <label><input type="checkbox" id="chkAllMigrate"> Pilih semua <span id="totalCountMigrate" class="table-count-total"></span></label>
                <label><input type="checkbox" id="chkTruncate" checked> Kosongkan tabel tujuan sebelum migrasi</label>
            </div>
            <div class="table-list" id="tableListMigrate">
                <div class="empty-hint">Klik "Muat Daftar Tabel Source" dulu.</div>
            </div>
            <div class="actions">
                <button type="button" class="primary" id="btnMigrate" disabled>Mulai Migrasi</button>
                <button type="button" class="danger" id="btnStopMigrate" style="display:none;">Stop Proses</button>
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

const routes = {
    testConnection: '{{ route('migration.test-connection') }}',
    createDatabase: '{{ route('migration.create-database') }}',
    createSchema: '{{ route('migration.create-schema') }}',
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
function confirmModal({ title = 'Konfirmasi', message = '', items = [] }) {
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

async function runTestConnection() {
    showAlert('testAlertBox', '');
    $('connStatus').innerHTML = '';

    const btn = $('btnTest');
    btn.disabled = true;
    btn.textContent = 'Menguji koneksi…';

    try {
        const result = await postJson(routes.testConnection, credentials());
        renderConnStatus(result);

        if (result.source.ok) {
            state.sourceTables = result.tables;
        }

        if (!result.source.ok || !result.target.ok) {
            showAlert('testAlertBox', 'Salah satu atau kedua koneksi gagal. Periksa kredensial di atas.', 'error');
        }

        return result;
    } catch (err) {
        showAlert('testAlertBox', err.message, 'error');
        throw err;
    } finally {
        btn.disabled = false;
        btn.textContent = 'Test Connection';
    }
}

$('btnTest').addEventListener('click', runTestConnection);

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
function renderCheckList(containerId, chkClass, tables, extraColumnFn, countElId) {
    const list = $(containerId);

    if (countElId && $(countElId)) {
        $(countElId).textContent = `(${tables.length} tabel)`;
    }

    if (!tables.length) {
        list.innerHTML = '<div class="empty-hint">Tidak ada tabel ditemukan.</div>';
        return;
    }

    list.innerHTML = tables.map(t => `
        <div class="table-row" data-table="${t.name}">
            <input type="checkbox" class="${chkClass}" value="${t.name}">
            <span class="name">${t.name}</span>
            <span class="count">${extraColumnFn ? extraColumnFn(t) : ''}</span>
            <span class="result pending">—</span>
        </div>
    `).join('');
}

function bindSelectAll(chkAllId, chkClass, onChange) {
    $(chkAllId).addEventListener('change', (e) => {
        document.querySelectorAll('.' + chkClass).forEach(cb => cb.checked = e.target.checked);
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
            resultEl.title = outcome.message || '';
            failed++;
        }
    }

    return { success, failed, cancelled };
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
    $('schemaSummary').textContent = '';

    let procedures = [];
    const runState = createRunState();
    runState.controller = new AbortController();
    const onStop = () => stopRun(runState);
    stopBtn.addEventListener('click', onStop);

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

        $('schemaSummary').textContent = `${success} tabel berhasil${failed ? `, ${failed} gagal` : ''}${cancelled ? `, ${cancelled} dibatalkan` : ''}.`;

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

        $('truncateSummary').textContent = `${success} tabel berhasil dikosongkan${failed ? `, ${failed} gagal` : ''}${cancelled ? `, ${cancelled} dibatalkan` : ''}.`;
    } catch (err) {
        showAlert('truncateAlertBox', err.message, 'error');
    } finally {
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
        renderCheckList('tableListMigrate', 'chk-migrate', state.sourceTables || [], t => `${t.row_count.toLocaleString('id-ID')} baris`, 'totalCountMigrate');
        bindRowCheckboxes('tableListMigrate', 'chk-migrate', updateMigrateButtonState);
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

$('btnLoadTablesMigrate').addEventListener('click', loadTablesForMigrate);
bindSelectAll('chkAllMigrate', 'chk-migrate', updateMigrateButtonState);

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

        $('migrateSummary').textContent = `${success} tabel berhasil${failed ? `, ${failed} gagal` : ''}${cancelled ? `, ${cancelled} dibatalkan` : ''}.`;
    } catch (err) {
        showAlert('migrateAlertBox', err.message, 'error');
    } finally {
        stopBtn.removeEventListener('click', onStop);
        stopBtn.style.display = 'none';
        btn.disabled = false;
        btn.textContent = 'Mulai Migrasi';
    }
});
</script>
</body>
</html>
