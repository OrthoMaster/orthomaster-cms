@extends('emails.template.user-side')

@section('title', 'Les devis')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name . ' ' . $user->first_name }},</div>
        <p>Nous vous remercions pour votre inscription à notre formation.</p>
        <p>Veuillez trouver en pièces jointes les devis correspondant aux formations sélectionnées lors de votre
            inscription.</p>



        <p style="margin-top: 30px;">Cordialement,<br>
            <strong style="color: #eb7b0f;">L'équipe Support Client</strong>
        </p>
    </div>
@endsection
