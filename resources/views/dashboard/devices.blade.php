@extends('voyager::master')

@section('page_title', 'Gestion des appareils')

@section('content')
    <div class="page-content">
        <div class="analytics-container">

            {{-- ============================================================ --}}
            {{--  Bandeau résumé : counters tổng quan                          --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-body" style="padding: 15px;">
                            <div id="vdsSummary" class="vds-summary">
                                <span class="text-muted">Chargement…</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  CARD 1 : Liste des appareils                                 --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"
                            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <h3 class="panel-title">Appareils</h3>

                            <div class="vds-filters"
                                style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-right: 15px;">
                                <input type="text" id="vdsSearch" class="form-control input-sm" style="width: 240px;"
                                    placeholder="Rechercher par série ou utilisateur…">
                                <button type="button" id="vdsRefresh" class="btn btn-sm btn-default">
                                    Actualiser
                                </button>
                                <button type="button" id="vdsAddBtn" class="btn btn-sm btn-info">
                                    + Nouvel appareil
                                </button>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div class="vds-table-wrap">
                                <table class="table table-bordered table-hover vds-table">
                                    <thead>
                                        <tr>
                                            <th>N° de série</th>
                                            <th>Utilisateur</th>
                                            <th>Statut</th>
                                            <th>Vu pour la dernière fois</th>
                                            <th>Dernier appel</th>
                                            <th>Agent</th>
                                            <th>Actif</th>
                                            <th style="text-align:center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vdsBody"></tbody>
                                </table>
                            </div>

                            <div class="vds-pagination"
                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <span id="vdsPageInfo" class="text-muted"></span>
                                <div class="btn-group">
                                    <button type="button" id="vdsPrev" class="btn btn-sm btn-default">
                                        &laquo; Précédent
                                    </button>
                                    <button type="button" id="vdsNext" class="btn btn-sm btn-default">
                                        Suivant &raquo;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  CARD 2 : Alertes récentes                                    --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"
                            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <h3 class="panel-title">
                                Alertes récentes
                                <small class="text-muted">— problèmes détectés par les agents Mac</small>
                            </h3>
                            <button type="button" id="vdsAlertClearAll" class="btn btn-sm btn-danger"
                                style="margin-right:15px;">
                                Tout supprimer
                            </button>
                        </div>
                        <div class="panel-body">
                            <div class="vds-table-wrap">
                                <table class="table table-bordered table-hover vds-table">
                                    <thead>
                                        <tr>
                                            <th>Détectée le</th>
                                            <th>Appareil</th>
                                            <th>Utilisateur</th>
                                            <th>Type</th>
                                            <th>Message</th>
                                            <th style="text-align:center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vdsAlertBody"></tbody>
                                </table>
                            </div>

                            <div class="vds-pagination"
                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <span id="vdsAlertPageInfo" class="text-muted"></span>
                                <div class="btn-group">
                                    <button type="button" id="vdsAlertPrev" class="btn btn-sm btn-default">
                                        &laquo; Précédent
                                    </button>
                                    <button type="button" id="vdsAlertNext" class="btn btn-sm btn-default">
                                        Suivant &raquo;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ---------- Modal : créer / éditer un appareil ---------- --}}
    <div id="vdsModalOverlay" class="vds-modal-overlay">
        <div class="vds-modal">
            <div class="vds-modal-header">
                <h4 class="vds-modal-title" id="vdsModalTitle">Nouvel appareil</h4>
                <button type="button" class="vds-modal-close" id="vdsModalClose">&times;</button>
            </div>
            <div class="vds-modal-body">
                <div class="form-group">
                    <label for="vdsModalSerial">Numéro de série <span style="color:#d9534f;">*</span></label>
                    <input type="text" id="vdsModalSerial" class="form-control" maxlength="255"
                        placeholder="Ex: FVFDRLGAQ05D">
                    <p class="help-block" style="margin-top:4px;">
                        Numéro de série du Mac. Souvent rempli automatiquement par l'agent ; ne le modifier qu'avec précaution.
                    </p>
                </div>
                <div class="form-group">
                    <label for="vdsModalUser">Utilisateur assigné</label>
                    <select id="vdsModalUser" class="form-control">
                        <option value="">— Non assigné —</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    <p class="help-block" style="margin-top:4px;">
                        Seuls les comptes ayant un rôle interne sont listés.
                    </p>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="vdsModalActive" checked> Actif
                    </label>
                </div>
            </div>
            <div class="vds-modal-footer">
                <button type="button" class="btn btn-default" id="vdsModalCancel">Annuler</button>
                <button type="button" class="btn btn-info" id="vdsModalSave">Enregistrer</button>
            </div>
        </div>
    </div>

    <style>
        /* Bandeau résumé : 5 ô màu */
        .vds-summary {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: stretch;
        }
        .vds-summary .vds-card {
            flex: 1 1 140px; padding: 10px 14px; border-radius: 4px;
            border: 1px solid #ddd; background: #fafafa;
            display: flex; flex-direction: column; gap: 4px;
        }
        .vds-summary .vds-card-value { font-size: 24px; font-weight: 600; line-height: 1; }
        .vds-summary .vds-card-label { font-size: 12px; color: #555; }
        .vds-summary .vds-card.online        { border-color: #5cb85c; background: #f4faf4; }
        .vds-summary .vds-card.no_permission { border-color: #f0ad4e; background: #fdf8ee; }
        .vds-summary .vds-card.offline       { border-color: #999; }
        .vds-summary .vds-card.never         { border-color: #d9534f; background: #fdf4f4; }

        /* Tables (cùng style với dashboard chính nhưng prefix riêng) */
        .vds-table-wrap { overflow-x: auto; width: 100%; }
        .vds-table { margin-bottom: 0; min-width: 880px; }
        .vds-serial { font-family: Menlo, Monaco, Consolas, monospace; font-size: 12px; }
        .vds-muted-row td { background-color: #fafafa; color: #777; }
        .vds-detail { font-size: 11px; color: #b94a48; margin-top: 3px; }

        /* Modal custom */
        .vds-modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 1050;
            background: rgba(0,0,0,.5);
        }
        .vds-modal-overlay.open { display: flex; align-items: flex-start; justify-content: center; }
        .vds-modal {
            background: #fff; border-radius: 4px; width: 100%; max-width: 500px;
            margin-top: 8vh; box-shadow: 0 5px 20px rgba(0,0,0,.3);
        }
        .vds-modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 18px; border-bottom: 1px solid #eee;
        }
        .vds-modal-title { margin: 0; font-size: 16px; }
        .vds-modal-close {
            background: none; border: none; font-size: 24px; line-height: 1;
            color: #999; cursor: pointer;
        }
        .vds-modal-body { padding: 18px; }
        .vds-modal-footer {
            padding: 12px 18px; border-top: 1px solid #eee;
            display: flex; justify-content: flex-end; gap: 8px;
        }
    </style>
@stop

@section('javascript')
    <script>
        const VDS = {
            devicesDataUrl:     @json($devicesDataUrl),
            alertsDataUrl:      @json($alertsDataUrl),
            alertsClearUrl:     @json($alertsClearUrl),
            alertDeleteUrlTpl:  @json($alertDeleteUrlTpl),
            deviceStoreUrl:     @json($deviceStoreUrl),
            deviceUpdateUrlTpl: @json($deviceUpdateUrlTpl),
            deviceDeleteUrlTpl: @json($deviceDeleteUrlTpl),
            csrf:               @json($csrfToken),
            offlineAfter:       @json($offlineAfter),
        };

        // ---------- Helpers ----------
        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }

        function formatDateTime(dt) {
            if (!dt) return '—';
            const d = new Date(String(dt).replace(' ', 'T'));
            return isNaN(d) ? escapeHtml(dt) : d.toLocaleString('fr-FR');
        }

        // Khoảng cách thời gian "humain" (vd "il y a 3 min", "il y a 2h")
        function timeAgo(dt) {
            if (!dt) return '—';
            const d = new Date(String(dt).replace(' ', 'T'));
            if (isNaN(d)) return escapeHtml(dt);
            const diff = Math.floor((Date.now() - d.getTime()) / 1000);
            if (diff < 60)    return `il y a ${diff} s`;
            if (diff < 3600)  return `il y a ${Math.floor(diff / 60)} min`;
            if (diff < 86400) return `il y a ${Math.floor(diff / 3600)} h`;
            return `il y a ${Math.floor(diff / 86400)} j`;
        }

        function statusBadge(s) {
            const map = {
                online:        ['label-success', 'En ligne'],
                offline:       ['label-default', 'Hors ligne'],
                no_permission: ['label-warning', 'Accès refusé'],
                never:         ['label-danger',  'Jamais vu'],
            };
            const [cls, label] = map[s] || ['label-default', s || '—'];
            return `<span class="label ${cls}">${escapeHtml(label)}</span>`;
        }

        function activeBadge(active) {
            return active
                ? '<span class="label label-success">Actif</span>'
                : '<span class="label label-default">Inactif</span>';
        }

        function alertTypeBadge(t) {
            // Mặc định danger; biết kiểu nào safe thì có thể map sau.
            const map = {
                permission_denied: ['label-danger', 'Accès refusé'],
            };
            const [cls, label] = map[t] || ['label-warning', t || '—'];
            return `<span class="label ${cls}">${escapeHtml(label)}</span>`;
        }

        // ===================================================================
        //  Devices table
        // ===================================================================
        const tbody    = document.getElementById('vdsBody');
        const summary  = document.getElementById('vdsSummary');
        const pageInfo = document.getElementById('vdsPageInfo');
        const prevBtn  = document.getElementById('vdsPrev');
        const nextBtn  = document.getElementById('vdsNext');
        const searchEl = document.getElementById('vdsSearch');

        let currentPage = 1, lastPage = 1;

        function loadDevices(page) {
            currentPage = page || 1;
            const params = new URLSearchParams();
            params.set('page', currentPage);
            if (searchEl.value) params.set('search', searchEl.value);

            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Chargement…</td></tr>';

            fetch(VDS.devicesDataUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(renderDevices)
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML =
                        '<tr><td colspan="8" class="text-center text-danger">Erreur de chargement</td></tr>';
                });
        }

        function renderSummary(c) {
            if (!c) { summary.innerHTML = ''; return; }
            summary.innerHTML = `
                <div class="vds-card">
                    <span class="vds-card-value">${c.total}</span>
                    <span class="vds-card-label">Appareils au total</span>
                </div>
                <div class="vds-card online">
                    <span class="vds-card-value">${c.online}</span>
                    <span class="vds-card-label">En ligne</span>
                </div>
                <div class="vds-card no_permission">
                    <span class="vds-card-value">${c.no_permission}</span>
                    <span class="vds-card-label">Accès refusé (FDA)</span>
                </div>
                <div class="vds-card offline">
                    <span class="vds-card-value">${c.offline}</span>
                    <span class="vds-card-label">Hors ligne (&gt; ${VDS.offlineAfter} min)</span>
                </div>
                <div class="vds-card never">
                    <span class="vds-card-value">${c.never}</span>
                    <span class="vds-card-label">Jamais vu</span>
                </div>
            `;
        }

        function renderDevices(payload) {
            const rows = payload.data || [];
            const meta = payload.meta || {};
            lastPage    = meta.last_page || 1;
            currentPage = meta.current_page || 1;

            renderSummary(payload.counts);

            if (rows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="8" class="text-center text-muted">Aucun appareil</td></tr>';
            } else {
                tbody.innerHTML = rows.map(d => {
                    const rowClass = d.active_flg ? '' : 'vds-muted-row';
                    const user = d.user_name
                        ? escapeHtml(d.user_name)
                        : '<span class="text-muted">— Non assigné</span>';
                    const lastSeen = d.last_seen_at
                        ? `${timeAgo(d.last_seen_at)}<br><small class="text-muted">${formatDateTime(d.last_seen_at)}</small>`
                        : '<span class="text-muted">—</span>';
                    const lastCall = d.last_call_at
                        ? formatDateTime(d.last_call_at)
                        : '<span class="text-muted">—</span>';
                    const detail = (d.status === 'no_permission' && d.last_detail)
                        ? `<div class="vds-detail" title="${escapeHtml(d.last_detail)}">${escapeHtml(d.last_detail.substring(0, 80))}${d.last_detail.length > 80 ? '…' : ''}</div>`
                        : '';

                    return `<tr class="${rowClass}">
                        <td><span class="vds-serial">${escapeHtml(d.device_serial)}</span></td>
                        <td>${user}</td>
                        <td>${statusBadge(d.status)}${detail}</td>
                        <td>${lastSeen}</td>
                        <td>${lastCall}</td>
                        <td>${escapeHtml(d.agent_version || '—')}</td>
                        <td>${activeBadge(d.active_flg)}</td>
                        <td style="text-align:center; white-space:nowrap;">
                            <button type="button" class="btn btn-xs btn-primary vds-edit"
                                data-id="${d.id}"
                                data-serial="${escapeHtml(d.device_serial)}"
                                data-user-id="${d.user_id ?? ''}"
                                data-active="${d.active_flg ? '1' : '0'}">
                                Modifier
                            </button>
                            <button type="button" class="btn btn-xs btn-danger vds-delete"
                                data-id="${d.id}"
                                data-serial="${escapeHtml(d.device_serial)}">
                                Supprimer
                            </button>
                        </td>
                    </tr>`;
                }).join('');
            }

            pageInfo.textContent = `Page ${currentPage} / ${lastPage} — ${meta.total || 0} appareil(s)`;
            prevBtn.disabled = (currentPage <= 1);
            nextBtn.disabled = (currentPage >= lastPage);
        }

        prevBtn.addEventListener('click', () => { if (currentPage > 1) loadDevices(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < lastPage) loadDevices(currentPage + 1); });
        document.getElementById('vdsRefresh').addEventListener('click', () => {
            loadDevices(currentPage);
            loadAlerts(alertPage);
        });
        // Debounce nhẹ cho search
        let searchTimer = null;
        searchEl.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadDevices(1), 300);
        });

        // ===================================================================
        //  Alerts table
        // ===================================================================
        const alertBody     = document.getElementById('vdsAlertBody');
        const alertPageInfo = document.getElementById('vdsAlertPageInfo');
        const alertPrev     = document.getElementById('vdsAlertPrev');
        const alertNext     = document.getElementById('vdsAlertNext');
        let alertPage = 1, alertLastPage = 1;

        function loadAlerts(page) {
            alertPage = page || 1;
            const params = new URLSearchParams({ page: alertPage });
            alertBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Chargement…</td></tr>';

            fetch(VDS.alertsDataUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(renderAlerts)
                .catch(err => {
                    console.error(err);
                    alertBody.innerHTML =
                        '<tr><td colspan="6" class="text-center text-danger">Erreur de chargement</td></tr>';
                });
        }

        function renderAlerts(payload) {
            const rows = payload.data || [];
            const meta = payload.meta || {};
            alertLastPage = meta.last_page || 1;
            alertPage     = meta.current_page || 1;

            if (rows.length === 0) {
                alertBody.innerHTML =
                    '<tr><td colspan="6" class="text-center text-muted">Aucune alerte</td></tr>';
            } else {
                alertBody.innerHTML = rows.map(a => {
                    const user = a.user_name
                        ? escapeHtml(a.user_name)
                        : '<span class="text-muted">— Non assigné</span>';
                    return `<tr>
                        <td>
                            ${timeAgo(a.detected_at)}<br>
                            <small class="text-muted">${formatDateTime(a.detected_at)}</small>
                        </td>
                        <td><span class="vds-serial">${escapeHtml(a.device_serial)}</span></td>
                        <td>${user}</td>
                        <td>${alertTypeBadge(a.alert_type)}</td>
                        <td>${escapeHtml(a.message || '—')}</td>
                        <td style="text-align:center;">
                            <button type="button" class="btn btn-xs btn-danger vds-alert-delete"
                                data-id="${a.id}">
                                Supprimer
                            </button>
                        </td>
                    </tr>`;
                }).join('');
            }

            alertPageInfo.textContent = `Page ${alertPage} / ${alertLastPage} — ${meta.total || 0} alerte(s)`;
            alertPrev.disabled = (alertPage <= 1);
            alertNext.disabled = (alertPage >= alertLastPage);
        }

        alertPrev.addEventListener('click', () => { if (alertPage > 1) loadAlerts(alertPage - 1); });
        alertNext.addEventListener('click', () => { if (alertPage < alertLastPage) loadAlerts(alertPage + 1); });

        // Xoá MỘT alert (event delegation vì hàng render động).
        alertBody.addEventListener('click', (e) => {
            const btn = e.target.closest('.vds-alert-delete');
            if (!btn) return;
            if (!confirm('Supprimer cette alerte ?')) return;

            const url = VDS.alertDeleteUrlTpl.replace('__ID__', btn.dataset.id);
            fetch(url, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': VDS.csrf },
                    credentials: 'same-origin',
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); })
                .then(() => loadAlerts(alertPage))
                .catch(err => alert('Erreur: ' + err.message));
        });

        // Xoá TẤT CẢ alerts — confirm rõ ràng vì không undo được.
        document.getElementById('vdsAlertClearAll').addEventListener('click', () => {
            if (!confirm('Supprimer TOUTES les alertes ?\n\nCette action est irréversible. Les alertes actuellement affichées et toutes les autres seront effacées.')) {
                return;
            }
            fetch(VDS.alertsClearUrl, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': VDS.csrf },
                    credentials: 'same-origin',
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok) throw new Error(j.message || 'Erreur');
                    loadAlerts(1);
                })
                .catch(err => alert('Erreur: ' + err.message));
        });

        // ===================================================================
        //  Modal créer / éditer
        // ===================================================================
        const overlay     = document.getElementById('vdsModalOverlay');
        const modalTitle  = document.getElementById('vdsModalTitle');
        const modalSerial = document.getElementById('vdsModalSerial');
        const modalUser   = document.getElementById('vdsModalUser');
        const modalActive = document.getElementById('vdsModalActive');
        const saveBtn     = document.getElementById('vdsModalSave');
        let editingId = null;

        function openCreate() {
            editingId = null;
            modalTitle.textContent = 'Nouvel appareil';
            modalSerial.value = '';
            modalSerial.readOnly = false;
            modalUser.value   = '';
            modalActive.checked = true;
            overlay.classList.add('open');
        }

        function openEdit(id, serial, userId, active) {
            editingId = id;
            modalTitle.textContent = 'Modifier l\'appareil';
            modalSerial.value = serial || '';
            // Serial là định danh vật lý do Mac gửi -> không khuyến khích đổi;
            // nhưng vẫn cho phép sửa trong trường hợp lỗi nhập tay.
            modalSerial.readOnly = false;
            modalUser.value   = userId || '';
            modalActive.checked = active === '1' || active === true;
            overlay.classList.add('open');
        }

        function closeModal() {
            overlay.classList.remove('open');
            editingId = null;
        }

        document.getElementById('vdsAddBtn').addEventListener('click', openCreate);
        document.getElementById('vdsModalClose').addEventListener('click', closeModal);
        document.getElementById('vdsModalCancel').addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

        // Delegation: Modifier + Supprimer trên bảng devices.
        tbody.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.vds-edit');
            if (editBtn) {
                openEdit(
                    editBtn.dataset.id,
                    editBtn.dataset.serial,
                    editBtn.dataset.userId,
                    editBtn.dataset.active
                );
                return;
            }
            const delBtn = e.target.closest('.vds-delete');
            if (delBtn) {
                if (!confirm(`Supprimer l'appareil "${delBtn.dataset.serial}" ?\n\nLes appels et alertes liés ne sont pas effacés, mais l'appareil sera désassigné. Si l'agent Mac est encore actif, l'appareil réapparaîtra automatiquement comme « Non assigné ».`)) {
                    return;
                }
                const url = VDS.deviceDeleteUrlTpl.replace('__ID__', delBtn.dataset.id);
                fetch(url, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': VDS.csrf },
                        credentials: 'same-origin',
                    })
                    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); })
                    .then(() => loadDevices(currentPage))
                    .catch(err => alert('Erreur: ' + err.message));
            }
        });

        saveBtn.addEventListener('click', () => {
            const serial = modalSerial.value.trim();
            if (!serial) { alert('Le numéro de série est requis.'); return; }

            const payload = {
                device_serial: serial,
                user_id: modalUser.value ? parseInt(modalUser.value, 10) : null,
                active_flg: modalActive.checked,
            };

            const url = editingId
                ? VDS.deviceUpdateUrlTpl.replace('__ID__', editingId)
                : VDS.deviceStoreUrl;

            saveBtn.disabled = true;
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': VDS.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok) throw new Error(j.message || 'Erreur lors de l\'enregistrement');
                    closeModal();
                    loadDevices(currentPage);
                })
                .catch(err => alert(err.message))
                .finally(() => { saveBtn.disabled = false; });
        });

        // ---------- Chargement initial ----------
        loadDevices(1);
        loadAlerts(1);

        // (alerts ít đổi hơn nên không cần auto-refresh).
        setInterval(() => loadDevices(currentPage), 300000);
    </script>
@stop