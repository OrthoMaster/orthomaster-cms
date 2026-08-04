{{-- Formulaire partagé création / édition --}}
@php
    $val = fn ($key, $default = null) => old($key, $default);

    // Les champs de la table `videos` n'existent que si le contenu y est lié.
    $hasVideo = !empty($video) || is_null($media);
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

@unless ($hasVideo)
    <div class="alert alert-info">
        Ce média n'est lié à aucune vidéo : seuls les champs du contenu sont modifiables.
    </div>
@endunless

<div class="row">
    <div class="col-md-8">
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-play"></i> Informations</h3>
            </div>
            <div class="panel-body">

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
                        <label for="thumbnail">Lien thumbnail (S3)</label>
                        <input type="text" class="form-control" id="thumbnail" name="thumbnail"
                               value="{{ $val('thumbnail', $video->thumbnail ?? '') }}"
                               placeholder="https://...">
                    </div>
                @endif

                <div class="form-group">
                    <label for="aws_object_path">Lien vidéo (S3)</label>
                    <input type="text" class="form-control" id="aws_object_path" name="aws_object_path"
                           value="{{ $val('aws_object_path', $media->aws_object_path ?? '') }}"
                           placeholder="https://...">
                </div>

                <div class="form-group">
                    <label for="duration">Durée</label>
                    <input type="text" class="form-control" id="duration" name="duration"
                           value="{{ $val('duration', $media->duration ?? '') }}"
                           placeholder="ex : 12:34">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4">{{ $val('description', $media->description ?? '') }}</textarea>
                </div>

                <div class="form-group">
                    <label for="tags">Tags / Mots-clés</label>
                    <textarea class="form-control" id="tags" name="tags" rows="2">{{ $val('tags', $media->tags ?? '') }}</textarea>
                </div>

                @if ($hasVideo)
                    <div class="form-group">
                        <label for="highlights">Chapitres (highlights)</label>
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
                    <label for="id_media_category">Catégorie</label>
                    <select class="form-control" id="id_media_category" name="id_media_category">
                        <option value="">—</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}"
                                @selected((int) $val('id_media_category', $media->id_media_category ?? null) === (int) $c->id)>
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

            </div>
        </div>

        @if (!empty($video))
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="voyager-key"></i> Code vidéo</h3>
                </div>
                <div class="panel-body">
                    <code>{{ $video->code }}</code>
                    <p class="help-block" style="margin-top:8px">Généré automatiquement, non modifiable.</p>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-md-12" style="margin-bottom:2em">
        <a href="{{ route('medias.index') }}" class="btn btn-default">Annuler</a>
        <button type="submit" class="btn btn-primary pull-right">
            {{ $submitLabel ?? 'Enregistrer' }}
        </button>
    </div>
</div>