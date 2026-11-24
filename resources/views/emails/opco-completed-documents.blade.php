@extends('emails.template.user-side')

@section('title', 'OPCO-EP OrthoMaster : fin de formation')

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $user->name }},</div>
        <p>La formation de votre équipe prévue par la convention <b>{{ $contract->no }}</b> est arrivée à son terme.</p>
        <p>Vous trouverez ci-joint, pour information, la <b>facture</b> adressée à l’OPCO-EP et les <b>certificats de réalisation</b> de vos apprenants.</p>
        <p>Comme prévu, nous nous chargeons de déclarer la fin de formation auprès de l’OPCO-EP, qui nous règle directement.
            <b>Vous n’avez rien à payer ni rien à faire.</b></p>
        <p>Et bien sûr, on est là pour accompagner votre équipe dans de nouvelles formations !</p>

        <div class="divider"></div>
        <p>Une question, un problème ? <a href="https://www.orthomaster.fr/contact">Contactez-nous</a><p>

        <p style="margin-top: 30px;">Orthopédiquement,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
