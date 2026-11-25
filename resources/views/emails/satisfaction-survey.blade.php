@extends('emails.template.user-side')

@section('title', 'Votre avis sur votre formation OrthoMaster')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $name }},</div>
        @if ($reminder)
            <p>Il y a quelques semaines, nous vous avons demandé votre avis sur votre formation. Si vous n'avez pas encore eu le temps d'y répondre, il n'est pas trop tard !</p>
        @else
            <p>Il y a quelques mois, vous avez suivi une formation OrthoMaster :</p>
        @endif
        <ul style="margin: 10px 0 20px 20px; color: #555;">
            @foreach ($packages as $package)
                <li style="margin-bottom: 4px;"><b>{{ $package }}</b></li>
            @endforeach
        </ul>
        <p>Votre avis nous est précieux pour améliorer nos formations. Le questionnaire prend environ <b>2 minutes</b>.</p>

        <div class="cta-container">
            <a href="{{ $url }}" class="cta-button">Donner mon avis</a>
        </div>

        <div class="divider"></div>
        <p>Une question, une réclamation ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a><p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
