<div class="row row-eq-height">
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-bordered kpi-panel kpi-blue">
            <div class="panel-body">
                <i class="voyager-play kpi-icon"></i>
                <div class="kpi-label">Vidéos</div>
                <div class="kpi-value">{{ number_format($summary['total_videos'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-bordered kpi-panel kpi-purple">
            <div class="panel-body">
                <i class="voyager-eye kpi-icon"></i>
                <div class="kpi-label">Vues totales (historique)</div>
                <div class="kpi-value">{{ number_format($summary['total_views_all'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-bordered kpi-panel kpi-green">
            <div class="panel-body">
                <i class="voyager-eye kpi-icon"></i>
                <div class="kpi-label">Vues sur la période</div>
                <div class="kpi-value">{{ number_format($summary['total_views_period'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="panel panel-bordered kpi-panel kpi-red">
            <div class="panel-body">
                <i class="voyager-people kpi-icon"></i>
                <div class="kpi-label">Spectateurs uniques</div>
                <div class="kpi-value">{{ number_format($summary['total_viewers'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
</div>