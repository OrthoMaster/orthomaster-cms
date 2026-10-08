@php
    $avatar = $avatar ?? (isset($expert) ? $expert->avatar_url : null);
    $honorific = $honorific ?? 'Docteur';
    // 'expertise' passé explicitement (pages anciennes, /expert_demo) ; sinon les domaines de l'expert en base
    $expertiseLabels = isset($expertise)
        ? \App\Support\Brochures\ExpertiseCatalog::labels($expertise)
        : (isset($expert) ? $expert->domains->pluck('label')->all() : []);
@endphp
<section class="hero">
    <div class="col">
        <div class="avatar fade" @if (empty($avatar)) aria-hidden="true" @endif>
            @if (!empty($avatar))
                <img src="{{ $avatar }}" alt="{{ $name }}">
            @else
                <svg class="silhouette" viewBox="0 0 100 100" fill="currentColor"><circle cx="50" cy="38" r="19"/><path d="M12 100c0-22 17-37 38-37s38 15 38 37z"/></svg>
            @endif
        </div>

        <p class="partner fade">Partenaire <b>OrthoMaster</b></p>

        <h1 class="fade d2">
            @if ($honorific !== '')
                <span class="dr">{{ $honorific }}</span>
            @endif
            <span class="ln">{{ $name }}</span>
        </h1>

        @if (!empty($credentials))
            <p class="role creds fade d3">{{ $credentials }}</p>
        @endif

        @if (!empty($expertiseLabels))
            <ul class="expertise fade d3" aria-label="Domaines d’expertise">
                @foreach ($expertiseLabels as $field)
                    <li>{{ $field }}</li>
                @endforeach
            </ul>
        @endif

        @if (!empty($lede))
            <p class="lede fade d3">{{ $lede }}</p>
        @endif

        @include('brochures.partials.places', ['places' => $places ?? []])

        @include('brochures.partials.contact-buttons')

        <p class="hint fade d4">Faites défiler pour découvrir le parcours</p>
    </div>
</section>