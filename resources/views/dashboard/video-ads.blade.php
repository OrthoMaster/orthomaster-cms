@extends('voyager::master')

@section('content')
    <div class="page-content">
        <div class="analytics-container">
            <div class="row">
                <div class="col-md-12 col-lg-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading"
                            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <h3 class="panel-title">
                                Statistiques des publicités vidéo
                                @if (!$isAdmin)
                                    &mdash; {{ $companies->first()->name ?? '' }}
                                @endif
                            </h3>

                            <div class="vad-filters"
                                style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-right: 15px;">

                                {{-- Company: admin chọn được, đối tác bị khóa --}}
                                @if ($isAdmin)
                                    <select id="filterCompany" class="form-control input-sm" style="width: auto;">
                                        <option value="">Toutes les entreprises</option>
                                        @foreach ($companies as $c)
                                            <option value="{{ $c->id }}"
                                                {{ (int) $companyId === (int) $c->id ? 'selected' : '' }}>
                                                {{ $c->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" id="filterCompany" value="{{ $companyId }}">
                                @endif

                                {{-- Video: cả hai đều chọn được. Trống = tổng hợp --}}
                                <select id="filterVideo" class="form-control input-sm" style="width: auto;">
                                    <option value="">Toutes les vidéos (cumul)</option>
                                    @foreach ($videoAds as $v)
                                        <option value="{{ $v->id }}"
                                            {{ (int) $videoAdId === (int) $v->id ? 'selected' : '' }}>
                                            {{ $v->title }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Granularité: par jour / semaine / mois --}}
                                <select id="filterGranularity" class="form-control input-sm" style="width: auto;">
                                    <option value="day" {{ $granularity === 'day' ? 'selected' : '' }}>Par jour</option>
                                    <option value="week" {{ $granularity === 'week' ? 'selected' : '' }}>Par semaine</option>
                                    <option value="month" {{ $granularity === 'month' ? 'selected' : '' }}>Par mois</option>
                                </select>

                                <input type="date" id="filterFrom" class="form-control input-sm" style="width: auto;"
                                    value="{{ $from }}">
                                <input type="date" id="filterTo" class="form-control input-sm" style="width: auto;"
                                    value="{{ $to }}">

                                <button type="button" id="applyFilters" class="btn btn-sm btn-info">
                                    Appliquer
                                </button>

                                <button type="button" id="shareBtn" class="btn btn-sm btn-default">
                                    <i class="voyager-share"></i> Partager
                                </button>
                            </div>
                        </div>

                        {{-- ===== Ligne de résumé : 4 KPI ===== --}}
                        <div class="panel-body" style="padding-bottom: 0;">
                            <div class="row vad-kpi-row">
                                <div class="col-md-3 col-sm-6">
                                    <div class="vad-kpi">
                                        <div class="vad-kpi-label">Impressions</div>
                                        <div class="vad-kpi-value" id="kpiImpressions">{{ number_format($summary['impressions'], 0, ',', ' ') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="vad-kpi">
                                        <div class="vad-kpi-label">Vues</div>
                                        <div class="vad-kpi-value" id="kpiViews">{{ number_format($summary['views'], 0, ',', ' ') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="vad-kpi">
                                        <div class="vad-kpi-label">Vues complètes</div>
                                        <div class="vad-kpi-value" id="kpiCompleted">{{ number_format($summary['completed_views'], 0, ',', ' ') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="vad-kpi">
                                        <div class="vad-kpi-label">Contacts</div>
                                        <div class="vad-kpi-value" id="kpiContacted">{{ number_format($summary['contacted'], 0, ',', ' ') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div style="min-height: 500px; max-height: 650px">
                                <canvas id="videoAdsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 col-lg-12">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title" id="vadTableTitle">Détail par jour</h3>
                        </div>
                        <div class="panel-body">
                            <div class="vad-table-wrap">
                                <table class="table table-bordered table-hover vad-table">
                                    <thead>
                                        <tr>
                                            <th class="vad-sticky-col" id="vadTablePeriodHead">Date</th>
                                            <th>Impressions</th>
                                            <th>Vues</th>
                                            <th>CTR</th>
                                            <th>Vues complètes</th>
                                            <th>Contacts</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vadTableBody"></tbody>
                                </table>
                            </div>

                            <div class="vad-pagination"
                                style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <span id="vadPageInfo" class="text-muted"></span>
                                <div class="btn-group">
                                    <button type="button" id="vadPrevPage" class="btn btn-sm btn-default">
                                        &laquo; Précédent
                                    </button>
                                    <button type="button" id="vadNextPage" class="btn btn-sm btn-default">
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

    <style>
        /* Cho phép scroll ngang chỉ vùng bảng */
        .vad-table-wrap {
            overflow-x: auto;
            width: 100%;
        }

        .vad-table {
            margin-bottom: 0;
            min-width: 720px;
        }

        /* Khóa cột Date: dính bên trái khi scroll ngang */
        .vad-table th.vad-sticky-col,
        .vad-table td.vad-sticky-col {
            position: sticky;
            left: 0;
            z-index: 2;
            background-color: #fff;
            box-shadow: 1px 0 0 #ddd;
        }

        .vad-table thead th.vad-sticky-col {
            z-index: 3;
        }

        /* KPI cards */
        .vad-kpi-row {
            margin-bottom: 4px;
        }
        .vad-kpi {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }
        .vad-kpi-label {
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .vad-kpi-value {
            font-size: 26px;
            font-weight: 700;
            color: #1d3557;
            margin-top: 6px;
            line-height: 1;
        }
    </style>
@stop

@section('javascript')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const VAD_CONFIG = {
            dataUrl: @json($chartDataUrl),
            isAdmin: @json($isAdmin),
            isShared: @json($isShared),
            fixedCompanyId: @json($companyId),
            signature: @json($signature),
            // URL gốc (không ký) của trang shared cho công ty đang xem.
            // companyId luôn có giá trị ở cả 2 chế độ.
            shareBaseUrl: @json($companyId ? url('dashboard/video-ads/shared/' . $companyId) : null),
        };

        // Libellé de la période selon la granularité (pour l'en-tête du tableau)
        const PERIOD_LABELS = {
            day: { title: 'Détail par jour', head: 'Date', unit: 'jour(s)' },
            week: { title: 'Détail par semaine', head: 'Semaine', unit: 'semaine(s)' },
            month: { title: 'Détail par mois', head: 'Mois', unit: 'mois' },
        };

        let videoAdsChartInstance;
        const videoAdsCanvas = document.getElementById('videoAdsChart');

        function renderChart(payload) {
            if (videoAdsChartInstance) {
                videoAdsChartInstance.destroy();
            }

            videoAdsChartInstance = new Chart(videoAdsCanvas, {
                type: 'line',
                data: {
                    labels: payload.labels,
                    datasets: [{
                            label: 'Impressions',
                            data: payload.impressions,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            fill: true,
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 3,
                            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                        },
                        {
                            label: 'Vues',
                            data: payload.views,
                            borderColor: 'rgba(40, 167, 69, 1)',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: true,
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 3,
                            pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                        },
                        {
                            label: 'Vues complètes',
                            data: payload.completed_views,
                            borderColor: 'rgba(255, 159, 64, 1)',
                            backgroundColor: 'rgba(255, 159, 64, 0.1)',
                            fill: true,
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 3,
                            pointBackgroundColor: 'rgba(255, 159, 64, 1)',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            renderSummary(payload);
            renderTable(payload);
        }

        // ---------- Ligne KPI ----------
        function renderSummary(payload) {
            // Si l'API renvoie summary, on l'utilise ; sinon on calcule côté client.
            const s = payload.summary || {
                impressions: payload.impressions.reduce((a, b) => a + b, 0),
                views: payload.views.reduce((a, b) => a + b, 0),
                completed_views: payload.completed_views.reduce((a, b) => a + b, 0),
                contacted: (payload.contacted || []).reduce((a, b) => a + b, 0),
            };
            document.getElementById('kpiImpressions').textContent = (s.impressions || 0).toLocaleString('fr-FR');
            document.getElementById('kpiViews').textContent = (s.views || 0).toLocaleString('fr-FR');
            document.getElementById('kpiCompleted').textContent = (s.completed_views || 0).toLocaleString('fr-FR');
            document.getElementById('kpiContacted').textContent = (s.contacted || 0).toLocaleString('fr-FR');
        }

        // ---------- Bảng chi tiết + phân trang ----------
        const VAD_PAGE_SIZE = 15;
        let vadRows = []; // [{period, impressions, views, ctr, completed, contacted}]
        let vadPage = 1;
        let vadUnit = 'jour(s)';

        function renderTable(payload) {
            const g = payload.granularity || 'day';
            const meta = PERIOD_LABELS[g] || PERIOD_LABELS.day;
            vadUnit = meta.unit;

            // En-têtes dynamiques
            document.getElementById('vadTableTitle').textContent = meta.title;
            document.getElementById('vadTablePeriodHead').textContent = meta.head;

            // Gộp các mảng song song thành mảng dòng. Mới nhất lên đầu.
            vadRows = payload.labels.map((label, i) => {
                const imp = payload.impressions[i] || 0;
                const vw = payload.views[i] || 0;
                const ctr = imp > 0 ? (vw / imp * 100) : 0;
                return {
                    period: label,
                    impressions: imp,
                    views: vw,
                    ctr: ctr,
                    completed: payload.completed_views[i] || 0,
                    contacted: (payload.contacted && payload.contacted[i]) || 0,
                };
            }).reverse();

            vadPage = 1;
            drawTablePage();
        }

        function drawTablePage() {
            const tbody = document.getElementById('vadTableBody');
            const total = vadRows.length;
            const totalPages = Math.max(1, Math.ceil(total / VAD_PAGE_SIZE));

            if (vadPage > totalPages) vadPage = totalPages;
            if (vadPage < 1) vadPage = 1;

            const start = (vadPage - 1) * VAD_PAGE_SIZE;
            const pageRows = vadRows.slice(start, start + VAD_PAGE_SIZE);

            if (pageRows.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="6" class="text-center text-muted">Aucune donnée</td></tr>';
            } else {
                tbody.innerHTML = pageRows.map(r => `
                    <tr>
                        <td class="vad-sticky-col">${r.period}</td>
                        <td>${r.impressions.toLocaleString('fr-FR')}</td>
                        <td>${r.views.toLocaleString('fr-FR')}</td>
                        <td>${r.ctr.toFixed(2)} %</td>
                        <td>${r.completed.toLocaleString('fr-FR')}</td>
                        <td>${r.contacted.toLocaleString('fr-FR')}</td>
                    </tr>
                `).join('');
            }

            document.getElementById('vadPageInfo').textContent =
                `Page ${vadPage} / ${totalPages} — ${total} ${vadUnit}`;

            document.getElementById('vadPrevPage').disabled = (vadPage <= 1);
            document.getElementById('vadNextPage').disabled = (vadPage >= totalPages);
        }

        document.getElementById('vadPrevPage').addEventListener('click', function() {
            vadPage--;
            drawTablePage();
        });
        document.getElementById('vadNextPage').addEventListener('click', function() {
            vadPage++;
            drawTablePage();
        });

        // Render lần đầu với dữ liệu server-side
        renderChart(@json($chart));

        // Khi đổi công ty (admin): nạp lại danh sách video tương ứng bằng cách
        // reload trang để controller lọc video theo công ty.
        const companyEl = document.getElementById('filterCompany');
        if (VAD_CONFIG.isAdmin && companyEl) {
            companyEl.addEventListener('change', function() {
                const params = new URLSearchParams({
                    ads_company_id: this.value || '',
                    from: document.getElementById('filterFrom').value,
                    to: document.getElementById('filterTo').value,
                    granularity: document.getElementById('filterGranularity').value,
                });
                window.location.search = params.toString();
            });
        }

        function buildParams() {
            const params = new URLSearchParams();

            params.set('from', document.getElementById('filterFrom').value);
            params.set('to', document.getElementById('filterTo').value);
            params.set('granularity', document.getElementById('filterGranularity').value);

            const videoVal = document.getElementById('filterVideo').value;
            if (videoVal) {
                params.set('video_ads_id', videoVal);
            }

            // Admin gửi kèm company; đối tác KHÔNG gửi (đã fix theo link signed)
            if (VAD_CONFIG.isAdmin) {
                const cVal = companyEl ? companyEl.value : '';
                if (cVal) {
                    params.set('ads_company_id', cVal);
                }
            }

            // Giữ chữ ký hợp lệ cho link đối tác
            if (VAD_CONFIG.isShared && VAD_CONFIG.signature) {
                params.set('signature', VAD_CONFIG.signature);
            }

            return params;
        }

        document.getElementById('applyFilters').addEventListener('click', function() {
            const url = VAD_CONFIG.dataUrl + '?' + buildParams().toString();

            fetch(url)
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => renderChart(data))
                .catch(err => {
                    console.error('Erreur:', err);
                    alert('Erreur lors du chargement des données');
                });
        });

        // ----- Nút Partager : sinh link công ty đang xem + filter hiện tại -----
        function buildShareUrl() {
            const params = new URLSearchParams();

            params.set('from', document.getElementById('filterFrom').value);
            params.set('to', document.getElementById('filterTo').value);
            params.set('granularity', document.getElementById('filterGranularity').value);

            const videoVal = document.getElementById('filterVideo').value;
            if (videoVal) {
                params.set('video_ads_id', videoVal);
            }

            const qs = params.toString();
            return VAD_CONFIG.shareBaseUrl + (qs ? '?' + qs : '');
        }

        document.getElementById('shareBtn').addEventListener('click', function() {
            if (!VAD_CONFIG.shareBaseUrl) {
                alert('Veuillez sélectionner une entreprise avant de partager.');
                return;
            }

            const shareUrl = buildShareUrl();

            const done = () => {
                const btn = document.getElementById('shareBtn');
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="voyager-check"></i> Lien copié !';
                setTimeout(() => {
                    btn.innerHTML = original;
                }, 2000);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shareUrl).then(done).catch(() => {
                    window.prompt('Copiez le lien :', shareUrl);
                });
            } else {
                window.prompt('Copiez le lien :', shareUrl);
            }
        });
    </script>
@stop