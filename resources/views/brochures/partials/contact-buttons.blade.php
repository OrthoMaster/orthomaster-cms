{{--
    Boutons Doctolib / LinkedIn / OrthoMaster.
    Le bouton OrthoMaster n'apparaît qu'avec ?inapp=WEB.
    Variables : $doctolib, $linkedin (facultatives)
--}}
<div class="actions fade d4">
    @if (!empty($doctolib))
        <a class="btn" href="{{ $doctolib }}" target="_blank" rel="noopener">
            <img class="ico" src="{{ asset('brochures/img/doctolib.png') }}" alt="" width="18" height="18">Doctolib
        </a>
    @endif
    @if (!empty($linkedin))
        <a class="btn" href="{{ $linkedin }}" target="_blank" rel="noopener">
            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#0A66C2"/><path fill="#fff" transform="translate(12 12) scale(.6) translate(-12 -11.75)" d="M6.9 8.8H3.6V20h3.3V8.8zM5.2 3.5a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 13.6c0-3-1.6-5-4.3-5-1.4 0-2.4.8-2.8 1.5V8.8H10V20h3.3v-5.6c0-1.5.4-2.9 2.2-2.9 1.8 0 1.8 1.7 1.8 3V20h3.3l-.2-6.4z"/></svg>LinkedIn
        </a>
    @endif
    @if ($showOrthoMaster ?? false)
        <a class="btn om" href="{{ $orthomasterUrl }}" target="_blank" rel="noopener">
            <span class="omlogo" aria-hidden="true"></span>OrthoMaster
        </a>
    @endif
</div>
