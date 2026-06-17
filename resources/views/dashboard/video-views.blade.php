@extends('voyager::master')

@section('content')
    <div class="page-content analytics-container">
        <div class="row">
            <div class="col-md-12 col-lg-8">
                <div class="panel panel-bordered">
                    <div class="panel-heading"
                        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <h3 class="panel-title">Statistiques des vidéos</h3>

                        <div class="vid-filters"
                            style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-right: 15px;">

                            {{-- Filter expert. '' = chưa chọn, 'all' = tất cả --}}
                            <select id="filterExpert" class="form-control input-sm" style="width: auto;">
                                <option value="">-- Choisir un expert --</option>
                                <option value="all">Tous les experts</option>
                                @foreach ($experts as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>

                            {{-- Khoảng thời gian (tuỳ chọn). Để trống = views cộng dồn --}}
                            <input type="date" id="filterFrom" class="form-control input-sm" style="width: auto;">
                            <input type="date" id="filterTo" class="form-control input-sm" style="width: auto;">

                            <button type="button" id="applyFilters" class="btn btn-sm btn-info">
                                Appliquer
                            </button>

                            {{-- Tổng views ở góc, hiện sau khi lọc --}}
                            <span id="totalBadge" class="label label-primary"
                                style="font-size: 13px; padding: 6px 10px; display: none;">
                                Vues totales : <strong id="totalViews">0</strong>
                            </span>
                        </div>
                    </div>

                    <div class="panel-body">
                        <div id="tableEmpty" class="text-center text-muted" style="padding: 60px 0;">
                            Sélectionnez un expert pour afficher les données.
                        </div>

                        <div id="tableWrap" class="vid-table-wrap" style="display: none;">
                            <table class="table table-hover vid-table">
                                <thead>
                                    <tr>
                                        <th>Vidéo</th>
                                        <th class="vid-views-col">Vues</th>
                                    </tr>
                                </thead>
                                <tbody id="vidTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .vid-table-wrap {
            max-height: 600px;
            overflow-y: auto;
        }

        .vid-table {
            margin-bottom: 0;
        }

        /* Cột Vues: cố định hẹp, canh phải để không chiếm chỗ */
        .vid-table th.vid-views-col,
        .vid-table td.vid-views-col {
            width: 120px;
            white-space: nowrap;
            text-align: right;
            vertical-align: middle;
        }

        .vid-title {
            font-weight: 600;
        }

        .vid-event {
            font-size: 12px;
            margin-top: 2px;
        }
    </style>
@stop

@section('javascript')
    <script>
        const VID_CONFIG = {
            dataUrl: @json($dataUrl),
        };

        const expertEl = document.getElementById('filterExpert');
        const fromEl   = document.getElementById('filterFrom');
        const toEl     = document.getElementById('filterTo');
        const emptyEl  = document.getElementById('tableEmpty');
        const wrapEl   = document.getElementById('tableWrap');
        const bodyEl   = document.getElementById('vidTableBody');
        const badgeEl  = document.getElementById('totalBadge');
        const totalEl  = document.getElementById('totalViews');

        function escapeHtml(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // 'all' và '' đều = không lọc expert ở backend
        function expertParam() {
            const v = expertEl.value;
            return (v && v !== 'all') ? v : '';
        }

        function loadTable() {
            const params = new URLSearchParams();

            const ep = expertParam();
            if (ep) params.set('expert_id', ep);

            // Khoảng thời gian (nếu có) -> backend chuyển sang đếm trk_content_views
            if (fromEl.value) params.set('from', fromEl.value);
            if (toEl.value)   params.set('to', toEl.value);

            fetch(VID_CONFIG.dataUrl + '?' + params.toString())
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(renderTable)
                .catch(err => {
                    console.error('Erreur:', err);
                    alert('Erreur lors du chargement des données');
                });
        }

        // Chỉ load khi đã chọn expert (giữ yêu cầu: không có data init)
        function maybeLoad(alertIfNone) {
            if (expertEl.value === '') {
                if (alertIfNone) alert('Veuillez d\'abord choisir un expert.');
                return;
            }
            loadTable();
        }

        function renderTable(payload) {
            const rows = payload.rows || [];
            const hasData = rows.length > 0;

            emptyEl.style.display = hasData ? 'none' : 'block';
            wrapEl.style.display  = hasData ? 'block' : 'none';
            if (!hasData) {
                emptyEl.textContent = 'Aucune donnée pour ce filtre.';
            }

            // Tổng views ở góc
            badgeEl.style.display = 'inline-block';
            totalEl.textContent = (payload.total_views || 0).toLocaleString('fr-FR');

            bodyEl.innerHTML = rows.map(r => {
                const event = r.event_name
                    ? `<div class="vid-event text-muted">${escapeHtml(r.event_name)}</div>`
                    : '';
                return `
                    <tr>
                        <td>
                            <div class="vid-title">${escapeHtml(r.title)}</div>
                            ${event}
                        </td>
                        <td class="vid-views-col">${(r.views || 0).toLocaleString('fr-FR')}</td>
                    </tr>
                `;
            }).join('');
        }

        function resetTable() {
            badgeEl.style.display = 'none';
            wrapEl.style.display  = 'none';
            bodyEl.innerHTML = '';
            emptyEl.style.display = 'block';
            emptyEl.textContent = 'Sélectionnez un expert pour afficher les données.';
        }

        // Filter expert thành combobox có ô tìm kiếm (Select2 có sẵn trong Voyager).
        // minimumInputLength: 2 -> gõ >= 2 ký tự mới hiện gợi ý, tránh xổ danh sách dài.
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#filterExpert').select2({
                width: '220px',
                minimumInputLength: 2,
                placeholder: '-- Choisir un expert --',
                allowClear: true,
                language: {
                    inputTooShort: () => 'Tapez au moins 2 caractères',
                    noResults: () => 'Aucun résultat',
                },
            });
        }

        // Dùng jQuery .on('change') vì Select2 phát sự kiện change qua jQuery.
        jQuery('#filterExpert').on('change', function () {
            if (this.value === '') {
                resetTable();
                return;
            }
            loadTable();
        });

        // Đổi ngày -> tự load lại nếu đã chọn expert; bấm Appliquer thì cảnh báo nếu chưa chọn.
        fromEl.addEventListener('change', () => maybeLoad(false));
        toEl.addEventListener('change', () => maybeLoad(false));
        document.getElementById('applyFilters').addEventListener('click', () => maybeLoad(true));
    </script>
@stop