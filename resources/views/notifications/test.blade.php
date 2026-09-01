@extends('voyager::master')

@section('page_title', ' Test de notification')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-bell"></i> Test de notification
        </h1>
    </div>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        @include('voyager::alerts')

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0; padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('notifications.test.send') }}" method="POST" id="notif-form">
            @csrf

            <div class="row">
                <div class="col-md-6">

                    {{-- ============ DESTINATAIRE ============ --}}
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-person"></i> Destinataire</h3>
                        </div>
                        <div class="panel-body">

                            <div class="form-group">
                                <label for="user_id">Utilisateur <span class="text-danger">*</span></label>
                                <select class="form-control" id="user_id" name="user_id" required>
                                    <option value="">—</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>
                                            {{ $u->email }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="help-block">
                                    Seuls les comptes en <code>@orthomaster.fr</code> sont proposés.
                                </p>
                            </div>

                            <div class="form-group" style="margin-bottom:0">
                                <label for="device_id">Appareil <span class="text-danger">*</span></label>
                                <select class="form-control" id="device_id" name="device_id"
                                        data-selected="{{ old('device_id') }}" required>
                                    <option value="">—</option>
                                </select>
                                <p class="help-block" id="device-hint">
                                    Sélectionnez d'abord un utilisateur.
                                </p>
                            </div>

                        </div>
                    </div>

                    {{-- ============ CONTENU ============ --}}
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-list"></i> Contenu visé</h3>
                        </div>
                        <div class="panel-body">

                            <div class="form-group">
                                <label for="target_type">Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="target_type" name="target_type" required>
                                    <option value="">—</option>
                                    <option value="podcast" @selected(old('target_type') === 'podcast')>Podcast</option>
                                    <option value="daily-question" @selected(old('target_type') === 'daily-question')>Question du jour</option>
                                    <option value="infographic" @selected(old('target_type') === 'infographic')>Infographie</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom:0">
                                <label for="target_id">Élément <span class="text-danger">*</span></label>
                                <select class="form-control" id="target_id" name="target_id"
                                        data-selected="{{ old('target_id') }}" required>
                                    <option value="">—</option>
                                </select>
                                <p class="help-block" id="target-hint">
                                    Sélectionnez d'abord un type de contenu.
                                </p>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="col-md-6">

                    {{-- ============ MESSAGE ============ --}}
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="voyager-bell"></i> Message</h3>
                        </div>
                        <div class="panel-body">

                            <div class="form-group">
                                <label for="title">Titre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title"
                                       value="{{ old('title') }}" maxlength="255" readonly required>
                            </div>

                            <div class="form-group">
                                <label for="body">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="body" name="body" rows="4"
                                          maxlength="500" readonly required>{{ old('body') }}</textarea>
                                <p class="help-block">
                                    Prérempli avec le contenu choisi, modifiable pour les essais.
                                </p>
                            </div>

                            <div class="form-group" style="margin-bottom:0">
                                <label>Données transmises</label>
                                <pre class="notif-payload" id="payload-preview">—</pre>
                                <p class="help-block">
                                    Ce sont ces données qui déterminent l'écran ouvert par l'application.
                                </p>
                            </div>

                        </div>
                    </div>

                    <div class="text-right" style="margin-bottom:2em">
                        <button type="submit" class="btn btn-primary">
                            <i class="voyager-send"></i> Envoyer la notification
                        </button>
                    </div>

                </div>
            </div>
        </form>
    </div>
@stop

@section('css')
    <style>
        .notif-payload {
            margin: 0;
            padding: 12px;
            background: #f7f9fc;
            border: 1px solid #e3e6ec;
            border-radius: 4px;
            font-size: 12px;
            color: #37474f;
            white-space: pre;
        }
    </style>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {

            var DEVICES_URL = '{{ url('admin/notifications/test/devices') }}',
                TARGETS_URL = '{{ url('admin/notifications/test/targets') }}';

            $('#user_id, #target_type').select2({ width: '100%' });
            $('#device_id, #target_id').select2({ width: '100%' });

            /* ---------- Appareils de l'utilisateur ---------- */
            function loadDevices() {
                var userId = $('#user_id').val(),
                    $sel   = $('#device_id'),
                    keep   = $sel.data('selected');

                $sel.find('option[value!=""]').remove();

                if (!userId) {
                    $('#device-hint').text('Sélectionnez d\'abord un utilisateur.');
                    $sel.trigger('change.select2');
                    return;
                }

                $('#device-hint').text('Chargement…');

                $.getJSON(DEVICES_URL + '/' + userId)
                    .done(function (items) {
                        items.forEach(function (it) {
                            $sel.append($('<option>').val(it.id).text(it.text));
                        });

                        if (keep) {
                            $sel.val(keep);
                            $sel.data('selected', null);
                        }

                        $sel.trigger('change.select2');

                        $('#device-hint').text(
                            items.length
                                ? items.length + ' appareil(s) enregistré(s).'
                                : 'Aucun appareil enregistré pour cet utilisateur.'
                        );
                    })
                    .fail(function () {
                        $('#device-hint').text('Impossible de charger les appareils.');
                    });
            }

            /* ---------- Contenus du type choisi ---------- */
            var targetsCache = {};

            function loadTargets() {
                var type = $('#target_type').val(),
                    $sel = $('#target_id'),
                    keep = $sel.data('selected');

                $sel.find('option[value!=""]').remove();

                if (!type) {
                    $('#target-hint').text('Sélectionnez d\'abord un type de contenu.');
                    $sel.trigger('change.select2');
                    updatePayload();
                    return;
                }

                $('#target-hint').text('Chargement…');

                $.getJSON(TARGETS_URL + '/' + type)
                    .done(function (items) {
                        targetsCache = {};

                        items.forEach(function (it) {
                            targetsCache[it.id] = it;
                            $sel.append($('<option>').val(it.id).text(it.text));
                        });

                        if (keep) {
                            $sel.val(keep);
                            $sel.data('selected', null);
                        }

                        $sel.trigger('change.select2');

                        $('#target-hint').text(
                            items.length ? items.length + ' élément(s).' : 'Aucun élément disponible.'
                        );

                        updatePayload();
                    })
                    .fail(function () {
                        $('#target-hint').text('Impossible de charger les éléments.');
                    });
            }

            /* ---------- Préremplissage du message ---------- */
            function fillMessage() {
                var item = targetsCache[$('#target_id').val()];

                if (!item) return;

                $('#title').val(item.title || '');
                $('#body').val(item.body || '');
            }

            function updatePayload() {
                var type = $('#target_type').val(),
                    id   = $('#target_id').val();

                if (!type || !id) {
                    $('#payload-preview').text('—');
                    return;
                }

                $('#payload-preview').text(
                    JSON.stringify({ action: type, id: parseInt(id, 10) }, null, 2)
                );
            }

            $('#user_id').on('change', loadDevices);
            $('#target_type').on('change', loadTargets);

            $('#target_id').on('change', function () {
                fillMessage();
                updatePayload();
            });

            // Rechargement après erreur de validation : on restaure les listes
            if ($('#user_id').val()) loadDevices();
            if ($('#target_type').val()) loadTargets();
        });
    </script>
@stop