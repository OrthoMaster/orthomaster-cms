{{--
    Boutons Doctolib / LinkedIn / Facebook.
    Chaque bouton n'apparaît que si son lien est renseigné.
    Variables : $doctolib, $linkedin, $facebook (facultatives)
--}}
<div class="actions fade d4">
    @if (!empty($doctolib))
        <a class="btn" href="{{ $doctolib }}" target="_blank" rel="noopener">
            <img class="ico" src="{{ asset('brochures/img/doctolib.png') }}" alt="" width="18" height="18">Doctolib
        </a>
    @endif
    @if (!empty($linkedin))
        <a class="btn" href="{{ $linkedin }}" target="_blank" rel="noopener">
            @include('brochures.partials.icons.linkedin')LinkedIn
        </a>
    @endif
    @if (!empty($facebook))
        <a class="btn" href="{{ $facebook }}" target="_blank" rel="noopener">
            @include('brochures.partials.icons.facebook')Facebook
        </a>
    @endif
</div>