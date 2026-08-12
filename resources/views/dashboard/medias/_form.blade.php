{{-- Formulaire partagé création / édition --}}
@php
    $val = fn($key, $default = null) => old($key, $default);

    // Les champs de la table `videos` n'existent que si le contenu y est lié.
    $hasVideo = !empty($video) || is_null($media);
    $thumbnailUrl = $thumbnailUrl ?? null;
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
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-play"></i> Informations</h3>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="version">Version</label>
                    <select class="form-control" id="version" name="version">
                        <option value="v1" @selected($val('version', $media->version ?? 'v2') === 'v1')>
                            Version 1 — contenu XML hérité
                        </option>
                        <option value="v2" @selected($val('version', $media->version ?? 'v2') === 'v2')>
                            Version 2 — contenu par text
                        </option>
                    </select>
                    <p class="help-block">
                        La version détermine la façon dont l'application affiche ce média.
                    </p>
                </div>

                @if ($hasVideo)
                    <div class="form-group">
                        <label for="title">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title"
                            value="{{ $val('title', $media->title ?? '') }}" required>
                        <p class="help-block">Le titre est enregistré à la fois sur le contenu et sur la vidéo.</p>
                    </div>
                @else
                    <div class="form-group">
                        <label>Titre</label>
                        <p class="form-control-static"><b>{{ $media->title }}</b></p>
                    </div>
                @endif

                @if ($hasVideo)
                    <div class="form-group">
                        <label for="thumbnail_file">Vignette</label>

                        @if ($thumbnailUrl)
                            <div style="margin-bottom:10px">
                                <img src="{{ $thumbnailUrl }}" alt="Vignette actuelle"
                                    style="max-width:260px; border:1px solid #e3e6ec; border-radius:4px">
                            </div>
                        @endif

                        <input type="file" id="thumbnail_file" name="thumbnail_file"
                            accept="image/jpeg,image/png,image/webp">
                        <p class="help-block">
                            Formats acceptés : JPG, PNG, WEBP — 5 Mo maximum.
                            @if ($thumbnailUrl)
                                Laissez vide pour conserver la vignette actuelle ; l'ancienne image sera
                                supprimée si vous en envoyez une nouvelle.
                            @endif
                        </p>
                        <div id="thumbnail-preview"></div>
                    </div>
                @endif

                <div class="form-group">
                    <label for="duration">Durée</label>
                    <input type="text" class="form-control" id="duration" name="duration"
                        value="{{ $val('duration', $media->duration ?? '') }}" placeholder="ex : 12:34">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4">{{ $val('description', $media->description ?? '') }}</textarea>
                </div>

                <div class="form-group">
                    <label for="tags">Mots-clés</label>
                    <textarea class="form-control" id="tags" name="tags" rows="1">{{ $val('tags', $media->tags ?? '') }}</textarea>
                </div>

                @if ($hasVideo)
                    <div class="form-group">
                        <label for="subtitles">Languages (Ex: fr;en)</label>
                        <input type="text" class="form-control" id="subtitles" name="subtitles"
                            value="{{ $val('subtitles', $video->subtitles ?? '') }}">
                        <p class="help-block">
                            Codes de langue des sous-titres, séparés par un point-virgule ou une virgule.
                            Exemple : <code>fr;en</code>
                        </p>
                    </div>
                @endif

                @if ($hasVideo)
                    <div class="form-group">
                        <label for="highlights">Chapitres (Ex: 00:00 Abc)</label>
                        <textarea class="form-control" id="highlights" name="highlights" rows="8"
                            placeholder="0:00 Introduction&#10;2:15 Premier cas clinique&#10;1:05:30 Conclusion">{{ $val('highlights', $video->highlights ?? '') }}</textarea>
                        <p class="help-block">
                            Une ligne par chapitre, au format <code>m:ss</code>, <code>mm:ss</code> ou
                            <code>h:mm:ss</code> suivi du libellé. Les lignes non conformes sont ignorées.
                        </p>
                        <div id="highlights-preview"></div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-list"></i> Classement</h3>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label for="experts">Auteurs</label>
                    <select class="form-control" id="experts" name="experts[]" multiple>
                        @foreach ($selectedExperts as $e)
                            <option value="{{ $e->id }}" selected>{{ $e->text }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="topics">Centres d'intérêt</label>
                    @php $chosenTopics = (array) $val('topics', $selectedTopics); @endphp
                    <select class="form-control" id="topics" name="topics[]" multiple>
                        @foreach ($topics as $t)
                            <option value="{{ $t->id }}" @selected(in_array($t->id, $chosenTopics))>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_media_category">Catégorie</label>
                    <select class="form-control" id="id_media_category" name="id_media_category">
                        <option value="">—</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected((int) $val('id_media_category', $media->id_media_category ?? null) === (int) $c->id)>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_past_event">Événement</label>
                    <select class="form-control" id="id_past_event" name="id_past_event">
                        <option value="">—</option>
                        @foreach ($pastEvents as $pe)
                            <option value="{{ $pe->id }}"
                                @selected((int) $val('id_past_event', $media->id_past_event ?? null) === (int) $pe->id)>
                                {{ $pe->text }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="trend_flg">Tendance</label>
                    <div>
                        <input type="hidden" name="trend_flg" value="0">
                        <input type="checkbox" name="trend_flg" id="trend_flg" value="1"
                               data-toggle="toggle" data-on="Oui" data-off="Non"
                               data-onstyle="danger" data-offstyle="default"
                               @checked((bool) $val('trend_flg', $media->trend_flg ?? 0))>
                    </div>
                    <small class="help-block text-warning" style="margin-top:8px">
                        Met le média en avant dans la section « Tendances » de l'application.
                    </small>
                </div>

            </div>
        </div>

        @if ($hasVideo)
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="voyager-key"></i> Diffusion</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="code">Code vidéo</label>
                        <input type="text" class="form-control" id="code" name="code"
                               value="{{ $val('code', $video->code ?? '') }}"
                               data-initial="{{ $video->code ?? '' }}"
                               placeholder="ex : 9272ff36-e650-4f33-8efe-5c4c7dcd451a">
                        <small class="help-block text-warning">
                            Ce code correspond au dossier de la vidéo sur le CDN.
                            Le modifier rendra la vidéo actuelle illisible.
                        </small>
                    </div>


                    <div class="form-group">
                        <label for="published">Publication</label>
                        <div>
                            <input type="hidden" name="published" value="0">
                            <input type="checkbox" name="published" id="published" value="1"
                                   data-toggle="toggle" data-on="Publié" data-off="Hors ligne"
                                   data-onstyle="success" data-offstyle="default"
                                   @checked((bool) $val('published', $media->published ?? 0))>
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="datetime-local" class="form-control" id="published_at" name="published_at"
                               value="{{ $val('published_at', isset($video->published_at) && $video->published_at
                                    ? \Illuminate\Support\Carbon::parse($video->published_at)->format('Y-m-d\TH:i')
                                    : '') }}">
                    </div>

                </div>
            </div>
        @endif

        @if (!is_null($media))
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="voyager-archive"></i> Réf. ancienne version</h3>
                </div>
                <div class="panel-body">

                    <div class="form-group">
                        <label>Lien thumbnail (S3)</label>
                        <input type="text" class="form-control" value="{{ $media->s3_path }}" readonly>
                    </div>

                    <div class="form-group" style="margin-bottom:0">
                        <label>Lien vidéo (S3)</label>
                        <input type="text" class="form-control" value="{{ $media->aws_object_path }}" readonly>
                    </div>

                    <p class="help-block" style="margin-top:8px; margin-bottom:0">
                        Données héritées, conservées pour référence. Non modifiables.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>

@if (!is_null($media) && !empty($video))
    @include('dashboard.medias._content')
@endif

<div class="action-wrapper-absolute">
    <a href="{{ route('medias.index') }}" class="btn btn-default">Annuler</a>

    <div class="action-wrapper-absolute">
        <a href="{{ route('medias.index') }}" class="btn btn-default">Annuler</a>
        <button type="submit" class="btn btn-primary">
            <i class="voyager-check"></i>
            <span>{{ $submitLabel ?? 'Enregistrer' }}</span>
        </button>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="voyager-check"></i>
        <span>{{ $submitLabel ?? 'Enregistrer' }}</span>
    </button>
</div>
