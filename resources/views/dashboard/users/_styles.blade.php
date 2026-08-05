<style>
    /* --- Colonnes de même hauteur --- */
    .row-eq-height { display: flex; flex-wrap: wrap; }
    .row-eq-height > [class*='col-'] { display: flex; }
    .row-eq-height .panel { width: 100%; display: flex; flex-direction: column; }
    .row-eq-height .panel-body { flex: 1 1 auto; }

    @media (max-width: 991px) {
        .row-eq-height { display: block; }
        .row-eq-height > [class*='col-'] { display: block; }
    }

    /* --- Cartes KPI --- */
    .kpi-panel { position: relative; overflow: hidden; border-left: 4px solid #ddd; }
    .kpi-panel .panel-body { padding: 18px 20px; }

    .kpi-label {
        font-size: 12px; font-weight: 600; text-transform: uppercase;
        letter-spacing: .04em; color: #8a9199; margin-bottom: 6px;
        padding-right: 40px; line-height: 1.35; min-height: 32px;
    }

    .kpi-value { font-size: 30px; font-weight: 700; line-height: 1.1; }
    .kpi-icon { position: absolute; top: 16px; right: 16px; font-size: 34px; opacity: .16; }

    .kpi-blue   { border-left-color: #22A7F0; }
    .kpi-purple { border-left-color: #955BA5; }
    .kpi-green  { border-left-color: #2ECC71; }

    .kpi-blue   .kpi-value, .kpi-blue   .kpi-icon { color: #22A7F0; }
    .kpi-purple .kpi-value, .kpi-purple .kpi-icon { color: #955BA5; }
    .kpi-green  .kpi-value, .kpi-green  .kpi-icon { color: #2ECC71; }

    /* --- Compteurs dans l'en-tête du panneau période --- */
    .panel-stats { display: inline-flex; gap: 22px; vertical-align: middle; }
    .panel-stat { line-height: 1.15; }
    .panel-stat span {
        display: block; font-size: 11px; text-transform: uppercase;
        letter-spacing: .03em; color: #8a9199; font-weight: 600;
    }
    .panel-stat strong { font-size: 20px; }
    .panel-stat.is-opens strong { color: #22A7F0; }
    .panel-stat.is-signups strong { color: #F39C12; }

    .panel-heading .filters-inline { margin-top: 12px; }
    .panel-heading .filters-inline label {
        font-size: 11px; text-transform: uppercase; color: #8a9199;
    }

    /* --- Panneau "période" : teinte distincte des panneaux graphiques --- */
    .panel-period { border-color: #d6dce6; }

    .panel-period > .panel-heading {
        background: #eef3f9;
        border-bottom: 1px solid #d6dce6;
    }

    .panel-period > .panel-body { background: #f7f9fc; }

    .panel-period .filters-inline {
        margin-top: 12px;
        margin-left: 0;
        margin-right: 0;
    }
</style>