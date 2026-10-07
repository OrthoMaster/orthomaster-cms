{{--
    Hero standard (facultatif : un expert peut écrire son propre hero).

    @include('brochures.partials.hero', [
        'honorific'   => 'Docteur',                          // facultatif (Docteur, Professeur…), vide = masqué
        'name'        => 'Etienne Dahan',
        'credentials' => 'MB BS, PhD',                       // facultatif
        'expertise'   => [2, 7],                             // facultatif : ids de ExpertiseCatalog (ou libellés)
        'lede'        => 'Courte présentation.',             // facultatif
        'places'      => [['name' => '…', 'address' => '…', 'city' => '75005 Paris']],   // facultatif
        'doctolib'    => 'https://www.doctolib.fr/…',        // facultatif
        'linkedin'    => 'https://www.linkedin.com/in/…',    // facultatif
        'facebook'    => 'https://www.facebook.com/…',       // facultatif
        'avatar'      => null,   // facultatif : par défaut $expert->avatar_url (storage), sinon silhouette
    ])
--}}
@php
    $avatar = $avatar ?? (isset($expert) ? $expert->avatar_url : null);
    $honorific = $honorific ?? 'Docteur';
    $expertiseLabels = \App\Support\Brochures\ExpertiseCatalog::labels($expertise ?? []);
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