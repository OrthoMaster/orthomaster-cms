@extends('voyager::master')

@section('page_title', 'Calendrier de travail')

@section('page_header')
    <div class="container-fluid">
        <h3 class="page-title">
            <i class="voyager-calendar"></i> Calendrier de travail
        </h3>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')
        <form id="action-form" action="{{ route('voyager.dashboard.staff.calendar') }}" method="GET">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-bordered">
                        <div class="panel-body row">
                            <div class="col-md-12">
                                <label for="sale">Employe</label>
                                <select id="sale" class="form-control select2" name="sale_id">
                                    <option value="">---</option>

                                    @foreach ($sales as $s)
                                        <option value="{{ $s->id }}" @selected(request()->get('sale_id') == $s->id || $selectedSale->id == $s->id)>
                                            {{ $s->name . ' ' . $s->first_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="display_name">Mois</label>
                                <select id="months" class="form-control select2" name="month">
                                    <option value="">---</option>

                                    @foreach ($months as $month)
                                        <option value="{{ $month }}" @selected(request()->get('month') == $month || $selectedMonth == $month)>
                                            {{ $month }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12" style="margin-bottom: 0">
                                <a class="btn btn-sm btn-warning"
                                    href="{{ route('voyager.dashboard.staff') }}">
                                    Retourner "Aperçu"
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    Voir Calendrier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title">{{ $selectedSale->name . ' ' . $selectedSale->first_name }}
                                <small>{{ $selectedMonth }}</small>
                            </h3>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-sm-2 bg-info">Lun</div>
                                <div class="col-sm-2 bg-info">Mar</div>
                                <div class="col-sm-2 bg-info">Mer</div>
                                <div class="col-sm-2 bg-info">Jeu</div>
                                <div class="col-sm-2 bg-info">Ven</div>
                                <div class="col-sm-1 bg-warning">Sam</div>
                                <div class="col-sm-1 bg-warning">Dim</div>
                                @foreach ($days as $day)
                                    @if ($loop->first)
                                        @if ($day['day_of_week'] == 0)
                                            <div class="col-sm-11"></div>
                                        @elseif ($day['day_of_week'] == 6)
                                            <div class="col-sm-10"></div>
                                        @else
                                            <div class="{{ 'col-sm-' . ($day['day_of_week'] - 1) * 2 }}"></div>
                                        @endif
                                    @endif
                                    @if ($day['day_of_week'] == 0 || $day['day_of_week'] == 6)
                                        <div class="col-sm-1">
                                        @else
                                            <div class="col-sm-2">
                                    @endif
                                    <label>
                                        <input type="checkbox" name="days[]" value="{{ $day['date'] }}"
                                            {{ $day['is_checked'] ? 'checked' : '' }}
                                            {{ !$day['can_uncheck'] ? 'disabled' : '' }}>
                                        {{ $day['day'] }}
                                        @if (!$day['can_uncheck'])
                                            🔒 {{-- Đã có status khác 0, không thể xoá --}}
                                        @endif
                                    </label>
                            </div>
                            @endforeach
                        </div>
                        <div class="panel-footer">
                            <button type="button" class="btn btn-warning pull-right" onclick="return syncData();">
                                Mise à jour
                            </button>
                        </div>
                    </div>
                </div>
            </div>
    </div>
    </form>
    </div>
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
    </script>
@stop
