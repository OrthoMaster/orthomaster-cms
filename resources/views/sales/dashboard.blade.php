@extends('voyager::master')

@section('page_title', 'Formulaire suivi')

@section('page_header')
    <div class="container-fluid">
        <h3 class="page-title">
            <i class="voyager-people"></i> Indicateurs clés de performance (KPI)
        </h3>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            @if (!$isSale)
                <div class="col-md-8">
                @else
                    <div class="col-md-5">
            @endif
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <form id="action-form" class="row" action="{{ route('voyager.dashboard.staff') }}" method="GET">
                        @csrf
                        @if (!$isSale)
                            <div class="col-md-6">
                                <label for="sale">Employe</label>
                                <select id="sale" class="form-control select2" name="sale_id">
                                    <option value="">OrthoMaster Équipe</option>

                                    @foreach ($sales as $s)
                                        <option value="{{ $s->id }}" @selected(request()->get('sale_id') == $s->id)>
                                            {{ $s->name . ' ' . $s->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if (!$isSale)
                            <div class="col-md-3">
                            @else
                                <div class="col-md-6">
                        @endif
                        <label for="display_name">De</label>
                        <input type="date" class="form-control" name="from" placeholder="dd/mm/yyyy"
                            value="{{ request('from') }}" required>
                </div>
                @if (!$isSale)
                    <div class="col-md-3">
                    @else
                        <div class="col-md-6">
                @endif
                <label for="display_name">À</label>
                <input type="date" class="form-control" name="to" placeholder="dd/mm/yyyy"
                    value="{{ request('to') }}" required>
            </div>
            <div class="col-md-12" style="margin-bottom: 0">
                <button type="submit" class="btn btn-primary pull-right">
                    Exporter
                </button>
                @if (!$isSale && request('from') && request('to'))
                    <button type="button" class="btn btn-danger pull-right" style="margin-right: 5px"
                        onclick="return syncData();">
                        Mise à jour
                    </button>
                @endif
                @if (!$isSale)
                    <a class="btn btn-warning pull-right" style="margin-right: 5px"
                        href="{{ route('voyager.dashboard.staff.calendar') }}">
                        Calendrier de travail
                    </a>
                @endif
            </div>
            </form>
        </div>
    </div>
    </div>
    </div>
    <h3>
        {{ request('from') ? \Carbon\Carbon::parse(request('from'))->format('d/m/Y') : '' }} -
        {{ request('to') ? \Carbon\Carbon::parse(request('to'))->format('d/m/Y') : '' }}
    </h3>
    <h4>Jours restants sur la période: {{ $cntLogs - $cntPastLogs }} / {{ $cntLogs }} jours</h4>
    <div class="row">
        {{-- CALL --}}
        <div class="col-md-4">
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title">APPELS</h3>
                </div>
                <div class="panel-body row">
                    <div class="col-md-12 text-center">
                        <h3>
                            @if ($sumCall >= ($currentKpi->calls_target * $cntLogs ?? 0) * 0.8)
                                <span class="text-success">{{ $sumCall }}</span>
                            @elseif ($sumCall >= ($currentKpi->calls_target * $cntLogs ?? 0) * 0.5)
                                <span class="text-warning">{{ $sumCall }}</span>
                            @else
                                <span class="text-danger">{{ $sumCall }}</span>
                            @endif
                            / {{ $currentKpi->calls_target * $cntLogs ?? 0 }}
                        </h3>
                        <span>Nb appels p/r objectif de la période</span>
                        <hr />
                    </div>

                    <div class="col-md-6">
                        <h5>Détails</h5>
                        <div><small>Appel prospect: </small> <b>{{ $sumCallNew }}</b> </div>
                        <div><small>Appel relance: </small> <b>{{ $sumCallBack }}</b> </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Résultat</h5>
                        <div><small>À relancer: </small> <b>{{ $sumCallResultBack }}</b> </div>
                        <div><small>Perdu: </small> <b>{{ $sumCall - $sumAptm - $sumCallResultBack }}</b> </div>
                        <div><small>Rdv DÉMO pris: </small> <b class="text-success">{{ $sumAptm }}</b> /
                            <b>{{ $currentKpi->appointments_target * $cntLogs ?? 0 }}</b>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <h5>Aperçu</h5>
                        <div>
                            <small>En moyenne par jour: </small>
                            <b class="float-right">
                                {{ $avgCall }} / {{ $currentKpi->calls_target ?? 0 }}
                            </b>
                        </div>

                        <div>
                            <small>Performance: </small>
                            @if ($avgCall >= ($currentKpi->calls_target ?? 0) * 0.8)
                                <b class="text-success float-right">
                                @elseif ($avgCall >= ($currentKpi->calls_target ?? 0) * 0.5)
                                    <b class="text-warning float-right">
                                    @else
                                        <b class="text-danger float-right">
                            @endif
                            {{ round(($avgCall / ($currentKpi->calls_target ?: 1)) * 100, 2) . '% ' }}</b>
                        </div>

                        <div>
                            <small>Reste à faire: </small>
                            <b class="text-warning float-right">
                                {{ ($currentKpi->calls_target * $cntLogs ?? 0) - $sumCall }}</b>
                        </div>

                        <div>
                            <small>Transformation Appels → Démo: </small>
                            <b class="float-right">
                                <span class="text-info">{{ round(($avgAptm / ($avgCall ?: 1)) * 100, 2) . '%' }}</span>
                                /
                                {{ round(($currentKpi->appointments_target / ($currentKpi->calls_target ?: 1)) * 100, 2) . '%' }}</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DEMO --}}
        <div class="col-md-3">
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title">DÉMO</small>
                    </h3>
                </div>
                <div class="panel-body row">
                    <div class="col-md-12 text-center">
                        <h3>
                            @if ($sumAten >= ($currentKpi->appointments_target * $cntLogs ?? 0) * 0.8)
                                <span class="text-success">{{ $sumAten }}</span>
                            @elseif ($sumAten >= ($currentKpi->appointments_target * $cntLogs ?? 0) * 0.5)
                                <span class="text-warning">{{ $sumAten }}</span>
                            @else
                                <span class="text-danger">{{ $sumAten }}</span>
                            @endif
                            / {{ $currentKpi->appointments_target * $cntLogs ?? 0 }}
                        </h3>
                        <span>Nb démo p/r objectif de la période</span>
                        <hr />
                    </div>
                    <div class="col-md-12">
                        <h5>Résultat</h5>
                        <div><small>Déclaration formation: </small> <b class="text-success">{{ $sumAten3 }}</b>
                            <b>/ {{ $currentKpi->attended_target * $cntLogs ?? 0 }}</b></div>
                        <div><small>Reporté: </small> <b>{{ $sumAten1 }}</b> </div>
                        <div><small>Décision en attente: </small> <b>{{ $sumAten2 }}</b> </div>
                        <div><small>Perdu: </small> <b>{{ $sumAten - $sumAten1 - $sumAten2 - $sumAten3 }}</b> </div>
                    </div>
                    <div class="col-md-12">
                        
                        <h5>Aperçu</h5>
                        <div>
                            <small>En moyenne par jour: </small>
                            <b class="float-right">
                                {{ $avgAten }} / {{ $currentKpi->appointments_target ?? 0 }}
                            </b>
                        </div>
                        <div>
                            <small>Performance: </small>
                            @if ($avgAten >= ($currentKpi->attended_target ?? 0)* 0.8)
                                <b class="text-success float-right">
                            @elseif ($avgAten >= ($currentKpi->attended_target ?? 0)* 0.5)
                                <b class="text-warning float-right">
                            @else
                                <b class="text-danger float-right">
                            @endif
                            {{ round(($sumAten / ($currentKpi->attended_target * $cntLogs ?: 1)) * 100, 2) . '%' }}</b>
                        </div>
                        <div>
                            <small>Reste à faire: </small>
                            <b class="text-warning float-right">
                                {{ ($currentKpi->appointments_target * $cntLogs ?? 0) - $sumAten }}</b>
                        </div>
                        <div>
                            <small>Transformation Démo → Inscription: </small>
                            <b class="float-right">
                                <span class="text-info">{{ round(($sumAten3 / ($sumAten ?: 1)) * 100, 2) . '%' }}</span>
                                /
                                {{ round(($currentKpi->attended_target / ($currentKpi->appointments_target ?: 1)) * 100, 2) . '%' }}</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CA --}}
        <div class="col-md-5">
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title">INSCRIPTION & CA</h3>
                </div>
                <div class="panel-body row">
                    <div class="col-md-12 text-center">
                        <h3>
                            @if ($totalOpcoContractIncome + $totalFifplContractIncome >= ($currentKpi->revenue_target * $cntLogs ?? 0) * 0.8)
                                <span class="text-success">{{ $totalOpcoContractIncome + $totalFifplContractIncome }}
                                    €</span>
                            @else
                                <span class="text-danger">{{ $totalOpcoContractIncome + $totalFifplContractIncome }}
                                    €</span>
                            @endif
                            / {{ $currentKpi->revenue_target * $cntLogs ?? 0 }} €
                        </h3>
                        <span>CA engagé p/r objectif de la période</span>
                        <hr />
                    </div>
                    <div class="col-md-3">
                        <h5>Inscription</h5>
                        <div><small>Nv Esp.Pro: </small>
                            @if ($sumComp >= ($currentKpi->registrations_target * $cntLogs ?? 0) * 0.8)
                                <b class="text-success">{{ $sumComp }}</b>
                            @elseif ($sumComp >= ($currentKpi->registrations_target * $cntLogs ?? 0) * 0.5)
                                <b class="text-warning">{{ $sumComp }}</b>
                            @else
                                <b class="text-danger">{{ $sumComp }}</b>
                            @endif <b>/ {{ $currentKpi->registrations_target * $cntLogs }}</b>
                        </div>
                        <div><small>Nv Comptes: </small> <b>{{ $sumReg }}</b> </div>
                    </div>
                    <div class="col-md-3">
                        <h5>OPCO</h5>
                        <div><small>CA: </small> <b>{{ $totalOpcoContractIncome }}</b> €</div>
                        <div><small>Nv devis signés: </small> <b>{{ $totalOpcoContracts }}</b> </div>
                        <div><small>Nv personnes signées: </small> <b>{{ $totalOpcoContractUser }}</b> </div>
                        <div><small>Finalisations: </small> <b>{{ $sumFinalDoss }}</b></div>
                    </div>
                    <div class="col-md-3">
                        <h5>FIFPL</h5>
                        <div><small>CA: </small> <b>{{ $totalFifplContractIncome }}</b> €</div>
                        <div><small>Nv devis signés: </small> <b>{{ $totalFifplContracts }}</b> </div>
                    </div>
                    <div class="col-md-3">
                        <h5>Diplome</h5>
                        <div><small>Préinscription: </small> <b>{{ $sumPreIns }}</b></div>
                    </div>
                    <div class="col-md-12">
                        <h5>Aperçu</h5>
                        <div>
                            <small>Performance: </small>
                            @if (($totalOpcoContractIncome + $totalFifplContractIncome) >= ($currentKpi->attended_target * $cntLogs ?? 0) * 0.8)
                                <b class="text-success float-right">
                            @elseif (($totalOpcoContractIncome + $totalFifplContractIncome) >= ($currentKpi->attended_target * $cntLogs ?? 0) * 0.5)
                                <b class="text-warning float-right">
                            @else
                                <b class="text-danger float-right">
                            @endif
                            {{ round((($totalOpcoContractIncome + $totalFifplContractIncome) / ($currentKpi->revenue_target * $cntLogs ?: 1)) * 100, 2) . '%' }}
                            </b>
                        </div>
                        <div>
                            <small>Reste à faire: </small>
                            <b class="text-warning float-right">
                                {{ ($currentKpi->revenue_target * $cntLogs ?? 0) - ($totalOpcoContractIncome + $totalFifplContractIncome) }} €</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            {{-- Logs --}}
            @if (!$isAll && isset($kpiDailyLogs) && $kpiDailyLogs->count() > 0)
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title">Rapports quotidiens
                            <sub>{{ $sale->name . ' ' . $sale->first_name }}</sub>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div>Total <b>{{ $kpiDailyLogs->total() }}/{{ $kpiDailyLogs->count() }}</b>
                                        {{ $kpiDailyLogs->count() > 1 ? 'rapports' : 'rapport' }}
                                        ---
                                        <span>Page
                                            <b>{{ $kpiDailyLogs->currentPage() }}/{{ $kpiDailyLogs->lastPage() }}</b></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-navigation">
                                        {{ $kpiDailyLogs->links() }}
                                    </div>
                                </div>
                            </div>
                            <table class="table database-tables">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Appel</th>
                                        <th>Appel resultat</th>
                                        <th>Démo</th>
                                        <th>Autres</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($kpiDailyLogs as $log)
                                        <tr class="{{ $log->log_date->isSameDay(today()) ? 'bg-info' : '' }}">
                                            <td>{{ $log->log_date->format('d/m/Y') }}</td>
                                            <td>
                                                <div><small>Total: </small> <b>{{ $log->calls_count }}</b></div>
                                                <div><small>Prospect: </small> {{ $log->calls_new_count }}</div>
                                                <div><small>Relance: </small> {{ $log->calls_back_count }}</div>
                                            </td>
                                            <td>
                                                <div><small>À relancer: </small>
                                                    {{ $log->calls_result_back_count }}
                                                </div>
                                                <div><small>Perdu: </small>
                                                    {{ $log->calls_count - $log->appointments_count - $log->calls_result_back_count }}
                                                </div>
                                                <div><small>Rdv Démo pris: </small> {{ $log->appointments_count }}
                                                </div>
                                            </td>
                                            <td>
                                                <div><small>Total: </small> <b>{{ $log->attended_count }}</b></div>
                                                <div><small>Déclaration formation: </small> {{ $log->attended_3_count }}
                                                </div>
                                                <div><small>Reporté: </small> {{ $log->attended_1_count }}</div>
                                                <div><small>Décision en attente: </small>
                                                    {{ $log->attended_2_count }}
                                                </div>
                                                <div><small>Perdu: </small>
                                                    {{ $log->attended_count - $log->attended_1_count - $log->attended_2_count - $log->attended_3_count }}
                                                </div>
                                            </td>

                                            <td>
                                                <div><small>Préinscription Diplome: </small>
                                                    {{ $log->preinscriptions_count }}</div>
                                                <div><small>Finalisations sur espace OPCO: </small>
                                                    {{ $log->final_opco_dossiers_count }}</div>
                                            </td>

                                            <td>
                                                <div>
                                                    @if ($log->status == '0' && $log->calls_count == 0)
                                                        <span class="label label-info">Init</span>
                                                    @elseif ($log->status == '0')
                                                        <span class="label label-warning">Rempli</span>
                                                    @elseif ($log->status == '1')
                                                        <span class="label label-success">Confirmé</span>
                                                    @endif
                                                </div>
                                                <div style="margin-top: 10px"><small>Dernière mise à jour</small>
                                                </div>
                                                <div>{{ $log->updated_at?->format('d-m-Y H:i') ?? '-' }}</div>
                                            </td>
                                            <td>
                                                @if (!$isSale && $log->status == '0')
                                                    <button type="button" class="btn btn-sm btn-info"
                                                        style="margin-right: 2px"
                                                        onclick="return confirmLog('{{ $log->id }}');">
                                                        Confirmer
                                                    </button>
                                                @endif
                                                @if (($isSale && $log->status == '0') || !$isSale)
                                                <a class="btn btn-sm btn-warning"
                                                    href="{{ url('admin/kpi-daily-logs/' . $log->id . '/edit') }}">
                                                    Modifier
                                                </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="table-navigation" style="margin-bottom: 2em">
                                {{ $kpiDailyLogs->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    </div>

    <form id="confirm-form" method="POST" action="">@csrf</form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/custom-pagination.css') }}" />
@stop

@section('javascript')
    <script>
        function syncData() {
            if (confirm("Êtes-vous sûr de vouloir mettre à jour les données ?")) {
                let form = document.getElementById('action-form');
                form.method = "POST";
                form.submit();
                form.method = "GET";
            }
        }

        function confirmLog($id) {
            if (confirm("Êtes-vous sûr de confirmer ce rapport ?")) {
                var url = "{{ route('kpi.log.confirm', ['id' => 'logId']) }}";
                url = url.replace('logId', $id);

                let form = document.getElementById('confirm-form');
                form.action = url;
                form.method = "POST";
                form.submit();
            }
        }
    </script>
@stop
