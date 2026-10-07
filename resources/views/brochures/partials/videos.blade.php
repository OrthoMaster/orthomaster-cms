{{--
    Liste des vidéos OrthoMaster (commune). Masquée si $videos est vide.
    Chaque vidéo : title, description, duration, url, thumbnail (facultatif)
--}}
@if (!empty($videos))
<section class="block videos" id="videos" data-om-zone>
    <div class="vbox panel">
        <h2>Ses vidéos avec OrthoMaster</h2>
        <div class="vgrid">
            @foreach ($videos as $video)
                <a class="vcard" href="{{ $video['url'] ?? '#' }}" target="_blank" rel="noopener">
                    <div class="poster">
                        @if (!empty($video['thumbnail']))
                            <img src="{{ $video['thumbnail'] }}" alt="" loading="lazy">
                        @else
                            <span class="ring"></span>
                        @endif
                        <span class="play"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 4.5v15l12-7.5z"/></svg></span>
                        @if (!empty($video['duration']))
                            <span class="dur">{{ $video['duration'] }}</span>
                        @endif
                    </div>
                    <div class="vbody">
                        <h3>{{ $video['title'] }}</h3>
                        @if (!empty($video['description']))
                            <p>{{ $video['description'] }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
