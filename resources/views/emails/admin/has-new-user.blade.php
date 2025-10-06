@extends('emails.template.admin-side')

@section('title', 'Nouvel utilisateur')

@section('content')
    <p>Un nouvel utilisateur vient de s'inscrire sur la plateforme. Veuillez vérifier et traiter cette inscription.</p>

    <div class="info-box">
        <div class="info-row">
            <div class="info-label">Nom complet :</div>
            <div class="info-value">{{ $user->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Email :</div>
            <div class="info-value">{{ $user->email }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Date d'inscription :</div>
            <div class="info-value">{{ $user->created_at->format('d/M/Y H:i') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Statut :</div>
            <div class="info-value">En attente de validation</div>
        </div>
    </div>
@endsection
