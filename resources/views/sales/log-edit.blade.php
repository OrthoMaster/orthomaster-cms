@extends('voyager::master')

@section('page_title', 'Formulaire suivi')

@section('page_header')
    <div class="container-fluid">
        <h3 class="page-title">
            <i class="voyager-list"></i> Formulaire suivi
        </h3>
        <a class="btn btn-sm btn-warning" href="{{ route('voyager.dashboard.staff') }}">
            Retourner "Aperçu"
        </a>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        <form id="action-form" method="POST" action="{{ route('kpi.log.update', $log->id) }}">
            @csrf
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h4 class="panel-title">{{ $log->log_date->format('d/m/Y') }}</h4>
                </div>
                <div class="panel-body bg-info">
                    {{-- CALL --}}
                    <div class="col-md-5">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title">APPELS</h3>
                            </div>
                            <div class="panel-body row">
                                <div class="col-md-4">
                                    <label>Prospect</label>
                                    <input type="number" name="calls_new_count" id="calls_new_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->calls_new_count }}" />
                                </div>
                                <div class="col-md-4">
                                    <label>Relance</label>
                                    <input type="number" name="calls_back_count" id="calls_back_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->calls_back_count }}" />
                                </div>
                                <div class="col-md-4">
                                    <label class="font-weigth-600">Total</label>
                                    <input type="number" id="calls_count" disabled
                                        class="form-control text-right"
                                        value="{{ $log->calls_count }}" />
                                </div>
                                <div class="col-md-12">
                                    <hr>
                                    <h5 style="margin-bottom: 0px">Résultat</h5>
                                </div>
                                <div class="col-md-4">
                                    <label>RDV DÉMO pris</label>
                                    <input type="number" name="appointments_count" id="appointments_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->appointments_count }}" />
                                </div>
                                <div class="col-md-4">
                                    <label>À relancer</label>
                                    <input type="number" name="calls_result_back_count" id="calls_result_back_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->calls_result_back_count }}" />
                                </div>
                                <div class="col-md-4">
                                    <label>Perdu</label>
                                    <input type="number" id="calls_lost" disabled
                                        class="form-control text-right"
                                        value="{{ $log->calls_count - $log->calls_result_back_count - $log->appointments_count }}" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DEMO --}}
                    <div class="col-md-7">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title">DÉMO</h3>
                            </div>
                            <div class="panel-body row">
                                <div class="col-md-4">
                                    <label class="font-weigth-600">Nb DÉMO prévu</label>
                                    <input type="number" name="attended_count" id="attended_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->attended_count }}" />
                                </div>
                                <div class="col-md-12">
                                    <hr>
                                    <h5 style="margin-bottom: 0px">Résultat</h5>
                                </div>
                                <div class="col-md-3">
                                    <label>Déclaration formation</label>
                                    <input type="number" name="attended_3_count" id="attended_3_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->attended_3_count }}" />
                                </div>
                                <div class="col-md-3">
                                    <label>Réporté</label>
                                    <input type="number" name="attended_1_count" id="attended_1_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->attended_1_count }}" />
                                </div>
                                <div class="col-md-3">
                                    <label>Décision en attente</label>
                                    <input type="number" name="attended_2_count" id="attended_2_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->attended_2_count }}" />
                                </div>
                                <div class="col-md-3">
                                    <label>Perdu</label>
                                    <input type="number" id="demo_lost" disabled
                                        class="form-control text-right"
                                        value="{{ $log->attended_count - $log->attended_1_count - $log->attended_2_count - $log->attended_3_count }}" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- OTHERS --}}
                    <div class="col-md-5">
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h3 class="panel-title">INSCRIPTION</h3>
                            </div>
                            <div class="panel-body row">
                                <div class="col-md-5">
                                    <label>Préinscription DIPLOME</label>
                                    <input type="number" name="preinscriptions_count" id="preinscriptions_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->preinscriptions_count }}" />
                                </div>
                                <div class="col-md-7">
                                    <label>Finalisations (facture/certificat) sur espace OPCO</label>
                                    <input type="number" name="final_opco_dossiers_count" id="final_opco_dossiers_count"
                                        class="form-control text-right" min="0"
                                        value="{{ $log->final_opco_dossiers_count }}" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-lg btn-primary">
                        Soumettre
                    </button>
                </div>
            </div>
        </form>
    </div>
@stop

@section('javascript')
<script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Helpers ──────────────────────────────────────────────────────────
        function val(id) {
            return Math.max(0, parseInt(document.getElementById(id).value) || 0);
        }

        function setVal(id, v) {
            document.getElementById(id).value = Math.max(0, v);
        }

        // Prevent negative on manual input
        document.querySelectorAll('input[type="number"]').forEach(function (input) {
            input.addEventListener('input', function () {
                if (parseInt(this.value) < 0) this.value = 0;
            });
        });

        // ── Rule 1 : Appels Total = Prospect + Relance ────────────────────────
        function updateCallsTotal() {
            setVal('calls_count', val('calls_new_count') + val('calls_back_count'));
            updateCallsLost(); // total changed → recalculate perdu
        }

        // ── Rule 2 : Appels Perdu = Total − RDV DÉMO pris − À relancer ───────
        function updateCallsLost() {
            setVal('calls_lost', val('calls_count') - val('appointments_count') - val('calls_result_back_count'));
        }

        // ── Rule 3 : Démo Perdu = Nb prévu − (formation + réporté + attente) ──
        function updateDemoLost() {
            setVal('demo_lost',
                val('attended_count') -
                val('attended_3_count') -
                val('attended_1_count') -
                val('attended_2_count')
            );
        }

        // ── Event listeners ───────────────────────────────────────────────────

        // Appels Total triggers
        ['calls_new_count', 'calls_back_count'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', updateCallsTotal);
        });

        // Appels Perdu triggers
        ['appointments_count', 'calls_result_back_count'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', updateCallsLost);
        });

        // Démo Perdu triggers
        ['attended_count', 'attended_3_count', 'attended_1_count', 'attended_2_count'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', updateDemoLost);
        });

    });
</script>
@stop