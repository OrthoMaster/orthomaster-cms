@extends('emails.template.admin-side')

@section('title', 'Les nouveau devis')

@section('content')
    <p>Les nouveau devis signé par le client.</p>

    <div class="info-box">
        <div class="info-row">
            <div class="info-label">Société :</div>
            <div class="info-value">{{ $contract->company->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Organisme :</div>
            <div class="info-value">{{ $contract->organization }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Date de signature :</div>
            <div class="info-value">{{ $contract->updated_at->format('d/m/Y H:i') }}</div>
        </div>
    </div>
@endsection
