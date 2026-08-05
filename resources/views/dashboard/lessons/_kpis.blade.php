<div class="row row-eq-height">
    <div class="col-lg-5 col-md-5 col-sm-12">
        <div class="panel panel-bordered kpi-panel kpi-blue">
            <div class="panel-body">
                <i class="voyager-book kpi-icon"></i>
                <div class="kpi-label">Leçons</div>
                <div class="kpi-split">
                    <div class="kpi-part">
                        <div class="kpi-sub">Total des leçons publiées (toutes les formations)</div>
                        <div class="kpi-value">{{ number_format($summary['total_lessons'], 0, ',', ' ') }}</div>
                    </div>
                    <div class="kpi-part">
                        <div class="kpi-sub">Nombre de leçons vues sur la période sélectionnée</div>
                        <div class="kpi-value">{{ number_format($summary['period_lessons'], 0, ',', ' ') }}</div>
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
                        <div class="kpi-sub">Nombre de vues totale sur toutes les leçons publiées</div>
                        <div class="kpi-value">{{ number_format($summary['total_views_all'], 0, ',', ' ') }}</div>
                    </div>
                    <div class="kpi-part">
                        <div class="kpi-sub">Nombre de vues totale sur la période sélectionnée</div>
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
                <div class="kpi-label">Utilisateurs</div>
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