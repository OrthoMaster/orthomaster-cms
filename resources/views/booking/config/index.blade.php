@extends('voyager::master')

@php
    $activeTab = old('_tab', $tab);
    $oldForm = old('_form');

    // Pourcentages et taux sans décimales inutiles, virgule française : 12.50 → « 12,5 % ».
    $pct = fn($value) => str_replace('.', ',', (string) (float) $value) . ' %';
    $date = fn($value) => \Carbon\Carbon::parse($value)->format('d/m/Y');

    // Taux en vigueur par périmètre (général ou expert) : le plus récent déjà entré en vigueur.
    $currentCommissionIds = $commissions
        ->filter(fn($r) => $r->effective_from <= $today)
        ->groupBy(fn($r) => $r->expert_id ?? 'global')
        ->map(fn($group) => $group->sortByDesc('effective_from')->first()->id)
        ->values()
        ->all();

    // [libellé, icône, compteur ou null]
    $tabs = [
        'policies' => ["Politique d'annulation", 'voyager-file-text', null],
        'commissions' => ['Commissions', 'voyager-wallet', null],
        'reasons' => ['Motifs de refus', 'voyager-list', $reasons->where('active_flg', true)->count()],
    ];
@endphp

@section('page_title', 'Configuration des rendez-vous')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-settings"></i> Configuration des rendez-vous
    </h1>
    <a href="{{ route('booking.appointments.index') }}" class="btn btn-default">
        <i class="voyager-calendar"></i> Rendez-vous
    </a>
    <a href="{{ route('booking.experts.index') }}" class="btn btn-default">
        <i class="voyager-people"></i> Experts
    </a>
    <a href="{{ route('voyager.settings.index') }}" class="btn btn-default" target="_blank">
        <i class="voyager-params"></i> Valeurs par défaut et paiement
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
            white-space: nowrap;
        }

        .booking-pill--on {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.1);
            color: #1e8e4f;
        }

        .booking-pill--next {
            border-color: #22a7f0;
            background: rgba(34, 167, 240, 0.1);
            color: #136b9c;
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
        .booking-section+.booking-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eef0f3;
        }

        .booking-section h3 {
            margin: 0 0 6px;
            font-size: 17px;
        }

        .booking-intro {
            margin: 0 0 18px;
            color: #8a8f98;
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

        .booking-inline-form {
            display: inline;
        }

        .booking-empty {
            padding: 30px !important;
            text-align: center;
            color: #8a8f98;
        }

        /* --- Politique en vigueur --- */
        .booking-facts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 0;
        }

        .booking-facts>div {
            padding: 14px 16px;
            border: 1px solid #e4e7eb;
            border-radius: 6px;
        }

        .booking-facts dt {
            font-weight: normal;
            color: #6b7280;
        }

        .booking-facts dd {
            margin: 4px 0 0;
            font-size: 22px;
            font-weight: 600;
            color: #1f2937;
        }

        .booking-facts dd small {
            display: block;
            font-size: 12px;
            font-weight: normal;
            color: #8a8f98;
        }

        /* --- Motifs --- */
        .booking-reasons td {
            vertical-align: middle !important;
        }

        .booking-reasons .booking-order {
            width: 80px;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">

        @if ($errors->any())
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
                <button type="button" class="stats-tab {{ $activeTab === $key ? 'is-active' : '' }}"
                    data-tab="{{ $key }}">
                    <i class="{{ $icon }}"></i> {{ $label }}
                    @if ($count !== null)
                        <span class="stats-tab-count">{{ $count }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ---------- Politique d'annulation ---------- --}}
        <div class="stats-pane" data-pane="policies" @if ($activeTab !== 'policies') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="booking-section">
                        @if ($currentPolicy)
                            <h3>En vigueur depuis le {{ $currentPolicy->effective_from->format('d/m/Y') }}</h3>
                            <p class="booking-intro">
                                Chaque demande enregistre la politique du jour : la modifier ne change rien aux rendez-vous
                                existants.
                            </p>
                            <dl class="booking-facts">
                                <div>
                                    <dt>Annulation gratuite</dt>
                                    <dd>
                                        {{ $currentPolicy->free_cancel_hours }} h
                                        <small>avant le premier bloc</small>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Annulation tardive</dt>
                                    <dd>
                                        {{ $pct($currentPolicy->late_cancel_penalty_pct) }}
                                        <small>retenus sur le montant</small>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Absence de l'utilisateur</dt>
                                    <dd>
                                        {{ $pct($currentPolicy->user_noshow_refund_pct) }}
                                        <small>remboursés</small>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Absence de l'expert</dt>
                                    <dd>
                                        100 %
                                        <small>remboursés, toujours</small>
                                    </dd>
                                </div>
                            </dl>
                        @else
                            <div class="alert alert-warning" style="margin: 0;">
                                Aucune politique en vigueur. Enregistrez-en une avant d'ouvrir les réservations.
                            </div>
                        @endif
                    </div>

                    <div class="booking-section">
                        <h3>Programmer une politique</h3>
                        <p class="booking-intro">
                            Choisir une date déjà programmée remplace la politique prévue ce jour-là.
                        </p>

                        @php $policyOld = $oldForm === 'policy'; @endphp
                        <form method="POST" action="{{ route('booking.config.policies.store') }}">
                            @csrf
                            <input type="hidden" name="_tab" value="policies">
                            <input type="hidden" name="_form" value="policy">

                            <div class="booking-form-row">
                                <div class="form-group">
                                    <label for="policy-from">Date d'effet</label>
                                    <input type="date" name="effective_from" id="policy-from" class="form-control"
                                        min="{{ $today }}" required
                                        value="{{ $policyOld ? old('effective_from') : $today }}">
                                </div>
                                <div class="form-group">
                                    <label for="policy-free">Annulation gratuite jusqu'à (h)</label>
                                    <input type="number" name="free_cancel_hours" id="policy-free" class="form-control"
                                        min="0" max="8760" required
                                        value="{{ $policyOld ? old('free_cancel_hours') : $currentPolicy?->free_cancel_hours }}">
                                </div>
                                <div class="form-group">
                                    <label for="policy-penalty">Pénalité tardive (%)</label>
                                    <input type="number" name="late_cancel_penalty_pct" id="policy-penalty"
                                        class="form-control" min="0" max="100" step="0.01" required
                                        value="{{ $policyOld ? old('late_cancel_penalty_pct') : $currentPolicy?->late_cancel_penalty_pct }}">
                                </div>
                                <div class="form-group">
                                    <label for="policy-noshow">Remboursement si l'utilisateur est absent (%)</label>
                                    <input type="number" name="user_noshow_refund_pct" id="policy-noshow"
                                        class="form-control" min="0" max="100" step="0.01" required
                                        value="{{ $policyOld ? old('user_noshow_refund_pct') : $currentPolicy?->user_noshow_refund_pct }}">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="booking-section">
                        <h3>Historique</h3>
                        <table class="table table-striped database-tables">
                            <thead>
                                <tr>
                                    <th>Date d'effet</th>
                                    <th>Annulation gratuite</th>
                                    <th>Pénalité tardive</th>
                                    <th>Remb. absence utilisateur</th>
                                    <th>Statut</th>
                                    <th class="text-right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($policies as $policy)
                                    @php $from = $policy->effective_from->toDateString(); @endphp
                                    <tr>
                                        <td>{{ $policy->effective_from->format('d/m/Y') }}</td>
                                        <td>{{ $policy->free_cancel_hours }} h</td>
                                        <td>{{ $pct($policy->late_cancel_penalty_pct) }}</td>
                                        <td>{{ $pct($policy->user_noshow_refund_pct) }}</td>
                                        <td>
                                            @if ($currentPolicy && $policy->id === $currentPolicy->id)
                                                <span class="booking-pill booking-pill--on">En vigueur</span>
                                            @elseif ($from > $today)
                                                <span class="booking-pill booking-pill--next">Programmée</span>
                                            @else
                                                <span class="booking-pill">Archivée</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($from >= $today)
                                                <form method="POST" class="booking-inline-form"
                                                    action="{{ route('booking.config.policies.destroy', $policy->id) }}"
                                                    data-confirm="Supprimer la politique du {{ $policy->effective_from->format('d/m/Y') }} ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="booking-empty">Aucune politique enregistrée.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Commissions ---------- --}}
        <div class="stats-pane" data-pane="commissions" @if ($activeTab !== 'commissions') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="booking-section">
                        <h3>Taux général en vigueur : {{ $pct($globalRate) }}</h3>
                        <p class="booking-intro">
                            Un taux propre à un expert remplace le taux général pour cet expert uniquement.
                            Chaque demande enregistre le taux du jour, pour la facturation.
                        </p>

                        @php $commissionOld = $oldForm === 'commission'; @endphp
                        <form method="POST" action="{{ route('booking.config.commissions.store') }}">
                            @csrf
                            <input type="hidden" name="_tab" value="commissions">
                            <input type="hidden" name="_form" value="commission">

                            <div class="booking-form-row">
                                <div class="form-group" style="min-width: 260px;">
                                    <label for="commission-expert">Concerne</label>
                                    <select name="expert_id" id="commission-expert" class="form-control">
                                        <option value="">Taux général</option>
                                        @foreach ($experts as $expert)
                                            <option value="{{ $expert->id }}" @selected($commissionOld && (int) old('expert_id') === $expert->id)>
                                                {{ $expert->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="commission-rate">Taux (%)</label>
                                    <input type="number" name="rate" id="commission-rate" class="form-control"
                                        min="0" max="100" step="0.01" required
                                        value="{{ $commissionOld ? old('rate') : '' }}">
                                </div>
                                <div class="form-group">
                                    <label for="commission-from">Date d'effet</label>
                                    <input type="date" name="effective_from" id="commission-from"
                                        class="form-control" min="{{ $today }}" required
                                        value="{{ $commissionOld ? old('effective_from') : $today }}">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="booking-section">
                        <table class="table table-striped database-tables">
                            <thead>
                                <tr>
                                    <th>Concerne</th>
                                    <th class="text-right">Taux</th>
                                    <th>Date d'effet</th>
                                    <th>Statut</th>
                                    <th class="text-right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($commissions as $commission)
                                    <tr>
                                        <td>
                                            @if ($commission->expert_id)
                                                {{ $commission->expert_name ?? 'Expert #' . $commission->expert_id }}
                                            @else
                                                <strong>Taux général</strong>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $pct($commission->rate) }}</td>
                                        <td>{{ $date($commission->effective_from) }}</td>
                                        <td>
                                            @if (in_array($commission->id, $currentCommissionIds, true))
                                                <span class="booking-pill booking-pill--on">En vigueur</span>
                                            @elseif ($commission->effective_from > $today)
                                                <span class="booking-pill booking-pill--next">Programmé</span>
                                            @else
                                                <span class="booking-pill">Archivé</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if ($commission->effective_from >= $today)
                                                <form method="POST" class="booking-inline-form"
                                                    action="{{ route('booking.config.commissions.destroy', $commission->id) }}"
                                                    data-confirm="Supprimer ce taux ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="btn btn-sm btn-danger">Supprimer</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="booking-empty">Aucun taux enregistré.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---------- Motifs de refus ---------- --}}
        <div class="stats-pane" data-pane="reasons" @if ($activeTab !== 'reasons') style="display:none" @endif>
            <div class="panel panel-bordered">
                <div class="panel-body">
                    <div class="booking-section">
                        <h3>Motifs proposés à l'expert</h3>
                        <p class="booking-intro">
                            L'expert choisit un motif ou écrit le sien. Un motif ne se supprime pas :
                            désactivez-le pour qu'il ne soit plus proposé.
                        </p>

                        <table class="table table-striped database-tables booking-reasons">
                            <thead>
                                <tr>
                                    <th class="booking-order">Ordre</th>
                                    <th>Libellé</th>
                                    <th>Proposé</th>
                                    <th class="text-right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reasons as $reason)
                                    {{-- Champs liés au formulaire par l'attribut form : un <form> ne peut pas envelopper une ligne de tableau. --}}
                                    <tr>
                                        <td>
                                            <input type="number" name="order_dsp" form="reason-{{ $reason->id }}"
                                                class="form-control input-sm booking-order" min="0"
                                                value="{{ $reason->order_dsp }}" aria-label="Ordre">
                                        </td>
                                        <td>
                                            <input type="text" name="label" form="reason-{{ $reason->id }}"
                                                class="form-control input-sm" maxlength="150" required
                                                value="{{ $reason->label }}" aria-label="Libellé">
                                        </td>
                                        <td>
                                            <input type="checkbox" name="active_flg" value="1"
                                                form="reason-{{ $reason->id }}" @checked($reason->active_flg)
                                                aria-label="Proposé">
                                        </td>
                                        <td class="text-right">
                                            <form method="POST" id="reason-{{ $reason->id }}"
                                                class="booking-inline-form"
                                                action="{{ route('booking.config.reasons.update', $reason->id) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="_tab" value="reasons">
                                                <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="booking-empty">Aucun motif.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="booking-section">
                        <form method="POST" action="{{ route('booking.config.reasons.store') }}">
                            @csrf
                            <input type="hidden" name="_tab" value="reasons">
                            <input type="hidden" name="_form" value="reason">

                            <div class="booking-form-row">
                                <div class="form-group" style="flex: 1; min-width: 260px;">
                                    <label for="reason-label">Nouveau motif</label>
                                    <input type="text" name="label" id="reason-label" class="form-control"
                                        maxlength="150" required value="{{ $oldForm === 'reason' ? old('label') : '' }}">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-success">Ajouter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function() {
            /* --- Onglets --- */
            function showTab(name) {
                var $tab = $('.stats-tab[data-tab="' + name + '"]');

                if (!$tab.length) return;

                $('.stats-tab').removeClass('is-active');
                $tab.addClass('is-active');

                $('.stats-pane').each(function() {
                    $(this).toggle($(this).data('pane') === name);
                });
            }

            $('.stats-tab').on('click', function() {
                var name = $(this).data('tab');

                // En paramètre plutôt qu'en ancre : les redirections du serveur
                // (?tab=) et un rechargement rouvrent ainsi le même onglet.
                var url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                history.replaceState(null, '', url);

                showTab(name);
            });


            $('#commission-expert').select2({
                width: '100%'
            });

            /* --- Confirmations --- */
            $(document).on('submit', 'form[data-confirm]', function(e) {
                if (!window.confirm($(this).data('confirm'))) {
                    e.preventDefault();
                }
            });
        });
    </script>
@stop
