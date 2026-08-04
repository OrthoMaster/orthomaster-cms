<div class="row row-eq-height">
    <div class="col-lg-5 col-md-5 col-sm-12">
        <div class="panel panel-bordered kpi-panel kpi-blue">
            <div class="panel-body">
                <i class="voyager-play kpi-icon"></i>
                <div class="kpi-label">Vidéos</div>
                <div class="kpi-split">
                    <div class="kpi-part">
                        <div class="kpi-sub">Totales</div>
                        <div class="kpi-value">{{ number_format($summary['total_videos'], 0, ',', ' ') }}</div>
                    </div>
                    <div class="kpi-part">
                        <div class="kpi-sub">Période</div>
                        <div class="kpi-value">{{ number_format($summary['period_videos'], 0, ',', ' ') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 col-md-5 col-sm-12">
        <div class="panel panel-bordered kpi-panel kpi-green">
            <div class="panel-body">
                <i class="voyager-eye kpi-icon"></i>
                <div class="kpi-label">Vues</div>
                <div class="kpi-split">
                    <div class="kpi-part">
                        <div class="kpi-sub">Totales</div>
                        <div class="kpi-value">{{ number_format($summary['total_views_all'], 0, ',', ' ') }}</div>
                    </div>
                    <div class="kpi-part">
                        <div class="kpi-sub">Période</div>
                        <div class="kpi-value">{{ number_format($summary['total_views_period'], 0, ',', ' ') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-2 col-sm-12">
        <div class="panel panel-bordered kpi-panel kpi-red">
            <div class="panel-body">
                <i class="voyager-people kpi-icon"></i>
                <div class="kpi-label">Spectateurs</div>
                <div class="kpi-split">
                    <div class="kpi-part">
                        <div class="kpi-sub">Période</div>
                        <div class="kpi-value">{{ number_format($summary['total_viewers'], 0, ',', ' ') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>