@extends('voyager::master')

@section('page_title', ' Rapports')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-file-text"></i> Rapports
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-12">

                {{-- ============ RAPPORTS DISPONIBLES ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-list"></i> Rapports disponibles</h3>
                    </div>
                    <div class="panel-body report-list">
                        @foreach ($reports as $key => $report)
                            <div class="report-item {{ $filters['report'] === $key ? 'is-active' : '' }}">
                                <div class="report-item-name">{{ $report['label'] }}</div>
                                <div class="report-item-desc">{{ $report['description'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ============ FILTRES ============ --}}
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="voyager-search"></i> Filtres</h3>
                    </div>
                    <div class="panel-body">
                        <form class="row" action="{{ route('dashboard.reports.index') }}" method="GET" id="report-form">
                            <div class="col-md-4">
                                <label for="report">Rapport</label>
                                <select class="form-control" name="report" id="report">
                                    @foreach ($reports as $key => $report)
                                        <option value="{{ $key }}" @selected($filters['report'] === $key)>
                                            {{ $report['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="from">De</label>
                                <input type="month" class="form-control" name="from" id="from"
                                    value="{{ $filters['from'] }}">
                            </div>

                            <div class="col-md-2">
                                <label for="to">À</label>
                                <input type="month" class="form-control" name="to" id="to"
                                    value="{{ $filters['to'] }}">
                            </div>

                            <div class="col-md-4" style="padding-top:24px">
                                <button type="submit" class="btn btn-primary">Appliquer</button>
                                <button type="button" class="btn btn-success" id="btn-export">
                                    <i class="voyager-file-text"></i> Excel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ============ TABLEAUX ============ --}}
                @foreach ($tables as $table)
                    @php
                        $columns = $table['columns'] ?? [];

                        // Rend une cellule selon le type déclaré pour sa colonne
                        $cell = function ($value, $index) use ($columns) {
                            if ($value === null) {
                                return '—';
                            }

                            return match ($columns[$index] ?? 'text') {
                                'money'   => number_format($value, 2, ',', ' ') . ' €',
                                'percent' => number_format($value, 1, ',', ' ') . ' %',
                                'decimal' => number_format($value, 1, ',', ' '),
                                'int'     => number_format($value, 0, ',', ' '),
                                default   => $value,
                            };
                        };

                        $alignRight = fn ($index) => ($columns[$index] ?? 'text') === 'text' ? '' : 'text-right';
                    @endphp

                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">{{ $table['title'] }}</h3>
                            @if (!empty($table['rows']))
                                <span class="report-count">
                                    {{ count($table['rows']) }}
                                    {{ count($table['rows']) > 1 ? 'lignes' : 'ligne' }}
                                </span>
                            @endif
                        </div>
                        <div class="panel-body">
                            @if (empty($table['rows']))
                                <div class="alert alert-warning" style="margin-bottom:0">
                                    <strong>Aucune donnée sur cette période.</strong>
                                </div>
                            @else
                                <div class="table-responsive report-scroll">
                                    <table class="table table-striped database-tables report-table">
                                        <thead>
                                            @if (!empty($table['groups']))
                                                @php $cursor = 0; @endphp
                                                <tr>
                                                    @foreach ($table['groups'] as $group)
                                                        <th class="{{ $group['span'] > 1 ? 'text-center' : $alignRight($cursor) }}"
                                                            colspan="{{ $group['span'] }}"
                                                            rowspan="{{ !empty($group['rowspan']) ? 2 : 1 }}">
                                                            {{ $group['label'] }}
                                                        </th>
                                                        @php $cursor += $group['span']; @endphp
                                                    @endforeach
                                                </tr>
                                                <tr>
                                                    @foreach ($table['headers'] as $i => $header)
                                                        @if ($header !== '')
                                                            <th class="{{ $alignRight($i) }}">{{ $header }}</th>
                                                        @endif
                                                    @endforeach
                                                </tr>
                                            @else
                                                <tr>
                                                    @foreach ($table['headers'] as $i => $header)
                                                        <th class="{{ $alignRight($i) }}">{{ $header }}</th>
                                                    @endforeach
                                                </tr>
                                            @endif
                                        </thead>
                                        <tbody>
                                            @foreach ($table['rows'] as $row)
                                                <tr>
                                                    @foreach (array_values($row) as $i => $value)
                                                        <td class="{{ $alignRight($i) }}">{{ $cell($value, $i) }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        @if (!empty($table['totals']))
                                            <tfoot>
                                                <tr>
                                                    @foreach (array_values($table['totals']) as $i => $value)
                                                        <td class="{{ $alignRight($i) }}">
                                                            {{ $i === 0 ? 'Total' : $cell($value, $i) }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        /* --- Liste des rapports : hauteur bornée, contenu défilant --- */
        .report-list {
            max-height: 180px;
            overflow-y: auto;
            padding: 0;
        }

        .report-item {
            padding: 12px 16px;
            border-left: 3px solid transparent;
        }

        .report-item + .report-item {
            border-top: 1px solid #f0f2f5;
        }

        .report-item.is-active {
            border-left-color: #22A7F0;
            background: #f7fbfe;
        }

        .report-item-name {
            font-size: 14px;
            font-weight: 600;
            color: #37474f;
        }

        .report-item.is-active .report-item-name {
            color: #22A7F0;
        }

        .report-item-desc {
            margin-top: 3px;
            font-size: 12px;
            color: #8a9199;
            line-height: 1.45;
        }

        /* --- Tableaux --- */
        .report-count {
            float: right;
            font-size: 12px;
            color: #8a9199;
        }

        /* Le détail peut être long : on borne la hauteur plutôt que de paginer */
        .report-scroll {
            max-height: 620px;
            overflow-y: auto;
        }

        .report-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fff;
        }

        .report-table tfoot td {
            font-weight: 700;
            border-top: 2px solid #22A7F0;
            background: #f2f4f7;
        }

        .report-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fff;
        }

        .report-table thead tr:nth-child(2) th {
            /* top: 38px; */
        }

        .report-table thead tr:first-child th {
            border-right: 1px solid #d6dce6;
        }

        .report-table thead tr:first-child th:last-child {
            border-right: none;
        }

        /* --- Bordures verticales : les colonnes se lisent mieux --- */
        .report-table th,
        .report-table td {
            border-right: 1px solid #e8ebef;
        }

        .report-table th:last-child,
        .report-table td:last-child {
            border-right: none;
        }

        /* La première colonne sert de repère : trait plus marqué */
        .report-table th:first-child,
        .report-table td:first-child {
            border-right: 1px solid #d6dce6;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {

            $('#report').select2({ width: '100%' });

            /* ---------- Export Excel : reprend les filtres courants ---------- */
            $('#btn-export').on('click', function () {
                window.location = '{{ route('dashboard.reports.export') }}?' + $('#report-form').serialize();
            });
        });
    </script>
@stop