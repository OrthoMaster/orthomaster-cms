{{--
    Lieux d'exercice (facultatif). Chaque ligne est facultative : une ville seule suffit.
    $places = [['name' => 'Clinique du Sport', 'address' => '28 Boulevard Saint-Marcel', 'city' => '75005 Paris'], …]
--}}
@if (!empty($places))
    <ul class="workplaces fade d3" aria-label="Lieux d’exercice">
        @foreach ($places as $place)
            <li>
                @include('brochures.partials.icons.pin')
                <address class="wp-lines">
                    @if (!empty($place['name']))<p class="wp-name">{{ $place['name'] }}</p>@endif
                    @if (!empty($place['address']))<p class="wp-addr">{{ $place['address'] }}</p>@endif
                    @if (!empty($place['city']))<p class="wp-city">{{ $place['city'] }}</p>@endif
                </address>
            </li>
        @endforeach
    </ul>
@endif