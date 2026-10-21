@extends('voyager::master')

@php
    use App\Models\ExpertAvailabilityRule;
    use App\Models\ExpertOffer;
    use App\Services\Booking\ExpertBookingSetupService as Setup;
    use Illuminate\Support\Str;

    // Après une erreur de validation, on revient sur l'onglet du formulaire envoyé.
    $activeTab = old('_tab', $tab);
    $oldForm   = old('_form');

    $today = \Carbon\Carbon::now($resolved['timezone'])->toDateString();

    // Valeur propre à l'expert (vide = valeur système), HH:MM pour les heures.
    $own = function (string $key) use ($settings) {
        $value = $settings?->{$key};
        return in_array($key, ['morning_start', 'morning_end', 'afternoon_start', 'afternoon_end'], true) && $value
            ? substr($value, 0, 5)
            : $value;
    };
    $settingsValue = fn (string $key) => $oldForm === 'settings' ? old($key) : $own($key);

    $validity = function (ExpertAvailabilityRule $rule) {
        $from  = $rule->valid_from?->format('d/m/Y');
        $until = $rule->valid_until?->format('d/m/Y');
        if (!$from && !$until) return 'Toujours';
        if ($from && !$until) return 'Dès le ' . $from;
        if (!$from && $until) return "Jusqu'au " . $until;
        return 'Du ' . $from . ' au ' . $until;
    };

    // [libellé, icône, compteur ou null]
    $tabs = [
        'settings'     => ['Réglages', 'voyager-settings', null],
        'offers'       => ['Offres', 'voyager-wallet', $offers->count()],
        'availability' => ['Disponibilités', 'voyager-calendar', $rules->count()],
    ];
@endphp

@section('page_title', 'Rendez-vous — ' . $expert->name)

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-calendar"></i> {{ $expert->name }}
        @if ($resolved['enabled_flg'])
            <span class="booking-pill booking-pill--on">Réservation activée</span>
        @else
            <span class="booking-pill">Réservation désactivée</span>
        @endif
    </h1>
    <a href="{{ route('booking.experts.index') }}" class="btn btn-default">
        <i class="voyager-angle-left"></i> Tous les experts
    </a>
@stop

