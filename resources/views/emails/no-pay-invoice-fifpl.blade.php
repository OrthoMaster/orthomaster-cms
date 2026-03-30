@extends('emails.template.user-side')

@section('title', 'Votre convention de formation FIFPL')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>Veuillez trouver ci-joint votre facture <b>{{ $invoice->no }}</b> d'un montant de <b>{{ number_format($invoice->amount, 2, ',', ' ') }}</b> € de
            ORTHOMASTER. Veuillez procéder au paiement dans les meilleurs délais.</p>

        <p>Veuillez utiliser la communication suivante pour votre paiement : <b>{{ $invoice->no }}</b>.</p>

        <p>Vous pouvez procéder à un règlement immédiat via ce lien
            :<br>
            <strong><a href="{{ 'https://app.orthomaster.fr/open/invoice/' . $invoice->id }}">{{ 'https://app.orthomaster.fr/open/invoice/' . $invoice->id }}</a>
        </p>
        
        <div class="divider"></div>
        <p>Une question, un problème ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a>
        <p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
