{{-- Formulaire partagé création / édition --}}
@php
    $val      = fn ($key, $default = null) => old($key, $default);
    $imageUrl = $imageUrl ?? null;
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0; padding-left:18px">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-8">

        {{-- ============ INFORMATIONS ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="voyager-photo"></i> Informations
                    @if (!is_null($infographic))
                        <code style="margin-left:10px; font-size:11px">{{ $infographic->code }}</code>
                    @endif
                </h3>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label for="video_id">Vidéo associée</label>
                    <select class="form-control" id="video_id" name="video_id">
                        <option value=""></option>
                        @if ($val('video_id', $infographic->video_id ?? null))
                            <option value="{{ $val('video_id', $infographic->video_id) }}" selected>
                                {{ $selectedVideo }}
                            </option>
                        @endif
                    </select>
                    <p class="help-block">Facultatif. Saisissez au moins 3 caractères pour rechercher.</p>
                </div>

                <div class="form-group">
                    <label for="topic_id">Sujet <span class="text-danger">*</span></label>
                    <select class="form-control" id="topic_id" name="topic_id" required>
                        <option value="">—</option>
                        @foreach ($topics as $t)
                            <option value="{{ $t->id }}"
                                @selected((int) $val('topic_id', $infographic->topic_id ?? null) === (int) $t->id)>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>


                <div class="form-group">
                    <label for="title">Titre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="{{ $val('title', $infographic->title ?? '') }}" required>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="image_file">Visuel</label>

                    @if ($imageUrl)
                        <div style="margin-bottom:10px">
                            <img src="{{ $imageUrl }}" alt="Visuel actuel" class="infographic-preview">
                        </div>
                    @endif

                    <input type="file" id="image_file" name="image_file"
                           accept="image/jpeg,image/png,image/webp">
                    <p class="help-block">
                        Formats acceptés : JPG, PNG, WEBP — 5 Mo maximum.
                        Une vignette de {{ 400 }} px de large est générée automatiquement.
                        @if ($imageUrl)
                            Laissez vide pour conserver le visuel actuel.
                        @endif
                    </p>
                    <div id="image-preview"></div>
                </div>

            </div>
        </div>

    </div>

    <div class="col-md-4">

        {{-- ============ PUBLICATION ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-calendar"></i> Publication</h3>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label for="published_at">Date de publication</label>
                    <input type="date" class="form-control" id="published_at" name="published_at"
                           value="{{ $val('published_at', isset($infographic->published_at) && $infographic->published_at
                                ? \Illuminate\Support\Carbon::parse($infographic->published_at)->format('Y-m-d')
                                : '') }}">
                    <p class="help-block">
                        Laissez vide tant que l'infographie n'est pas programmée.
                    </p>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="expired_at">Date de fin</label>
                    <input type="date" class="form-control" id="expired_at" name="expired_at"
                           value="{{ $val('expired_at', isset($infographic->expired_at) && $infographic->expired_at
                                ? \Illuminate\Support\Carbon::parse($infographic->expired_at)->format('Y-m-d')
                                : '') }}">
                </div>

            </div>
        </div>

        {{-- ============ NOTIFICATION ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-bell"></i> Notification</h3>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label for="notification_title">Titre</label>
                    <input type="text" class="form-control" id="notification_title" name="notification_title"
                           value="{{ $val('notification_title', $infographic->notification_title ?? '') }}"
                           maxlength="150">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="notification_body">Message</label>
                    <textarea class="form-control" id="notification_body" name="notification_body"
                              rows="4" maxlength="255">{{ $val('notification_body', $infographic->notification_body ?? '') }}</textarea>
                    <p class="help-block">
                        Texte envoyé sur les mobiles. Laissez vide pour ne pas notifier.
                    </p>
                </div>

            </div>
        </div>

    </div>
</div>

<div class="action-wrapper-absolute">
    <a href="{{ route('infographics.index') }}" class="btn btn-default">Annuler</a>
    <button type="submit" class="btn btn-primary">
        <i class="voyager-check"></i>
        <span>{{ $submitLabel ?? 'Enregistrer' }}</span>
    </button>
</div>