@section('css')
    <style>
        /* --- Badges --- */
        .booking-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid #d5d9df;
            color: #6b7280;
            font-size: 12px;
            line-height: 18px;
            font-weight: normal;
            vertical-align: middle;
            white-space: nowrap;
        }

        .booking-pill--on {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
            color: #1e8e4f;
        }

        .booking-pill--danger {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.08);
            color: #b03a2e;
        }

        /* --- Onglets --- */
        .stats-tabs {
            display: inline-flex;
            gap: 4px;
            margin-bottom: 18px;
            padding: 4px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        }

        .stats-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 20px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            transition: background .15s, color .15s;
        }

        .stats-tab:hover {
            color: #1d3557;
            background: #f4f7fb;
        }

        .stats-tab.is-active,
        .stats-tab.is-active:hover {
            background: #1d3557;
            color: #fff;
            box-shadow: 0 2px 6px rgba(29, 53, 87, .28);
        }

        .stats-tab-count {
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(0, 0, 0, .07);
            font-size: 11px;
            font-weight: 600;
        }

        .stats-tab.is-active .stats-tab-count {
            background: rgba(255, 255, 255, .22);
        }

        /* --- Structure --- */
        .booking-section + .booking-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eef0f3;
        }

        .booking-section h3 {
            margin: 0 0 6px;
            font-size: 17px;
        }

        .booking-section > .booking-intro {
            margin: 0 0 18px;
            color: #8a8f98;
        }

        .booking-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .booking-empty {
            padding: 30px !important;
            text-align: center;
            color: #8a8f98;
        }

        .booking-inline-form {
            display: inline;
        }

        .booking-muted {
            color: #8a8f98;
        }

        /* --- Réglages : blocs horaires --- */
        .booking-blocks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 15px;
        }

        .booking-block {
            padding: 15px;
            border: 1px solid #e4e7eb;
            border-radius: 6px;
        }

        .booking-block h4 {
            margin: 0 0 10px;
            font-size: 15px;
        }

        .booking-block__times {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-block__duration {
            margin: 8px 0 0;
            font-weight: 600;
            color: #1e8e4f;
        }

        .booking-block.is-invalid {
            border-color: #e74c3c;
        }

        .booking-block.is-invalid .booking-block__duration {
            color: #b03a2e;
        }

        /* --- Disponibilités : grille de la semaine --- */
        .booking-week-wrap {
            overflow-x: auto;
        }

        .booking-week {
            width: 100%;
            min-width: 760px;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 4px;
        }

        .booking-week th {
            padding: 6px;
            font-weight: 600;
            text-align: center;
            color: #4b5563;
        }

        .booking-week tbody th {
            width: 120px;
            text-align: left;
        }

        .booking-week tbody th small {
            display: block;
            font-weight: normal;
            color: #8a8f98;
        }

        .booking-week td {
            height: 64px;
            padding: 6px;
            vertical-align: top;
            border-radius: 6px;
            background: #f6f7f9;
        }

        .booking-week td.is-open {
            background: rgba(46, 204, 113, 0.12);
        }

        .booking-rule {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 4px;
            font-size: 12px;
            line-height: 16px;
            color: #1e5e3a;
        }

        .booking-rule + .booking-rule {
            margin-top: 4px;
        }

        .booking-rule.is-expired {
            color: #9ca3af;
            text-decoration: line-through;
        }

        .booking-rule__remove {
            padding: 0 4px;
            border: 0;
            background: none;
            color: #9ca3af;
            font-size: 15px;
            line-height: 16px;
        }

        .booking-rule__remove:hover,
        .booking-rule__remove:focus {
            color: #b03a2e;
        }

        /* --- Disponibilités : sélecteurs --- */
        .booking-choices {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .booking-choice input {
            position: absolute;
            opacity: 0;
        }

        .booking-choice span {
            display: inline-block;
            padding: 5px 14px;
            border: 1px solid #d5d9df;
            border-radius: 999px;
            cursor: pointer;
            user-select: none;
        }

        .booking-choice input:checked + span {
            border-color: #22a7f0;
            background: #22a7f0;
            color: #fff;
        }

        .booking-choice input:focus-visible + span {
            outline: 2px solid #22a7f0;
            outline-offset: 2px;
        }

        .booking-form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .booking-form-row .form-group {
            margin-bottom: 0;
        }

        .booking-scroll {
            max-height: 420px;
            overflow-y: auto;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">

        {{-- Les erreurs du formulaire d'offre s'affichent dans la modale. --}}
        @if ($errors->any() && $oldForm !== 'offer')
            <div class="alert alert-danger">
                <ul class="list-unstyled" style="margin: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ============ ONGLETS ============ --}}
        <div class="stats-tabs">
            @foreach ($tabs as $key => [$label, $icon, $count])
                <button type="button" class="stats-tab {{ $activeTab === $key ? 'is-active' : '' }}" data-tab="{{ $key }}">
                    <i class="{{ $icon }}"></i> {{ $label }}
                    @if ($count !== null)
                        <span class="stats-tab-count">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ---------- Réglages ---------- --}}
        <div class="stats-pane" data-pane="settings" @if ($activeTab !== 'settings') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <form method="POST" action="{{ route('booking.experts.settings.update', $expert->id) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_tab" value="settings">
                        <input type="hidden" name="_form" value="settings">

                        <div class="booking-section">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="enabled_flg" value="1"
                                        @checked($oldForm === 'settings' ? old('enabled_flg') : $resolved['enabled_flg'])>
                                    <strong>Accepter les demandes de rendez-vous</strong>
                                </label>
                            </div>
                            <p class="booking-muted">
                                Désactiver n'annule pas les rendez-vous existants : seules les nouvelles demandes sont bloquées.
                            </p>
                        </div>

                        <div class="booking-section">
                            <h3>Délais de réservation</h3>
                            <p class="booking-intro">Laissez vide pour utiliser la valeur par défaut.</p>

                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="booking-timezone">Fuseau horaire</label>
                                    <select name="timezone" id="booking-timezone" class="form-control">
                                        @foreach (DateTimeZone::listIdentifiers() as $zone)
                                            <option value="{{ $zone }}"
                                                @selected(($oldForm === 'settings' ? old('timezone') : $resolved['timezone']) === $zone)>
                                                {{ $zone }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="min_notice_days">Délai minimum avant le rendez-vous (jours)</label>
                                    <input type="number" min="1" max="{{ Setup::MAX_NOTICE_DAYS }}"
                                           name="min_notice_days" id="min_notice_days" class="form-control"
                                           value="{{ $settingsValue('min_notice_days') }}"
                                           placeholder="{{ $defaults['min_notice_days'] }}">
                                    <span class="help-block">
                                        Par défaut : {{ $defaults['min_notice_days'] }} {{ $defaults['min_notice_days'] > 1 ? 'jours' : 'jour' }}
                                        — 1 jour = réservable dès le lendemain matin.
                                    </span>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="max_advance_months">Réservation au plus tard dans (mois)</label>
                                    <input type="number" min="1" max="{{ Setup::MAX_ADVANCE_MONTHS }}"
                                           name="max_advance_months" id="max_advance_months" class="form-control"
                                           value="{{ $settingsValue('max_advance_months') }}"
                                           placeholder="{{ $defaults['max_advance_months'] }}">
                                    <span class="help-block">Par défaut : {{ $defaults['max_advance_months'] }} mois</span>
                                </div>
                            </div>
                        </div>

                        <div class="booking-section">
                            <h3>Horaires des demi-journées</h3>
                            <p class="booking-intro">
                                Chaque bloc dure entre {{ Setup::BLOCK_MIN_MINUTES / 60 }} et {{ Setup::BLOCK_MAX_MINUTES / 60 }} heures.
                                Laissez vide pour reprendre l'horaire par défaut.
                            </p>

                            <div class="booking-blocks">
                                @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                    <div class="booking-block">
                                        <h4>{{ $periodLabel }}</h4>
                                        <div class="booking-block__times">
                                            <input type="time" class="form-control" data-role="start"
                                                   name="{{ $period }}_start"
                                                   value="{{ $settingsValue($period . '_start') }}"
                                                   data-default="{{ $defaults[$period . '_start'] }}"
                                                   aria-label="Début {{ $periodLabel }}">
                                            <span>à</span>
                                            <input type="time" class="form-control" data-role="end"
                                                   name="{{ $period }}_end"
                                                   value="{{ $settingsValue($period . '_end') }}"
                                                   data-default="{{ $defaults[$period . '_end'] }}"
                                                   aria-label="Fin {{ $periodLabel }}">
                                        </div>
                                        <p class="booking-block__duration" data-role="duration"></p>
                                        <span class="help-block">
                                            Par défaut : {{ $defaults[$period . '_start'] }} – {{ $defaults[$period . '_end'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="booking-section">
                            <button type="submit" class="btn btn-primary">Enregistrer les réglages</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ---------- Offres ---------- --}}
        <div class="stats-pane" data-pane="offers" @if ($activeTab !== 'offers') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="booking-toolbar">
                        <p class="booking-muted" style="margin: 0;">
                            Les offres masquées restent attachées aux rendez-vous déjà pris.
                        </p>
                        <button type="button" class="btn btn-success" data-action="new-offer">
                            <i class="voyager-plus"></i> Nouvelle offre
                        </button>
                    </div>

                    <table class="table table-striped database-tables">
                        <thead>
                            <tr>
                                <th>Offre</th>
                                <th>Durée</th>
                                <th class="text-right">Prix</th>
                                <th>Statut</th>
                                <th class="text-center">Ordre</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($offers as $offer)
                                <tr>
                                    <td>
                                        <strong>{{ $offer->title }}</strong>
                                        @if ($offer->description)
                                            <div class="booking-muted">{{ Str::limit($offer->description, 120) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $offer->duration_label }}</td>
                                    <td class="text-right">{{ number_format((float) $offer->price, 2, ',', ' ') }} €</td>
                                    <td>
                                        @if ($offer->active_flg)
                                            <span class="booking-pill booking-pill--on">Active</span>
                                        @else
                                            <span class="booking-pill">Masquée</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $offer->order_dsp }}</td>
                                    <td class="text-right" style="white-space: nowrap;">
                                        <button type="button" class="btn btn-sm btn-primary" data-action="edit-offer"
                                                data-offer="{{ json_encode([
                                                    'id'           => $offer->id,
                                                    'title'        => $offer->title,
                                                    'description'  => $offer->description,
                                                    'blocks_count' => $offer->blocks_count,
                                                    'price'        => (float) $offer->price,
                                                    'order_dsp'    => $offer->order_dsp,
                                                    'active_flg'   => $offer->active_flg,
                                                ]) }}">
                                            Modifier
                                        </button>
                                        <a href="{{ route('booking.experts.offers.images.index', [$expert->id, $offer->id]) }}"
                                           class="btn btn-sm btn-default">
                                            <i class="voyager-photos"></i> Images ({{ $offer->images_count ?? 0 }})
                                        </a>
                                        <form method="POST" class="booking-inline-form"
                                              action="{{ route('booking.experts.offers.destroy', [$expert->id, $offer->id]) }}"
                                              data-confirm="Supprimer l'offre « {{ $offer->title }} » ?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="booking-empty">
                                        Aucune offre. Les utilisateurs ne peuvent pas réserver tant qu'aucune offre active n'existe.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ---------- Disponibilités ---------- --}}
        <div class="stats-pane" data-pane="availability" @if ($activeTab !== 'availability') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">

                    <div class="booking-section">
                        <h3>Semaine type</h3>
                        <p class="booking-intro">
                            Demi-journées ouvertes chaque semaine. Les exceptions ci-dessous priment sur cette grille.
                        </p>

                        <div class="booking-week-wrap">
                            <table class="booking-week">
                                <thead>
                                    <tr>
                                        <th></th>
                                        @foreach (ExpertAvailabilityRule::WEEKDAY_LABELS as $dayLabel)
                                            <th>{{ $dayLabel }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                        <tr>
                                            <th>
                                                {{ $periodLabel }}
                                                <small>{{ $resolved[$period . '_start'] }} – {{ $resolved[$period . '_end'] }}</small>
                                            </th>
                                            @foreach (ExpertAvailabilityRule::WEEKDAY_LABELS as $weekday => $dayLabel)
                                                @php
                                                    $cellRules = $rules->where('weekday', $weekday)->where('period', $period);
                                                    $isOpen    = $cellRules->contains(fn ($r) => !$r->valid_until || $r->valid_until->toDateString() >= $today);
                                                @endphp
                                                <td class="{{ $isOpen ? 'is-open' : '' }}">
                                                    @foreach ($cellRules as $rule)
                                                        @php $expired = $rule->valid_until && $rule->valid_until->toDateString() < $today; @endphp
                                                        <div class="booking-rule {{ $expired ? 'is-expired' : '' }}">
                                                            <span>{{ $validity($rule) }}</span>
                                                            <form method="POST" class="booking-inline-form"
                                                                  action="{{ route('booking.experts.rules.destroy', [$expert->id, $rule->id]) }}"
                                                                  data-confirm="Supprimer ce créneau ({{ $dayLabel }} {{ Str::lower($periodLabel) }}) ?">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="booking-rule__remove"
                                                                        title="Supprimer" aria-label="Supprimer">&times;</button>
                                                            </form>
                                                        </div>
                                                    @endforeach
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="booking-section">
                        <h3>Ajouter des créneaux récurrents</h3>
                        <p class="booking-intro">
                            Chaque jour coché est combiné avec chaque demi-journée cochée.
                        </p>

                        <form method="POST" action="{{ route('booking.experts.rules.store', $expert->id) }}">
                            @csrf
                            <input type="hidden" name="_tab" value="availability">
                            <input type="hidden" name="_form" value="rules">

                            @php
                                $oldWeekdays = $oldForm === 'rules' ? array_map('intval', (array) old('weekdays', [])) : [];
                                $oldPeriods  = $oldForm === 'rules' ? (array) old('periods', []) : [];
                            @endphp

                            <div class="form-group">
                                <label>Jours</label>
                                <div class="booking-choices">
                                    @foreach (ExpertAvailabilityRule::WEEKDAY_LABELS as $weekday => $dayLabel)
                                        <label class="booking-choice">
                                            <input type="checkbox" name="weekdays[]" value="{{ $weekday }}"
                                                @checked(in_array($weekday, $oldWeekdays, true))>
                                            <span>{{ $dayLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Demi-journées</label>
                                <div class="booking-choices">
                                    @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                        <label class="booking-choice">
                                            <input type="checkbox" name="periods[]" value="{{ $period }}"
                                                @checked(in_array($period, $oldPeriods, true))>
                                            <span>{{ $periodLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="booking-form-row">
                                <div class="form-group">
                                    <label for="valid_from">Valable du</label>
                                    <input type="date" name="valid_from" id="valid_from" class="form-control"
                                           value="{{ $oldForm === 'rules' ? old('valid_from') : '' }}">
                                </div>
                                <div class="form-group">
                                    <label for="valid_until">au</label>
                                    <input type="date" name="valid_until" id="valid_until" class="form-control"
                                           value="{{ $oldForm === 'rules' ? old('valid_until') : '' }}">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Ajouter les créneaux</button>
                                </div>
                            </div>
                            <span class="help-block">Dates vides : le créneau s'applique sans limite de durée.</span>
                        </form>
                    </div>

                    <div class="booking-section">
                        <h3>Exceptions</h3>
                        <p class="booking-intro">
                            Fermer une date (congés, congrès) ou ouvrir exceptionnellement une demi-journée hors semaine type.
                        </p>

                        <form method="POST" action="{{ route('booking.experts.exceptions.store', $expert->id) }}">
                            @csrf
                            <input type="hidden" name="_tab" value="availability">
                            <input type="hidden" name="_form" value="exceptions">

                            @php
                                $exOld    = $oldForm === 'exceptions';
                                $exType   = $exOld ? old('type', 'block') : 'block';
                                $exPeriod = $exOld ? old('period') : null;
                            @endphp

                            <div class="booking-form-row">
                                <div class="form-group">
                                    <label for="date_from">Du</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control" required
                                           min="{{ $today }}" value="{{ $exOld ? old('date_from') : '' }}">
                                </div>
                                <div class="form-group">
                                    <label for="date_to">au <span class="booking-muted">(facultatif)</span></label>
                                    <input type="date" name="date_to" id="date_to" class="form-control"
                                           min="{{ $today }}" value="{{ $exOld ? old('date_to') : '' }}">
                                </div>
                                <div class="form-group">
                                    <label for="period">Demi-journée</label>
                                    <select name="period" id="period" class="form-control">
                                        <option value="">Journée entière</option>
                                        @foreach (ExpertAvailabilityRule::PERIOD_LABELS as $period => $periodLabel)
                                            <option value="{{ $period }}" @selected($exPeriod === $period)>{{ $periodLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Action</label>
                                    <div class="booking-choices">
                                        <label class="booking-choice">
                                            <input type="radio" name="type" value="block" @checked($exType === 'block')>
                                            <span>Fermer</span>
                                        </label>
                                        <label class="booking-choice">
                                            <input type="radio" name="type" value="add" @checked($exType === 'add')>
                                            <span>Ouvrir</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </div>
                        </form>

                        <div class="booking-scroll" style="margin-top: 20px;">
                            <table class="table table-striped database-tables">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Demi-journée</th>
                                        <th>Action</th>
                                        <th class="text-right"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($exceptions as $exception)
                                        <tr>
                                            <td>{{ Str::ucfirst($exception->date->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}</td>
                                            <td>{{ $exception->period_label }}</td>
                                            <td>
                                                @if ($exception->type === 'block')
                                                    <span class="booking-pill booking-pill--danger">Fermé</span>
                                                @else
                                                    <span class="booking-pill booking-pill--on">Ouvert</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <form method="POST" class="booking-inline-form"
                                                      action="{{ route('booking.experts.exceptions.destroy', [$expert->id, $exception->id]) }}"
                                                      data-confirm="Supprimer cette exception ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="booking-empty">Aucune exception à venir.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== MODALE OFFRE ===================== --}}
    @php
        $offerOld = $oldForm === 'offer';
    @endphp
    <div class="modal fade" id="offer-modal" tabindex="-1" role="dialog" aria-labelledby="offer-modal-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" id="offer-form"
                      action="{{ route('booking.experts.offers.store', $expert->id) }}"
                      data-store-url="{{ route('booking.experts.offers.store', $expert->id) }}"
                      data-update-url="{{ route('booking.experts.offers.update', [$expert->id, '__ID__']) }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="offer-method">
                    <input type="hidden" name="_tab" value="offers">
                    <input type="hidden" name="_form" value="offer">
                    <input type="hidden" name="_offer_id" id="offer-id" value="{{ $offerOld ? old('_offer_id') : '' }}">

                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="offer-modal-title">Nouvelle offre</h4>
                    </div>

                    <div class="modal-body">
                        @if ($offerOld && $errors->any())
                            <div class="alert alert-danger">
                                <ul class="list-unstyled" style="margin: 0;">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="form-group">
                            <label for="offer-title">Titre</label>
                            <input type="text" name="title" id="offer-title" class="form-control" maxlength="150" required
                                   value="{{ $offerOld ? old('title') : '' }}" placeholder="Ex. Chirurgie du ligament croisé">
                        </div>

                        <div class="form-group">
                            <label for="offer-description">Description</label>
                            <textarea name="description" id="offer-description" class="form-control" rows="4"
                                      maxlength="5000">{{ $offerOld ? old('description') : '' }}</textarea>
                        </div>

                        <div class="row">
                            <div class="form-group col-sm-6">
                                <label for="offer-blocks">Durée</label>
                                <select name="blocks_count" id="offer-blocks" class="form-control">
                                    @for ($blocks = 1; $blocks <= Setup::MAX_BLOCKS_PER_OFFER; $blocks++)
                                        <option value="{{ $blocks }}"
                                            @selected((int) ($offerOld ? old('blocks_count', 1) : 1) === $blocks)>
                                            {{ ExpertOffer::durationLabel($blocks) }}
                                            ({{ $blocks }} {{ $blocks > 1 ? 'demi-journées' : 'demi-journée' }})
                                        </option>
                                    @endfor
                                </select>
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="offer-price">Prix</label>
                                <div class="input-group">
                                    <input type="number" name="price" id="offer-price" class="form-control"
                                           min="0" step="0.01" required value="{{ $offerOld ? old('price') : '' }}">
                                    <span class="input-group-addon">€</span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-group col-sm-6">
                                <label for="offer-order">Ordre d'affichage</label>
                                <input type="number" name="order_dsp" id="offer-order" class="form-control" min="0"
                                       value="{{ $offerOld ? old('order_dsp') : '' }}" placeholder="En dernier">
                            </div>
                            <div class="form-group col-sm-6">
                                <label>&nbsp;</label>
                                <div class="checkbox" style="margin-top: 0;">
                                    <label>
                                        <input type="checkbox" name="active_flg" value="1" id="offer-active"
                                            @checked($offerOld ? old('active_flg') : true)>
                                        Proposée aux utilisateurs
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="offer-submit">Créer l'offre</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            var BLOCK_MIN = {{ Setup::BLOCK_MIN_MINUTES }};
            var BLOCK_MAX = {{ Setup::BLOCK_MAX_MINUTES }};

            /* --- Onglets --- */
            function showTab(name) {
                var $tab = $('.stats-tab[data-tab="' + name + '"]');

                if (!$tab.length) return;

                $('.stats-tab').removeClass('is-active');
                $tab.addClass('is-active');

                $('.stats-pane').each(function () {
                    $(this).toggle($(this).data('pane') === name);
                });
            }

            $('.stats-tab').on('click', function () {
                var name = $(this).data('tab');

                // En paramètre plutôt qu'en ancre : les redirections du serveur
                // (?tab=) et un rechargement rouvrent ainsi le même onglet.
                var url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                history.replaceState(null, '', url);

                showTab(name);
            });

            $('#booking-timezone').select2({ width: '100%' });

            /* --- Durée des blocs --- */
            function toMinutes(value) {
                var parts = (value || '').split(':');
                return parts.length < 2 ? null : parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            }

            function formatDuration(minutes) {
                var h = Math.floor(minutes / 60);
                var m = minutes % 60;
                return h + ' h' + (m ? ' ' + (m < 10 ? '0' : '') + m : '');
            }

            // Calcul sur la valeur effective (saisie ou défaut), comme le fait la validation serveur.
            function refreshBlock($block) {
                var $start = $block.find('[data-role="start"]');
                var $end = $block.find('[data-role="end"]');
                var start = toMinutes($start.val() || $start.data('default'));
                var end = toMinutes($end.val() || $end.data('default'));
                var $out = $block.find('[data-role="duration"]');

                if (start === null || end === null) {
                    $out.text('');
                    return;
                }

                var length = end - start;
                var invalid = length < BLOCK_MIN || length > BLOCK_MAX;
                $out.text(length > 0
                    ? 'Durée : ' + formatDuration(length) + (invalid ? ' — hors limites' : '')
                    : 'La fin doit être après le début');
                $block.toggleClass('is-invalid', invalid);
            }

            $('.booking-block').each(function () {
                var $block = $(this);
                refreshBlock($block);
                $block.on('input change', 'input', function () {
                    refreshBlock($block);
                });
            });

            /* --- Modale offre --- */
            var $modal = $('#offer-modal');
            var $form = $('#offer-form');

            function setOfferMode(id) {
                if (id) {
                    $form.attr('action', $form.data('update-url').replace('__ID__', id));
                    $('#offer-method').val('PUT');
                    $('#offer-modal-title').text("Modifier l'offre");
                    $('#offer-submit').text('Enregistrer');
                } else {
                    $form.attr('action', $form.data('store-url'));
                    $('#offer-method').val('POST');
                    $('#offer-modal-title').text('Nouvelle offre');
                    $('#offer-submit').text("Créer l'offre");
                }
                $('#offer-id').val(id || '');
            }

            function fillOffer(offer) {
                $('#offer-title').val(offer ? offer.title : '');
                $('#offer-description').val(offer ? (offer.description || '') : '');
                $('#offer-blocks').val(offer ? offer.blocks_count : 1);
                $('#offer-price').val(offer ? offer.price : '');
                $('#offer-order').val(offer ? offer.order_dsp : '');
                $('#offer-active').prop('checked', offer ? !!offer.active_flg : true);
                $modal.find('.alert-danger').remove();
            }

            $('[data-action="new-offer"]').on('click', function () {
                setOfferMode(null);
                fillOffer(null);
                $modal.modal('show');
            });

            $('[data-action="edit-offer"]').on('click', function () {
                var offer = $(this).data('offer');
                setOfferMode(offer.id);
                fillOffer(offer);
                $modal.modal('show');
            });

            $modal.on('shown.bs.modal', function () {
                $('#offer-title').trigger('focus');
            });

            // Retour après erreur : on rouvre la modale avec la saisie conservée.
            @if ($offerOld)
                setOfferMode($('#offer-id').val() || null);
                $modal.modal('show');
            @endif

            /* --- Confirmations --- */
            $(document).on('submit', 'form[data-confirm]', function (e) {
                if (!window.confirm($(this).data('confirm'))) {
                    e.preventDefault();
                }
            });
        });
    </script>
@stop