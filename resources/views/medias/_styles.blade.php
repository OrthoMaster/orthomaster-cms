<style>
    /* --- Colonnes de même hauteur (KPI + charts) --- */
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
        letter-spacing: .04em; color: #8a9199; margin-bottom: 6px; padding-right: 40px;
    }

    .kpi-value { font-size: 30px; font-weight: 700; line-height: 1.1; }
    .kpi-icon { position: absolute; top: 16px; right: 16px; font-size: 34px; opacity: .16; }

    .kpi-blue   { border-left-color: #22A7F0; }
    .kpi-purple { border-left-color: #955BA5; }
    .kpi-green  { border-left-color: #2ECC71; }
    .kpi-red    { border-left-color: #E74C3C; }

    .kpi-blue   .kpi-value, .kpi-blue   .kpi-icon { color: #22A7F0; }
    .kpi-purple .kpi-value, .kpi-purple .kpi-icon { color: #955BA5; }
    .kpi-green  .kpi-value, .kpi-green  .kpi-icon { color: #2ECC71; }
    .kpi-red    .kpi-value, .kpi-red    .kpi-icon { color: #E74C3C; }

    /* --- KPI à deux chiffres --- */
    .kpi-split { display: flex; }
    .kpi-part { flex: 1 1 50%; }
    .kpi-part + .kpi-part { border-left: 1px solid #eceff3; padding-left: 14px; }

    .kpi-sub {
        font-size: 11px; text-transform: uppercase; letter-spacing: .03em;
        color: #a4abb3; margin-bottom: 2px;
    }

    .kpi-split .kpi-value { font-size: 24px; }
</style>