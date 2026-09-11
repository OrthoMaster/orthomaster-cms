{{-- Formulaire partagé création / édition --}}
@php
    $val = fn($key, $default = null) => old($key, $default);

    // Réponses à afficher : anciennes valeurs après erreur, sinon celles en base
    $oldAnswers = old('answers');

    if (is_array($oldAnswers)) {
        $rows = [];
        foreach (array_values($oldAnswers) as $i => $text) {
            $rows[] = (object) ['text' => $text, 'correct_flg' => (int) old('correct') === $i];
        }
    } else {
        $rows = $answers
            ->map(
                fn($a) => (object) [
                    'text' => $a->text,
                    'correct_flg' => (bool) $a->correct_flg,
                ],
            )
            ->all();
    }

    if (empty($rows)) {
        $rows = [(object) ['text' => '', 'correct_flg' => true], (object) ['text' => '', 'correct_flg' => false]];
    }
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

        {{-- ============ QUESTION ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-question"></i> Question</h3>
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
                            @if ($val('video_id', $question->video_id ?? null))
                                <option value="{{ $val('video_id', $question->video_id) }}" selected>
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
                            <option value="{{ $t->id }}" @selected((int) $val('topic_id', $question->topic_id ?? null) === (int) $t->id)>
                                {{ $t->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Titre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                        value="{{ $val('title', $question->title ?? '') }}" maxlength="120" required>
                    <p class="help-block">120 caractères maximum.</p>
                </div>

                <div class="form-group">
                    <label for="question">Énoncé <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="question" name="question" rows="3" required>{{ $val('question', $question->question ?? '') }}</textarea>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="explanation">Explication</label>
                    <textarea class="form-control" id="explanation" name="explanation" rows="5">{{ $val('explanation', $question->explanation ?? '') }}</textarea>
                    <p class="help-block">
                        Affichée à l'utilisateur une fois sa réponse validée.
                    </p>
                </div>

            </div>
        </div>

        {{-- ============ RÉPONSES ============ --}}
        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="voyager-list"></i> Réponses</h3>
            </div>
            <div class="panel-body">

                <p class="help-block" style="margin-top:0">
                    Cochez la bonne réponse à gauche. Une seule réponse peut être correcte.
                </p>

                <div id="answers-list">
                    @foreach ($rows as $i => $row)
                        <div class="answer-row">
                            <label class="answer-correct" title="Bonne réponse">
                                <input type="radio" name="correct" value="{{ $i }}"
                                    @checked($row->correct_flg)>
                            </label>
                            <input type="text" class="form-control answer-text" name="answers[]"
                                value="{{ $row->text }}" placeholder="Texte de la réponse" required>
                            <button type="button" class="btn btn-link answer-remove" title="Retirer">
                                <i class="voyager-trash"></i>
                            </button>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="btn btn-default btn-sm" id="add-answer">
                    <i class="voyager-plus"></i> Ajouter une réponse
                </button>
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
                            isset($question->published_at) && $question->published_at
                                ? \Illuminate\Support\Carbon::parse($question->published_at)->format('Y-m-d')
                                : '',
                        ) }}">
                    <p class="help-block">
                        La question apparaît dans l'application à partir de cette date.
                    </p>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="expired_at">Date de fin</label>
                    <input type="date" class="form-control" id="expired_at" name="expired_at"
                        value="{{ $val(
                            'expired_at',
                            isset($question->expired_at) && $question->expired_at
                                ? \Illuminate\Support\Carbon::parse($question->expired_at)->format('Y-m-d')
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
                        value="{{ $val('notification_title', $question->notification_title ?? '') }}"
                        maxlength="255">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="notification_body">Message</label>
                    <textarea class="form-control" id="notification_body" name="notification_body" rows="4" maxlength="200">{{ $val('notification_body', $question->notification_body ?? '') }}</textarea>
                </div>

            </div>
        </div>

    </div>
</div>

<div class="action-wrapper-absolute">
    <a href="{{ route('voyager.daily-questions.index') }}" class="btn btn-default">Annuler</a>
    <button type="submit" class="btn btn-primary">
        <i class="voyager-check"></i>
        <span>{{ $submitLabel ?? 'Enregistrer' }}</span>
    </button>
</div>
