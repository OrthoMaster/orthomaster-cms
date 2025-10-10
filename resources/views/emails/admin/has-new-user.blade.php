@extends('emails.template.admin-side')

@section('title', $user->status == 1 ? 'Nouvel utilisateur' : 'Nouvel utilisateur - En attente de verifier')

@section('content')
    @if ($user->status == 1)
        <p>Un nouvel utilisateur vient de s'inscrire sur la plateforme.</p>
    @else
        <p>Un nouvel utilisateur vient de s'inscrire sur la plateforme. Veuillez vérifier et traiter cette inscription.</p>
    @endif

    <div class="info-box">
        <div class="info-row">
            <div class="info-label">Nom complet :</div>
            <div class="info-value">{{ $user->name . ' ' . $user->first_name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Profession :</div>
            <div class="info-value">{{ $user->student_flg ? 'Étudiant' : 'Professionel de santé' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Email :</div>
            <div class="info-value">{{ $user->email }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Date d'inscription :</div>
            <div class="info-value">{{ $user->created_at->format('d/m/Y H:i') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Statut :</div>
            @if ($user->status == 1)
                <div class="info-value text-success">Vérifié automatiquement</div>
            @else
                <div class="info-value text-danger">En attente de validation</div>
            @endif
        </div>
    </div>
@endsection
