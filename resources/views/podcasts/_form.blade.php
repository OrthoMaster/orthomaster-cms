{{-- Formulaire partagé création / édition --}}
@php
    $val = fn($key, $default = null) => old($key, $default);
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

        {{-- ============ PODCAST ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-music"></i> Podcast</h3>
            </div>
            <div class="panel-body">

                @if (!empty($lockedVideo))
                    {{-- Création lancée depuis un média : la vidéo est imposée --}}
                    <div class="form-group">
                        <label>Vidéo associée</label>
                        <input type="hidden" name="video_id" value="{{ $lockedVideo->id }}">
                        <input type="text" class="form-control" value="{{ $lockedVideo->title }}" readonly>
                        <p class="help-block">Imposée par le média d'origine.</p>
                    </div>
                @else
                    <div class="form-group">
                        <label for="video_id">Vidéo associée</label>
                        <select class="form-control" id="video_id" name="video_id">
                            <option value=""></option>
                            @if ($val('video_id', $podcast->video_id ?? null))
                                <option value="{{ $val('video_id', $podcast->video_id) }}" selected>
                                    {{ $selectedVideo }}
                                </option>
                            @endif
                        </select>
                        <p class="help-block">Facultatif. Saisissez au moins 3 caractères pour rechercher.</p>
                    </div>
                @endif

                <div class="form-group">
                    <label for="topic_id">Sujet <span class="text-danger">*</span></label>
                    <select class="form-control" id="topic_id" name="topic_id" required>
                        <option value="">—</option>
                        @foreach ($topics as $t)
                            <option value="{{ $t->id }}" @selected((int) $val('topic_id', $podcast->topic_id ?? null) === (int) $t->id)>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Titre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                        value="{{ $val('title', $podcast->title ?? '') }}" required>
                </div>

                <div class="form-group">
                    <label for="author">Auteur</label>
                    <input type="text" class="form-control" id="author" name="author"
                        value="{{ $val('author', $podcast->author ?? '') }}">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="duration">Durée</label>
                    <input type="text" class="form-control" id="duration" name="duration"
                        value="{{ $val('duration', $podcast->duration ?? '') }}" placeholder="ex : 12:34">
                </div>

            </div>
        </div>

        {{-- ============ SOURCES ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-file-text"></i> Sources</h3>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label for="link">Lien du fichier audio (S3) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="link" name="link"
                        value="{{ $val('link', $podcast->link ?? '') }}" placeholder="https://…" required>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="image">Lien de la vignette (S3)</label>
                    <input type="text" class="form-control" id="image" name="image"
                        value="{{ $val('image', $podcast->image ?? '') }}" placeholder="https://…">
                    <p class="help-block">L'aperçu se met à jour à la saisie du lien.</p>
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
                        value="{{ $val(
                            'published_at',
                            isset($podcast->published_at) && $podcast->published_at
                                ? \Illuminate\Support\Carbon::parse($podcast->published_at)->format('Y-m-d')
                                : '',
                        ) }}">
                    <p class="help-block">
                        Le podcast apparaît dans l'application à partir de cette date.
                    </p>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="expired_at">Date de fin</label>
                    <input type="date" class="form-control" id="expired_at" name="expired_at"
                        value="{{ $val(
                            'expired_at',
                            isset($podcast->expired_at) && $podcast->expired_at
                                ? \Illuminate\Support\Carbon::parse($podcast->expired_at)->format('Y-m-d')
                                : '',
                        ) }}">
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
                    <label for="notification_topics">Centres d'intérêt notifiés</label>
                    <select class="form-control" id="notification_topics" name="notification_topics[]" multiple>
                        @foreach ($topics as $topic)
                            <option value="{{ $topic->id }}" @selected(in_array($topic->id, old('notification_topics', $selectedTopics ?? [])))>
                                {{ $topic->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="notification_title">Titre</label>
                    <input type="text" class="form-control" id="notification_title" name="notification_title"
                        value="{{ $val('notification_title', $podcast->notification_title ?? '') }}" maxlength="255">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="notification_body">Message</label>
                    <textarea class="form-control" id="notification_body" name="notification_body" rows="4" maxlength="500">{{ $val('notification_body', $podcast->notification_body ?? '') }}</textarea>
                    <p class="help-block">
                        Texte envoyé sur les mobiles. Laissez vide pour ne pas notifier.
                    </p>
                </div>

            </div>
        </div>

    </div>
</div>

<div class="action-wrapper-absolute">
    <a href="{{ route('voyager.podcasts.index') }}" class="btn btn-default">Annuler</a>
    <button type="submit" class="btn btn-primary">
        <i class="voyager-check"></i>
        <span>{{ $submitLabel ?? 'Enregistrer' }}</span>
    </button>
</div>
