@extends('voyager::master')

@section('page_title', ' Test factures')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-paper-plane"></i> Test factures
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content browse container-fluid">
        @include('voyager::alerts')

        <div class="row">
            <div class="col-lg-8">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <p class="test-intro">
                            Refait la <b>facture</b>, le <b>certificat</b> et le <b>programme</b> d'une facture existante
                            avec les modèles actuels, puis envoie l'<b>e-mail de fin de formation</b> tel qu'il partirait
                            au client — mais à l'adresse saisie ci-dessous.
                            <br>
                            <span class="text-muted">
                                Rien n'est enregistré : ni base de données, ni fichiers S3, ni Odoo.
                            </span>
                        </p>

                        <form action="{{ route('test-invoices.send') }}" method="POST">
                            @csrf

                            <div class="form-group @error('invoice_id') has-error @enderror">
                                <label for="invoice_id">Facture</label>
                                <select class="form-control select2" name="invoice_id" id="invoice_id" required>
                                    <option value="">Choisir une facture FIFPL ou OPCO-EP…</option>
                                    @foreach ($candidates as $c)
                                        <option value="{{ $c->id }}" @selected($selected === (string) $c->id)>
                                            #{{ $c->id }} · {{ $c->no ?: 'sans numéro' }} · {{ $c->organization }}
                                            · {{ $c->contract_no }}
                                            · {{ trim($c->user_name . ' ' . $c->user_first_name) ?: $c->client_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('invoice_id')
                                    <span class="help-block">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group @error('email') has-error @enderror">
                                <label for="email">Envoyer à</label>
                                <input type="email" class="form-control" name="email" id="email"
                                    value="{{ $email }}" placeholder="votre@adresse.fr" required>
                                @error('email')
                                    <span class="help-block">{{ $message }}</span>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="voyager-paper-plane"></i> Envoyer le test
                            </button>
                        </form>
                    </div>
                </div>

                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <h4 class="test-result-title">Devis OPCO-EP fictif</h4>
                        <p class="test-intro">
                            Invente un devis OPCO-EP signé aujourd'hui (entreprise « Cabinet TEST OrthoMaster »,
                            apprenants « TEST Apprenant n », vous comme manager) avec de vraies formations,
                            un seul dossier de 10 h au plus. Envoie l'e-mail de fin de formation tel que le reçoit
                            le 1<sup>er</sup> apprenant (son <b>certificat</b>), avec en plus, pour le test,
                            <b>devis + facture + programme + certificats de tous les apprenants</b>.
                            <br>
                            <span class="text-muted">Tout est fait en mémoire : rien n'est enregistré.</span>
                        </p>

                        <form action="{{ route('test-invoices.fake-opco') }}" method="POST">
                            @csrf

                            <div class="row">
                                <div class="col-md-8 form-group @error('fake_email') has-error @enderror">
                                    <label for="fake_email">Envoyer à</label>
                                    <input type="email" class="form-control" name="fake_email" id="fake_email"
                                        value="{{ old('fake_email', $email) }}" required>
                                    @error('fake_email')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-4 form-group @error('learners') has-error @enderror">
                                    <label for="learners">Apprenants</label>
                                    <input type="number" class="form-control" name="learners" id="learners"
                                        min="1" max="20" value="{{ old('learners', 3) }}" required>
                                    @error('learners')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="package_ids">Formations <span class="text-muted">(vide : au hasard)</span></label>
                                <select class="form-control select2" name="package_ids[]" id="package_ids" multiple>
                                    @foreach ($packages as $p)
                                        <option value="{{ $p->id }}" @selected(in_array($p->id, (array) old('package_ids', [])))>
                                            {{ $p->name }} ({{ rtrim(rtrim(number_format($p->duration / 60, 2, ',', ''), '0'), ',') }} h)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="voyager-paper-plane"></i> Créer et envoyer le devis fictif
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            @if ($result)
                <div class="col-lg-4">
                    <div class="panel panel-bordered">
                        <div class="panel-body">
                            <h4 class="test-result-title">Dernier envoi</h4>
                            <dl class="test-result">
                                <dt>Facture</dt>
                                <dd>{{ $result['invoice_no'] }} ({{ $result['organization'] }})</dd>
                                <dt>Envoyé à</dt>
                                <dd>{{ $result['email'] }}</dd>
                                <dt>Pièces jointes</dt>
                                <dd>
                                    @foreach ($result['attachments'] as $name)
                                        <div>{{ $name }}</div>
                                    @endforeach
                                </dd>
                            </dl>
                            @if (!empty($result['notes']))
                                <ul class="test-notes">
                                    @foreach ($result['notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@stop

@section('css')
    <style>
        .test-intro {
            margin-bottom: 18px;
        }

        .test-result-title {
            margin-top: 0;
        }

        .test-result dt {
            color: #76838f;
            font-weight: 600;
            margin-top: 8px;
        }

        .test-result dd {
            word-break: break-all;
        }

        .test-notes {
            margin: 12px 0 0;
            padding-left: 18px;
            color: #8a6d3b;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('#invoice_id, #package_ids').select2({ width: '100%' });
            }
        });
    </script>
@stop
