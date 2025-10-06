<div class="footer">
    <div class="footer-logo">
        <img src="{{ setting('mail.header_logo') }}"
            alt="Orthomaster" width="20" height="20" style="margin-right: 5px"/>
        Orthomaster
    </div>

    <div class="app-buttons">
        <p style="color: #666; font-size: 13px; display: inline-block">Téléchargez l'application :</p>
        <a href="{{ setting('mail.ios_download_link') }}" class="app-button">iOS</a>
        <a href="{{ setting('mail.android_download_link') }}" class="app-button">Android</a>
    </div>

    @if (isset($mailTo))
        <div class="footer-note">
            Ce message a été envoyé à <strong>{{ $mailTo }}</strong>. Si vous avez des questions ou des réclamations, veuillez <a href="https://www.orthomaster.fr/contact">nous contacter</a>.
        </div>
    @endif

    <div class="footer-links">
        <a href="https://www.orthomaster.fr/terms-conditions">CGV</a>
        <a href="https://www.orthomaster.fr/mentions-l%C3%A9gales">Mentions légales</a>
        <a href="https://www.orthomaster.fr/politique-de-confidentialit%C3%A9">Politique de confidentialité</a>
        <a href="https://www.orthomaster.fr/contact">Nous contacter</a>
    </div>

    <div class="footer-address">{{ setting('mail.footer_address') }}</div>
</div>
