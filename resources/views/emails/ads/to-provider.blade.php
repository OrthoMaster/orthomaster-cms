@extends('emails.template.ad-provider-side')

@section('title', "Votre annonce a de l'intérêt !")

@section('content')
    @if (sizeof($users) > 1)
        <p>Les utilisateurs OrthoMaster suivants :</p>
    @else
        <p>L'utilisateur OrthoMaster suivant :</p>
    @endif

    @foreach ($users as $user)
        <div class="info-box">
            <div class="info-row">
                <div class="info-label">Nom :</div>
                <div class="info-value">{{ $user['name'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email :</div>
                <div class="info-value">{{ $user['email'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fonction Pro :</div>
                <div class="info-value">{{ $user['profession'] }}</div>
            </div>
        </div>
    @endforeach

    <p>A montré un intérêt pour votre annonce <b>{{ $video }}</b>.</p>
    @if (sizeof($users) > 1)
        <p>Ils demandent à être contactés pour une présentation plus détaillée.</p>
    @else
        <p>Il demande a être contacté pour une présentation plus détaillée.</p>
    @endif

    <div class="divider"></div>

    <p style="margin-top: 30px;">Nous sommes heureux de contribuer à la visibilité de votre solution.</p>
    <p>
        <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
    </p>
@endsection
