@extends('voyager::master')

@section('page_title', 'Suivi Télévente')

@section('content')
    <div class="page-content">
        <div class="analytics-container">

            {{-- ============================================================ --}}
            {{--  Bandeau résumé : 3 ô lớn, mỗi ô có tổng + breakdown          --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="vts-summary-row">
                        <div class="vts-summary-card">
                            <div class="vts-summary-header">
                                <div class="vts-summary-title">Appels typés</div>
                                <div class="vts-summary-total" id="vtsSumTypedTotal">0</div>
                            </div>
                            <div class="vts-summary-breakdown">
                                <div class="vts-mini info">
                                    <div class="vts-mini-value" id="vtsSumProspect">0</div>
                                    <div class="vts-mini-label">Prospect</div>
                                </div>
                                <div class="vts-mini warning">
                                    <div class="vts-mini-value" id="vtsSumFollowUp">0</div>
                                    <div class="vts-mini-label">Relance</div>
                                </div>
                                <div class="vts-mini default">
                                    <div class="vts-mini-value" id="vtsSumMemberCare">0</div>
                                    <div class="vts-mini-label">Suivi</div>
                                </div>
                            </div>
                        </div>

                        <div class="vts-summary-card">
                            <div class="vts-summary-header">
                                <div class="vts-summary-title">Appels avec résultat</div>
                                <div class="vts-summary-total" id="vtsSumResultTotal">0</div>
                            </div>
                            <div class="vts-summary-breakdown">
                                <div class="vts-mini success">
                                    <div class="vts-mini-value" id="vtsSumResMeeting">0</div>
                                    <div class="vts-mini-label">Rendez-vous</div>
                                </div>
                                <div class="vts-mini warning">
                                    <div class="vts-mini-value" id="vtsSumResRecall">0</div>
                                    <div class="vts-mini-label">Rappel</div>
                                </div>
                                <div class="vts-mini danger">
                                    <div class="vts-mini-value" id="vtsSumResFailed">0</div>
                                    <div class="vts-mini-label">Échec</div>
                                </div>
                            </div>
                        </div>

                        <div class="vts-summary-card">
                            <div class="vts-summary-header">
                                <div class="vts-summary-title">Rendez-vous du jour</div>
                                <div class="vts-summary-total">
                                    <span id="vtsSumMeetDone" class="vts-sum-done">0</span><span class="vts-sum-sep">/</span><span id="vtsSumMeetTotal" class="vts-sum-total">0</span>
                                </div>
                            </div>
                            <div class="vts-summary-breakdown">
                                <div class="vts-mini success">
                                    <div class="vts-mini-value" id="vtsSumMeetSuccess">0</div>
                                    <div class="vts-mini-label">Succès</div>
                                </div>
                                <div class="vts-mini warning">
                                    <div class="vts-mini-value" id="vtsSumMeetPostponed">0</div>
                                    <div class="vts-mini-label">Reporté</div>
                                </div>
                                <div class="vts-mini info">
                                    <div class="vts-mini-value" id="vtsSumMeetUndecided">0</div>
                                    <div class="vts-mini-label">Indécis</div>
                                </div>
                                <div class="vts-mini danger">
                                    <div class="vts-mini-value" id="vtsSumMeetFailed">0</div>
                                    <div class="vts-mini-label">Échec</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  CARD 1 : Historique des appels                              --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-12 col-lg-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"
                            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <h3 class="panel-title">
                                Historique des appels <small id="vtsCallsTitleDate" class="text-muted"></small>
                            </h3>

                            <div class="vts-filters"
                                style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-right: 15px;">

                                {{-- Filter user : admin chọn được; nhân viên thường không hiển thị --}}
                                @if ($isAdmin)
                                    <select id="vtsUser" class="form-control input-sm" style="width: auto;">
                                        <option value="">Tous les employés</option>
                                        @foreach ($users as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                @endif

                                {{-- Filter date : mặc định hôm nay, CHO PHÉP chọn cả tương lai --}}
                                <input type="date" id="vtsDate" class="form-control input-sm" style="width: auto;"
                                    value="{{ $today }}">

                                <button type="button" id="vtsApply" class="btn btn-sm btn-info">
                                    Appliquer
                                </button>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div class="vts-table-wrap">
                                <table class="table table-bordered table-hover vts-table">
                                    <thead>
                                        <tr>
                                            <th class="vts-sticky-col">Date / Heure</th>
                                            <th>Type</th>
                                            <th>Statut</th>
                                            <th>Téléphone</th>
                                            <th>Durée</th>
                                            <th>Type de client / Résultat</th>
                                            <th style="text-align:center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vtsTableBody"></tbody>
                                </table>
                            </div>

                            <div class="vts-pagination"
                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <span id="vtsPageInfo" class="text-muted"></span>
                                <div class="btn-group">
                                    <button type="button" id="vtsPrev" class="btn btn-sm btn-default">
                                        &laquo; Précédent
                                    </button>
                                    <button type="button" id="vtsNext" class="btn btn-sm btn-default">
                                        Suivant &raquo;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{--  CARD 2 (gauche) : Rappels  |  CARD 3 (droite) : Rendez-vous  --}}
            {{-- ============================================================ --}}
            <div class="row">
                <div class="col-md-6 col-lg-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Rappels à passer <small id="vtsRecallTitleDate" class="text-muted"></small></h3>
                        </div>
                        <div class="panel-body">
                            <div class="vts-table-wrap">
                                <table class="table table-bordered table-hover vts-table" style="min-width:520px;">
                                    <thead>
                                        <tr>
                                            <th>Téléphone</th>
                                            <th>Heure prévue</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vtsRecallBody"></tbody>
                                </table>
                            </div>

                            <div class="vts-pagination"
                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <span id="vtsRecallPageInfo" class="text-muted"></span>
                                <div class="btn-group">
                                    <button type="button" id="vtsRecallPrev" class="btn btn-sm btn-default">
                                        &laquo; Précédent
                                    </button>
                                    <button type="button" id="vtsRecallNext" class="btn btn-sm btn-default">
                                        Suivant &raquo;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-6">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">Rendez-vous <small id="vtsMeetingTitleDate" class="text-muted"></small></h3>
                        </div>
                        <div class="panel-body">
                            <div class="vts-table-wrap">
                                <table class="table table-bordered table-hover vts-table" style="min-width:520px;">
                                    <thead>
                                        <tr>
                                            <th>Téléphone</th>
                                            <th>RDV prévu</th>
                                            <th>Résultat</th>
                                            <th style="text-align:center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vtsMeetingBody"></tbody>
                                </table>
                            </div>

                            <div class="vts-pagination"
                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <span id="vtsMeetingPageInfo" class="text-muted"></span>
                                <div class="btn-group">
                                    <button type="button" id="vtsMeetingPrev" class="btn btn-sm btn-default">
                                        &laquo; Précédent
                                    </button>
                                    <button type="button" id="vtsMeetingNext" class="btn btn-sm btn-default">
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

    {{-- ---------- Modal : définir le type de client ---------- --}}
    <div id="vtsModalOverlay" class="vts-modal-overlay">
        <div class="vts-modal">
            <div class="vts-modal-header">
                <h4 class="vts-modal-title">Définir le type de client</h4>
                <button type="button" class="vts-modal-close" id="vtsModalClose">&times;</button>
            </div>
            <div class="vts-modal-body">
                <div class="form-group">
                    <label for="vtsModalType">Type de client <span style="color:#d9534f;">*</span></label>
                    <select id="vtsModalType" class="form-control">
                        <option value="">— Choisir —</option>
                        <option value="prospect">Prospect</option>
                        <option value="follow-up">Relance</option>
                        <option value="member-care">Suivi membre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="vtsModalResult">Résultat</label>
                    <select id="vtsModalResult" class="form-control">
                        <option value="">— Choisir —</option>
                        <option value="meeting">Rendez-vous</option>
                        <option value="recall">Rappel</option>
                        <option value="failed">Échec</option>
                    </select>
                </div>
                <div class="form-group" id="vtsModalClientGroup" style="display:none;">
                    <label for="vtsModalClient">Nom du client <span style="color:#d9534f;">*</span></label>
                    <input type="text" id="vtsModalClient" class="form-control" maxlength="255" placeholder="Ex: Jean Dupont">
                </div>
                <div class="form-group" id="vtsModalCompanyGroup" style="display:none;">
                    <label for="vtsModalCompany">Société <span class="text-muted">(facultatif)</span></label>
                    <input type="text" id="vtsModalCompany" class="form-control" maxlength="255" placeholder="Ex: Société ABC">
                </div>
                <div class="form-group">
                    <label for="vtsModalFollow">Prochain suivi <span class="text-muted">(facultatif)</span></label>
                    <input type="datetime-local" id="vtsModalFollow" class="form-control">
                    <p class="help-block" style="margin-top:4px;">
                        Laisser vide s'il n'y a pas de relance prévue.
                    </p>
                </div>
            </div>
            <div class="vts-modal-footer">
                <button type="button" class="btn btn-default" id="vtsModalCancel">Annuler</button>
                <button type="button" class="btn btn-info" id="vtsModalSave">Enregistrer</button>
            </div>
        </div>
    </div>

    {{-- ---------- Modal : modifier le rendez-vous ---------- --}}
    <div id="vtsMeetingModalOverlay" class="vts-modal-overlay">
        <div class="vts-modal">
            <div class="vts-modal-header">
                <h4 class="vts-modal-title">Modifier le rendez-vous</h4>
                <button type="button" class="vts-modal-close" id="vtsMeetingModalClose">&times;</button>
            </div>
            <div class="vts-modal-body">
                <div class="form-group">
                    <label for="vtsMeetingModalDate">Date du rendez-vous</label>
                    <input type="datetime-local" id="vtsMeetingModalDate" class="form-control">
                    <p class="help-block" style="margin-top:4px;">
                        Doit être dans le futur. Si modifiée, le calendrier Google sera synchronisé.
                    </p>
                </div>
                <div class="form-group">
                    <label for="vtsMeetingModalResult">Résultat du rendez-vous</label>
                    <select id="vtsMeetingModalResult" class="form-control">
                        <option value="">— Choisir —</option>
                        <option value="success">Succès</option>
                        <option value="postponed">Reporté par le client</option>
                        <option value="undecided">Indécis</option>
                        <option value="failed">Échec</option>
                    </select>
                </div>
            </div>
            <div class="vts-modal-footer">
                <button type="button" class="btn btn-default" id="vtsMeetingModalCancel">Annuler</button>
                <button type="button" class="btn btn-info" id="vtsMeetingModalSave">Enregistrer</button>
            </div>
        </div>
    </div>

    {{-- ---------- Modal : ajouter au répertoire ---------- --}}
    <div id="vtsContactModalOverlay" class="vts-modal-overlay">
        <div class="vts-modal">
            <div class="vts-modal-header">
                <h4 class="vts-modal-title">Ajouter au répertoire</h4>
                <button type="button" class="vts-modal-close" id="vtsContactModalClose">&times;</button>
            </div>
            <div class="vts-modal-body">
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" id="vtsContactModalPhone" class="form-control" readonly
                        style="background-color: #f5f5f5; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label for="vtsContactModalName">Nom du client</label>
                    <input type="text" id="vtsContactModalName" class="form-control" maxlength="255"
                        placeholder="Ex: Jean Dupont">
                </div>
                <div class="form-group">
                    <label for="vtsContactModalCompany">Société</label>
                    <input type="text" id="vtsContactModalCompany" class="form-control" maxlength="255"
                        placeholder="Ex: Société ABC">
                </div>
                <p class="help-block" style="margin-top:-4px;">
                    Au moins l'un des deux champs (nom ou société) doit être renseigné.
                </p>
            </div>
            <div class="vts-modal-footer">
                <button type="button" class="btn btn-default" id="vtsContactModalCancel">Annuler</button>
                <button type="button" class="btn btn-info" id="vtsContactModalSave">Enregistrer</button>
            </div>
        </div>
    </div>

    <style>
        /* Bandeau résumé : 3 ô lớn, mỗi ô có header (title+total cùng hàng) + breakdown */
        .vts-summary-row {
            display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .vts-summary-card {
            flex: 1 1 300px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;
            background: #fff;
        }
        /* Header : label trái, số phải, cùng 1 hàng */
        .vts-summary-header {
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; margin-bottom: 12px;
        }
        .vts-summary-title {
            font-size: 11px; color: #777;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .vts-summary-total {
            font-size: 28px; font-weight: 600; line-height: 1; color: #333;
        }
        /* Card "Rendez-vous du jour" hiển thị A/B với 2 màu phân biệt */
        .vts-sum-done  { color: #5cb85c; }   /* đã có résultat — xanh */
        .vts-sum-sep   { color: #bbb; margin: 0 4px; font-weight: 400; }
        .vts-sum-total { color: #999; }      /* tổng — xám nhẹ */

        .vts-summary-breakdown { display: flex; gap: 8px; }
        .vts-mini {
            flex: 1; padding: 8px 6px; border: 1px solid #ddd; border-radius: 4px;
            text-align: center; background: #fafafa;
        }
        .vts-mini-value { font-size: 18px; font-weight: 600; line-height: 1.2; }
        .vts-mini-label { font-size: 11px; color: #555; margin-top: 2px; }
        .vts-mini.success { border-color: #5cb85c; background: #f4faf4; }
        .vts-mini.warning { border-color: #f0ad4e; background: #fdf8ee; }
        .vts-mini.danger  { border-color: #d9534f; background: #fdf4f4; }
        .vts-mini.info    { border-color: #5bc0de; background: #f4faff; }
        .vts-mini.default { border-color: #ccc; }

        /* Scroll ngang chỉ trong vùng bảng */
        .vts-table-wrap { overflow-x: auto; width: 100%; }
        .vts-table { margin-bottom: 0; min-width: 760px; }

        /* Khóa cột Date dính bên trái khi scroll ngang */
        .vts-table th.vts-sticky-col,
        .vts-table td.vts-sticky-col {
            position: sticky; left: 0; z-index: 2;
            background-color: #fff; box-shadow: 1px 0 0 #ddd;
        }
        .vts-table thead th.vts-sticky-col { z-index: 3; }

        /* Type d'appel */
        .vts-type { white-space: nowrap; font-weight: 600; }
        .vts-type.vts-in  { color: #28a745; }
        .vts-type.vts-out { color: #0275d8; }

        /* Dòng phụ hiển thị RDV / prochain appel dưới badge */
        .vts-follow { font-size: 11px; color: #777; margin-top: 3px; }

        /* Dòng phụ hiển thị tên khách / công ty dưới số điện thoại */
        .vts-contact { font-size: 11px; color: #555; margin-top: 3px; line-height: 1.3; }
        .vts-add-contact {
            font-size: 11px; color: #5bc0de; margin-top: 3px;
            cursor: pointer; text-decoration: none; display: inline-block;
        }
        .vts-add-contact:hover { text-decoration: underline; color: #31708f; }

        /* Modal custom (không phụ thuộc plugin Bootstrap JS) */
        .vts-modal-overlay {
            display: none; position: fixed; inset: 0; z-index: 1050;
            background: rgba(0,0,0,.5);
        }
        .vts-modal-overlay.open { display: flex; align-items: flex-start; justify-content: center; }
        .vts-modal {
            background: #fff; border-radius: 4px; width: 100%; max-width: 460px;
            margin-top: 8vh; box-shadow: 0 5px 20px rgba(0,0,0,.3);
        }
        .vts-modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 18px; border-bottom: 1px solid #eee;
        }
        .vts-modal-title { margin: 0; font-size: 16px; }
        .vts-modal-close {
            background: none; border: none; font-size: 24px; line-height: 1;
            color: #999; cursor: pointer;
        }
        .vts-modal-body { padding: 18px; }
        .vts-modal-footer {
            padding: 12px 18px; border-top: 1px solid #eee;
            display: flex; justify-content: flex-end; gap: 8px;
        }
    </style>
@stop

@section('javascript')
    <script>
        const VTS = {
            callsDataUrl:        @json($callsDataUrl),
            defineUrlTpl:        @json($defineUrlTpl),
            recallsDataUrl:      @json($recallsDataUrl),
            meetingsDataUrl:     @json($meetingsDataUrl),
            meetingUpdateUrlTpl: @json($meetingUpdateUrlTpl),
            summaryDataUrl:      @json($summaryDataUrl),
            contactStoreUrl:     @json($contactStoreUrl),
            isAdmin:             @json($isAdmin),
            today:               @json($today),
            csrf:                @json($csrfToken),
        };

        const tbody   = document.getElementById('vtsTableBody');
        const dateEl  = document.getElementById('vtsDate');
        const userEl  = document.getElementById('vtsUser'); // null nếu không phải admin
        const pageInfoEl = document.getElementById('vtsPageInfo');
        const prevBtn = document.getElementById('vtsPrev');
        const nextBtn = document.getElementById('vtsNext');

        let currentPage = 1;
        let lastPage    = 1;

        // ---------- Helpers d'affichage ----------
        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }

        function callTypeBadge(t) {
            if (t === 'incoming') return '<span class="vts-type vts-in">&#8600; Entrant</span>';
            if (t === 'outgoing') return '<span class="vts-type vts-out">&#8599; Sortant</span>';
            return '<span class="text-muted">—</span>';
        }

        function statusBadge(s) {
            const map = {
                answered:  ['label-success', 'Répondu'],
                missed:    ['label-danger',  'Manqué'],
                completed: ['label-info',    'Terminé'],
            };
            const [cls, label] = map[s] || ['label-default', s || '—'];
            return `<span class="label ${cls}">${escapeHtml(label)}</span>`;
        }

        function customerTypeBadge(ct) {
            if (!ct) return '<span class="text-muted">Non défini</span>';
            const map = { 'prospect': 'Prospect', 'follow-up': 'Relance', 'member-care': 'Suivi membre' };
            return `<span class="label label-default">${escapeHtml(map[ct] || ct)}</span>`;
        }

        function resultBadge(res) {
            const map = {
                meeting: ['label-success', 'Rendez-vous'],
                recall:  ['label-warning', 'Rappel'],
                failed:  ['label-danger',  'Échec'],
            };
            const [cls, label] = map[res] || ['label-default', res];
            return `<span class="label ${cls}">${escapeHtml(label)}</span>`;
        }

        // Badge cho meeting_result (enum: success / postponed / undecided / failed)
        function meetingResultBadge(res) {
            const map = {
                success:   ['label-success', 'Succès'],
                postponed: ['label-warning', 'Reporté'],
                undecided: ['label-info',    'Indécis'],
                failed:    ['label-danger',  'Échec'],
            };
            const [cls, label] = map[res] || ['label-default', res];
            return `<span class="label ${cls}">${escapeHtml(label)}</span>`;
        }

        // Ô gộp: type de client + résultat + dòng RDV/prochain appel
        function customerCell(r) {
            let html = customerTypeBadge(r.customer_type);
            if (r.call_result) {
                html += ' ' + resultBadge(r.call_result);
            }
            if (r.next_follow_up_at && r.call_result === 'meeting') {
                html += `<div class="vts-follow">RDV le ${formatDateTime(r.next_follow_up_at)}</div>`;
            } else if (r.next_follow_up_at && r.call_result === 'recall') {
                html += `<div class="vts-follow">Prochain appel le ${formatDateTime(r.next_follow_up_at)}</div>`;
            }
            return html;
        }

        // Hiển thị số điện thoại + dòng phụ "Nom · Société" lấy từ danh bạ.
        // Nếu chưa định danh được -> link để mở modal thêm vào danh bạ.
        function phoneCell(r) {
            const phone = escapeHtml(r.partner_phone || '—');
            const name  = r.contact_name || '';
            const comp  = r.contact_company || '';
            let sub;
            if (name && comp) {
                sub = `<div class="vts-contact">${escapeHtml(name)} · ${escapeHtml(comp)}</div>`;
            } else if (name) {
                sub = `<div class="vts-contact">${escapeHtml(name)}</div>`;
            } else if (comp) {
                sub = `<div class="vts-contact">${escapeHtml(comp)}</div>`;
            } else {
                // Phone chưa có trong danh bạ -> cho phép click để thêm.
                sub = `<a class="vts-add-contact" data-phone="${escapeHtml(r.partner_phone || '')}">+ Ajouter au répertoire</a>`;
            }
            return phone + '<br>' + sub;
        }

        function formatDuration(sec) {
            sec = parseInt(sec || 0, 10);
            const m = Math.floor(sec / 60);
            const s = sec % 60;
            return m > 0 ? `${m} min ${String(s).padStart(2, '0')} s` : `${s} s`;
        }

        function formatDateTime(dt) {
            if (!dt) return '—';
            const d = new Date(String(dt).replace(' ', 'T'));
            return isNaN(d) ? escapeHtml(dt) : d.toLocaleString('fr-FR');
        }

        // Hiển thị ngày kiểu fr-FR (ví dụ "08/06/2026") cho phần title các card.
        function formatDateOnly(yyyymmdd) {
            if (!yyyymmdd) return '';
            const d = new Date(yyyymmdd + 'T00:00:00');
            return isNaN(d) ? yyyymmdd : d.toLocaleDateString('fr-FR');
        }

        function updateTitleDates() {
            const label = '— ' + formatDateOnly(dateEl.value || VTS.today);
            const c = document.getElementById('vtsCallsTitleDate');
            const r = document.getElementById('vtsRecallTitleDate');
            const m = document.getElementById('vtsMeetingTitleDate');
            if (c) c.textContent = label;
            if (r) r.textContent = label;
            if (m) m.textContent = label;
        }

        // ===================================================================
        //  Bandeau résumé : tải 3 ô lớn theo cùng filter date + user
        // ===================================================================
        function loadSummary() {
            const params = new URLSearchParams();
            params.set('date', dateEl.value || VTS.today);
            if (VTS.isAdmin && userEl && userEl.value) {
                params.set('user_id', userEl.value);
            }
            fetch(VTS.summaryDataUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(renderSummary)
                .catch(err => console.error('summary:', err));
        }

        function renderSummary(d) {
            // Nhóm 1: Appels typés
            document.getElementById('vtsSumTypedTotal').textContent  = d.typed.total;
            document.getElementById('vtsSumProspect').textContent    = d.typed.prospect;
            document.getElementById('vtsSumFollowUp').textContent    = d.typed.follow_up;
            document.getElementById('vtsSumMemberCare').textContent  = d.typed.member_care;
            // Nhóm 2: Appels avec résultat
            document.getElementById('vtsSumResultTotal').textContent = d.result.total;
            document.getElementById('vtsSumResMeeting').textContent  = d.result.meeting;
            document.getElementById('vtsSumResRecall').textContent   = d.result.recall;
            document.getElementById('vtsSumResFailed').textContent   = d.result.failed;
            // Nhóm 3: Rendez-vous du jour
            // A = đã có résultat (sum 4 breakdown), B = tổng meetings của card.
            const done = d.meetings.success + d.meetings.postponed
                       + d.meetings.undecided + d.meetings.failed;
            document.getElementById('vtsSumMeetDone').textContent       = done;
            document.getElementById('vtsSumMeetTotal').textContent      = d.meetings.total;
            document.getElementById('vtsSumMeetSuccess').textContent    = d.meetings.success;
            document.getElementById('vtsSumMeetPostponed').textContent  = d.meetings.postponed;
            document.getElementById('vtsSumMeetUndecided').textContent  = d.meetings.undecided;
            document.getElementById('vtsSumMeetFailed').textContent     = d.meetings.failed;
        }

        // ---------- Chargement des appels ----------
        function loadCalls(page) {
            currentPage = page || 1;
            updateTitleDates();

            const params = new URLSearchParams();
            params.set('date', dateEl.value || VTS.today);
            if (VTS.isAdmin && userEl && userEl.value) {
                params.set('user_id', userEl.value);
            }
            params.set('page', currentPage);

            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Chargement…</td></tr>';

            fetch(VTS.callsDataUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(renderCalls)
                .catch(err => {
                    console.error('Erreur:', err);
                    tbody.innerHTML =
                        '<tr><td colspan="7" class="text-center text-danger">Erreur de chargement</td></tr>';
                });
        }

        function renderCalls(payload) {
            const rows = payload.data || [];
            const meta = payload.meta || {};
            lastPage    = meta.last_page || 1;
            currentPage = meta.current_page || 1;

            if (rows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="7" class="text-center text-muted">Aucun appel pour cette date</td></tr>';
            } else {
                tbody.innerHTML = rows.map(r => {
                    // Luật:
                    //  - status = 'missed' (không bắt máy) -> không có gì để define, ẩn nút.
                    //  - editable=false (quá 3 ngày) -> ẩn nút.
                    //  - đã có google_event_id -> nút "Modifier RDV".
                    //  - còn lại -> nút "Définir".
                    let action;
                    if (r.status === 'missed' || !r.editable) {
                        action = '<span class="text-muted">—</span>';
                    } else if (r.google_event_id) {
                        action = `<button type="button" class="btn btn-xs btn-warning vts-reschedule"
                                    data-id="${r.id}"
                                    data-follow="${escapeHtml(r.next_follow_up_at || '')}"
                                    data-result="${escapeHtml(r.meeting_result || '')}">
                                    Modifier RDV
                               </button>`;
                    } else {
                        action = `<button type="button" class="btn btn-xs btn-primary vts-define"
                                    data-id="${r.id}"
                                    data-type="${escapeHtml(r.customer_type || '')}"
                                    data-result="${escapeHtml(r.call_result || '')}"
                                    data-client="${escapeHtml(r.meeting_client_name || '')}"
                                    data-company="${escapeHtml(r.meeting_client_company || '')}"
                                    data-follow="${escapeHtml(r.next_follow_up_at || '')}">
                                    Définir
                               </button>`;
                    }

                    return `
                        <tr>
                            <td class="vts-sticky-col">${formatDateTime(r.call_time)}</td>
                            <td>${callTypeBadge(r.call_type)}</td>
                            <td>${statusBadge(r.status)}</td>
                            <td>${phoneCell(r)}</td>
                            <td>${formatDuration(r.duration_seconds)}</td>
                            <td>${customerCell(r)}</td>
                            <td style="text-align:center;">${action}</td>
                        </tr>`;
                }).join('');
            }

            pageInfoEl.textContent =
                `Page ${currentPage} / ${lastPage} — ${meta.total || 0} appel(s)`;
            prevBtn.disabled = (currentPage <= 1);
            nextBtn.disabled = (currentPage >= lastPage);
        }

        // ---------- Pagination ----------
        prevBtn.addEventListener('click', () => { if (currentPage > 1) loadCalls(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < lastPage) loadCalls(currentPage + 1); });

        // ---------- Filtres ----------
        document.getElementById('vtsApply').addEventListener('click', () => loadCalls(1));
        if (userEl) userEl.addEventListener('change', () => loadCalls(1));
        document.getElementById('vtsApply').addEventListener('click', () => loadSummary());
        if (userEl) userEl.addEventListener('change', () => loadSummary());

        // ---------- Modal ----------
        const overlay   = document.getElementById('vtsModalOverlay');
        const modalType = document.getElementById('vtsModalType');
        const modalResult = document.getElementById('vtsModalResult');
        const modalClient = document.getElementById('vtsModalClient');
        const modalClientGroup = document.getElementById('vtsModalClientGroup');
        const modalCompany = document.getElementById('vtsModalCompany');
        const modalCompanyGroup = document.getElementById('vtsModalCompanyGroup');
        const modalFollow = document.getElementById('vtsModalFollow');
        const saveBtn   = document.getElementById('vtsModalSave');
        let editingId   = null;

        // Trả về 'YYYY-MM-DDTHH:MM' của thời điểm hiện tại (giờ local) để gán vào input[min].
        function nowDatetimeLocal() {
            const now = new Date();
            const tz  = now.getTimezoneOffset() * 60000;
            return new Date(now.getTime() - tz).toISOString().slice(0, 16);
        }

        // Bật/tắt result, client_name, company & next_follow_up theo nghiệp vụ:
        //  - member-care        : tắt tất cả result, client, company, follow-up.
        //  - prospect/follow-up : bật result.
        //  - result = meeting   : hiện ô Nom du client + Société.
        //  - result = failed    : tắt follow-up.
        //  - Prochain suivi     : luôn min = bây giờ (chỉ chọn tương lai).
        function applyModalRules() {
            const ct = modalType.value;
            const resultEnabled = (ct === 'prospect' || ct === 'follow-up');

            modalResult.disabled = !resultEnabled;
            if (!resultEnabled) modalResult.value = '';

            const isMeeting = (modalResult.value === 'meeting');
            modalClientGroup.style.display  = isMeeting ? '' : 'none';
            modalCompanyGroup.style.display = isMeeting ? '' : 'none';
            if (!isMeeting) {
                modalClient.value  = '';
                modalCompany.value = '';
            }

            const followEnabled = resultEnabled && (modalResult.value !== 'failed');
            modalFollow.disabled = !followEnabled;
            if (!followEnabled) modalFollow.value = '';

            // Chỉ cho chọn thời điểm trong tương lai
            modalFollow.min = nowDatetimeLocal();
        }

        function openModal(id, type, result, follow, clientName, companyName) {
            editingId = id;
            modalType.value = type || '';
            modalResult.value = result || '';
            modalClient.value = clientName || '';
            modalCompany.value = companyName || '';

            // Prefill prochain suivi; nếu giá trị cũ đã quá khứ thì để trắng (input.min sẽ chặn re-save).
            let f = follow ? String(follow).replace(' ', 'T').slice(0, 16) : '';
            if (f && new Date(f) <= new Date()) f = '';
            modalFollow.value = f;

            applyModalRules();
            overlay.classList.add('open');
        }
        function closeModal() {
            overlay.classList.remove('open');
            editingId = null;
        }

        modalType.addEventListener('change', applyModalRules);
        modalResult.addEventListener('change', applyModalRules);

        document.getElementById('vtsModalClose').addEventListener('click', closeModal);
        document.getElementById('vtsModalCancel').addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

        // Click delegation: phân biệt nút Définir vs Modifier RDV (vì đều render động).
        tbody.addEventListener('click', (e) => {
            const defineBtn = e.target.closest('.vts-define');
            if (defineBtn) {
                openModal(
                    defineBtn.dataset.id,
                    defineBtn.dataset.type,
                    defineBtn.dataset.result,
                    defineBtn.dataset.follow,
                    defineBtn.dataset.client,
                    defineBtn.dataset.company
                );
                return;
            }
            const rescheduleBtn = e.target.closest('.vts-reschedule');
            if (rescheduleBtn) {
                openMeetingModal(
                    rescheduleBtn.dataset.id,
                    rescheduleBtn.dataset.follow,
                    rescheduleBtn.dataset.result
                );
                return;
            }
        });

        saveBtn.addEventListener('click', () => {
            if (!editingId) return;
            const type = modalType.value;
            if (!type) { alert('Veuillez choisir un type de client.'); return; }

            const result = modalResult.value || null;
            const clientName  = (result === 'meeting') ? modalClient.value.trim()  : '';
            const companyName = (result === 'meeting') ? modalCompany.value.trim() : '';

            // Khi meeting -> bắt buộc nhập tên khách (đối ứng required_if ở server).
            if (result === 'meeting' && !clientName) {
                alert('Veuillez saisir le nom du client pour le rendez-vous.');
                return;
            }

            // datetime-local 'YYYY-MM-DDTHH:MM' -> 'YYYY-MM-DD HH:MM' ; vide => null
            const follow = modalFollow.value ? modalFollow.value.replace('T', ' ') : null;

            // Prochain suivi phải tương lai (đối ứng after:now ở server).
            if (follow && new Date(modalFollow.value) <= new Date()) {
                alert('Le prochain suivi doit être dans le futur.');
                return;
            }

            const url = VTS.defineUrlTpl.replace('__ID__', editingId);

            saveBtn.disabled = true;
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': VTS.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        customer_type: type,
                        call_result: result,
                        meeting_client_name: clientName || null,
                        meeting_client_company: companyName || null,
                        next_follow_up_at: follow,
                    }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok) throw new Error(j.message || 'Erreur lors de l\'enregistrement');
                    closeModal();
                    // Refresh cả 3 card + bandeau résumé.
                    loadCalls(currentPage);
                    loadRecalls(1);
                    loadMeetings(1);
                    loadSummary();
                })
                .catch(err => alert(err.message))
                .finally(() => { saveBtn.disabled = false; });
        });

        // ===================================================================
        //  CARD 2 : Rappels à passer aujourd'hui
        // ===================================================================
        const recallBody     = document.getElementById('vtsRecallBody');
        const recallPageInfo = document.getElementById('vtsRecallPageInfo');
        const recallPrevBtn  = document.getElementById('vtsRecallPrev');
        const recallNextBtn  = document.getElementById('vtsRecallNext');
        let recallPage = 1, recallLastPage = 1;

        function loadRecalls(page) {
            recallPage = page || 1;
            const params = new URLSearchParams();
            params.set('date', dateEl.value || VTS.today);
            if (VTS.isAdmin && userEl && userEl.value) {
                params.set('user_id', userEl.value);
            }
            params.set('page', recallPage);

            recallBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Chargement…</td></tr>';

            fetch(VTS.recallsDataUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(renderRecalls)
                .catch(err => {
                    console.error('Erreur:', err);
                    recallBody.innerHTML =
                        '<tr><td colspan="3" class="text-center text-danger">Erreur de chargement</td></tr>';
                });
        }

        function renderRecalls(payload) {
            const rows = payload.data || [];
            const meta = payload.meta || {};
            recallLastPage = meta.last_page || 1;
            recallPage     = meta.current_page || 1;

            if (rows.length === 0) {
                recallBody.innerHTML =
                    '<tr><td colspan="3" class="text-center text-muted">Aucun rappel à passer</td></tr>';
            } else {
                recallBody.innerHTML = rows.map(r => {
                    const time = r.next_follow_up_at
                        ? formatDateTime(r.next_follow_up_at)
                        : '<span class="text-muted">Pas d\'heure précise</span>';
                    const badge = r.status === 'contacted'
                        ? '<span class="label label-success">Déjà appelé</span>'
                        : '<span class="label label-warning">À rappeler</span>';
                    return `<tr>
                        <td>${phoneCell(r)}</td>
                        <td>${time}</td>
                        <td>${badge}</td>
                    </tr>`;
                }).join('');
            }

            recallPageInfo.textContent =
                `Page ${recallPage} / ${recallLastPage} — ${meta.total || 0} numéro(s)`;
            recallPrevBtn.disabled = (recallPage <= 1);
            recallNextBtn.disabled = (recallPage >= recallLastPage);
        }

        recallPrevBtn.addEventListener('click', () => { if (recallPage > 1) loadRecalls(recallPage - 1); });
        recallNextBtn.addEventListener('click', () => { if (recallPage < recallLastPage) loadRecalls(recallPage + 1); });

        // Card Rappels dùng chung filter date + user với card Historique.
        document.getElementById('vtsApply').addEventListener('click', () => loadRecalls(1));
        if (userEl) userEl.addEventListener('change', () => loadRecalls(1));

        // ===================================================================
        //  CARD 3 : Rendez-vous
        // ===================================================================
        const meetingBody     = document.getElementById('vtsMeetingBody');
        const meetingPageInfo = document.getElementById('vtsMeetingPageInfo');
        const meetingPrevBtn  = document.getElementById('vtsMeetingPrev');
        const meetingNextBtn  = document.getElementById('vtsMeetingNext');
        let meetingPage = 1, meetingLastPage = 1;

        function loadMeetings(page) {
            meetingPage = page || 1;
            const params = new URLSearchParams();
            params.set('date', dateEl.value || VTS.today);
            if (VTS.isAdmin && userEl && userEl.value) {
                params.set('user_id', userEl.value);
            }
            params.set('page', meetingPage);

            meetingBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Chargement…</td></tr>';

            fetch(VTS.meetingsDataUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(renderMeetings)
                .catch(err => {
                    console.error('Erreur:', err);
                    meetingBody.innerHTML =
                        '<tr><td colspan="4" class="text-center text-danger">Erreur de chargement</td></tr>';
                });
        }

        function renderMeetings(payload) {
            const rows = payload.data || [];
            const meta = payload.meta || {};
            meetingLastPage = meta.last_page || 1;
            meetingPage     = meta.current_page || 1;

            if (rows.length === 0) {
                meetingBody.innerHTML =
                    '<tr><td colspan="4" class="text-center text-muted">Aucun rendez-vous</td></tr>';
            } else {
                meetingBody.innerHTML = rows.map(r => {
                    const time = r.next_follow_up_at
                        ? formatDateTime(r.next_follow_up_at)
                        : '<span class="text-muted">Pas d\'heure précise</span>';
                    const badge = r.meeting_result
                        ? meetingResultBadge(r.meeting_result)
                        : '<span class="text-muted">Non renseigné</span>';
                    const btn = `<button type="button" class="btn btn-xs btn-warning vts-meeting-edit"
                                    data-id="${r.id}"
                                    data-follow="${escapeHtml(r.next_follow_up_at || '')}"
                                    data-result="${escapeHtml(r.meeting_result || '')}">
                                    Modifier
                                </button>`;
                    return `<tr>
                        <td>${phoneCell(r)}</td>
                        <td>${time}</td>
                        <td>${badge}</td>
                        <td style="text-align:center;">${btn}</td>
                    </tr>`;
                }).join('');
            }

            meetingPageInfo.textContent =
                `Page ${meetingPage} / ${meetingLastPage} — ${meta.total || 0} RDV`;
            meetingPrevBtn.disabled = (meetingPage <= 1);
            meetingNextBtn.disabled = (meetingPage >= meetingLastPage);
        }

        meetingPrevBtn.addEventListener('click', () => { if (meetingPage > 1) loadMeetings(meetingPage - 1); });
        meetingNextBtn.addEventListener('click', () => { if (meetingPage < meetingLastPage) loadMeetings(meetingPage + 1); });

        // Card Rendez-vous dùng chung filter date + user với hai card kia.
        document.getElementById('vtsApply').addEventListener('click', () => loadMeetings(1));
        if (userEl) userEl.addEventListener('change', () => loadMeetings(1));

        // ===================================================================
        //  Modal : Modifier le rendez-vous (chung cho 2 nút: Modifier RDV
        //  trên Historique và Modifier trên card Rendez-vous)
        // ===================================================================
        const mmOverlay = document.getElementById('vtsMeetingModalOverlay');
        const mmDate    = document.getElementById('vtsMeetingModalDate');
        const mmResult  = document.getElementById('vtsMeetingModalResult');
        const mmSaveBtn = document.getElementById('vtsMeetingModalSave');
        let mmEditingId = null;

        function openMeetingModal(id, follow, result) {
            mmEditingId = id;

            // Prefill date; nếu giá trị cũ quá khứ thì để trắng (input.min sẽ chặn submit).
            let f = follow ? String(follow).replace(' ', 'T').slice(0, 16) : '';
            if (f && new Date(f) <= new Date()) f = '';
            mmDate.value = f;
            mmDate.min   = nowDatetimeLocal();

            mmResult.value = result || '';
            mmOverlay.classList.add('open');
        }
        function closeMeetingModal() {
            mmOverlay.classList.remove('open');
            mmEditingId = null;
        }

        document.getElementById('vtsMeetingModalClose').addEventListener('click', closeMeetingModal);
        document.getElementById('vtsMeetingModalCancel').addEventListener('click', closeMeetingModal);
        mmOverlay.addEventListener('click', (e) => { if (e.target === mmOverlay) closeMeetingModal(); });

        // Click delegation cho nút Modifier trên card Rendez-vous.
        meetingBody.addEventListener('click', (e) => {
            const btn = e.target.closest('.vts-meeting-edit');
            if (!btn) return;
            openMeetingModal(btn.dataset.id, btn.dataset.follow, btn.dataset.result);
        });

        mmSaveBtn.addEventListener('click', () => {
            if (!mmEditingId) return;

            const newDate = mmDate.value ? mmDate.value.replace('T', ' ') : null;
            if (newDate && new Date(mmDate.value) <= new Date()) {
                alert('Le rendez-vous doit être dans le futur.');
                return;
            }

            const newResult = mmResult.value || null;
            const url = VTS.meetingUpdateUrlTpl.replace('__ID__', mmEditingId);

            mmSaveBtn.disabled = true;
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': VTS.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        next_follow_up_at: newDate,
                        meeting_result: newResult,
                    }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok) throw new Error(j.message || 'Erreur lors de la mise à jour');
                    closeMeetingModal();
                    // Refresh cả 3 card + bandeau résumé.
                    loadCalls(currentPage);
                    loadRecalls(1);
                    loadMeetings(1);
                    loadSummary();
                })
                .catch(err => alert(err.message))
                .finally(() => { mmSaveBtn.disabled = false; });
        });

        // ===================================================================
        //  Modal : Ajouter au répertoire
        //  Mở khi click link "+ Ajouter au répertoire" dưới một số chưa biết.
        // ===================================================================
        const cmOverlay = document.getElementById('vtsContactModalOverlay');
        const cmPhone   = document.getElementById('vtsContactModalPhone');
        const cmName    = document.getElementById('vtsContactModalName');
        const cmCompany = document.getElementById('vtsContactModalCompany');
        const cmSaveBtn = document.getElementById('vtsContactModalSave');
        let cmEditingPhone = null;

        function openContactModal(rawPhone) {
            cmEditingPhone = rawPhone;
            cmPhone.value   = rawPhone || '';
            cmName.value    = '';
            cmCompany.value = '';
            cmOverlay.classList.add('open');
            // Focus vào ô tên để gõ được ngay.
            setTimeout(() => cmName.focus(), 50);
        }
        function closeContactModal() {
            cmOverlay.classList.remove('open');
            cmEditingPhone = null;
        }

        document.getElementById('vtsContactModalClose').addEventListener('click', closeContactModal);
        document.getElementById('vtsContactModalCancel').addEventListener('click', closeContactModal);
        cmOverlay.addEventListener('click', (e) => { if (e.target === cmOverlay) closeContactModal(); });

        // Click delegation cho cả 3 tbody: Historique, Rappels, Rendez-vous.
        function bindAddContactClick(container) {
            container.addEventListener('click', (e) => {
                const link = e.target.closest('.vts-add-contact');
                if (!link) return;
                e.preventDefault();
                openContactModal(link.dataset.phone);
            });
        }
        bindAddContactClick(tbody);
        bindAddContactClick(recallBody);
        bindAddContactClick(meetingBody);

        cmSaveBtn.addEventListener('click', () => {
            if (!cmEditingPhone) return;
            const name    = cmName.value.trim();
            const company = cmCompany.value.trim();

            if (!name && !company) {
                alert('Veuillez saisir au moins le nom du client ou de la société.');
                return;
            }

            cmSaveBtn.disabled = true;
            fetch(VTS.contactStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': VTS.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        phone: cmEditingPhone,
                        display_name: name || null,
                        company_name: company || null,
                    }),
                })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok) throw new Error(j.message || 'Erreur lors de l\'enregistrement');
                    closeContactModal();
                    // Reload cả 3 card để hiển thị tên/société vừa thêm trên mọi dòng cùng số.
                    loadCalls(currentPage);
                    loadRecalls(1);
                    loadMeetings(1);
                })
                .catch(err => alert(err.message))
                .finally(() => { cmSaveBtn.disabled = false; });
        });

        // ---------- Chargement initial ----------
        loadCalls(1);
        loadRecalls(1);
        loadMeetings(1);
        loadSummary();
    </script>
@stop