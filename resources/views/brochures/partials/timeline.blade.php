{{--
    Section chronologique (Formation, Parcours…). Les entrées vont de la plus récente à la plus ancienne.
    Chaque <li> de toutes les timelines de la page fait avancer l'éclairage du modèle 3D,
    quel que soit leur nombre.

    @include('brochures.partials.timeline', [
        'id'    => 'formation',          // facultatif
        'title' => 'Formation',
        'items' => [
            ['year' => '2014', 'title' => 'D.I.U. Podologie médicale', 'place' => 'Faculté de médecine de Nancy'],
        ],
    ])
--}}
<section class="block" @if (!empty($id)) id="{{ $id }}" @endif>
    <div class="col panel">
        <h2>{{ $title }}</h2>
        <ol class="tl">
            @foreach ($items as $item)
                <li>
                    <div class="yr">{{ $item['year'] }}</div>
                    <div>
                        <p class="tt">{{ $item['title'] }}</p>
                        @if (!empty($item['place']))
                            <p class="pl">{{ $item['place'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
