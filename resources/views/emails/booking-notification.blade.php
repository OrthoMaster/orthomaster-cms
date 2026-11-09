@extends('emails.template.user-side')

{{-- E-mail des rendez-vous experts (Tutorat) : un seul gabarit, rempli par BookingMailService. --}}
@section('title', $heading)

@section('content')
    <div class="content">
        <div class="greeting">Bonjour {{ $name }},</div>

        @foreach ($lines as $line)
            <p>{!! $line !!}</p>
        @endforeach

        @if (!empty($details))
            <div class="credentials-box">
                @foreach ($details as $label => $value)
                    <div class="credential-row" style="margin: 10px 0;">
                        <span class="credential-label">{{ $label }}</span>
                        <div style="font-size: 15px; color: #333; line-height: 1.5;">{!! nl2br(e($value)) !!}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @if (!empty($action))
            <div>
                <a href="{{ $action['url'] }}" class="action-button">{{ $action['text'] }}</a>
            </div>
        @endif

        @if (!empty($note))
            <div class="warning-box">
                <p>{{ $note }}</p>
            </div>
        @endif

        <div class="divider"></div>

        <p style="margin-top: 30px;">À très vite sur OrthoMaster,<br>
            <strong style="color: #eb7b0f;">L'équipe OrthoMaster</strong>
        </p>
    </div>
@endsection
