/**
 * Bloc vidéo HLS pour les médias.
 * Le code provient de la fiche vidéo : il est imposé, jamais saisi.
 */
class MediaVideoBlock {
    constructor({ data, api, config, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};

        // Code de la vidéo courante, injecté à l'initialisation de l'éditeur
        this.videoCode = this.config.videoCode || null;

        this.data = {
            type: 'hls',
            code: data?.code || this.videoCode || ''
        };

        this.wrapper = null;
    }

    static get toolbox() {
        return {
            title: 'Vidéo',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" class="tool-icon-custom">
  <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
  <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" />
</svg>`
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('video-block', 'media-video-block');

        if (!this.data.code) {
            this.renderMissingCode();
        } else {
            this.renderForm();
        }

        return this.wrapper;
    }

    renderMissingCode() {
        this.wrapper.innerHTML = `
            <div class="video-mode-selection">
                <h4>Vidéo HLS</h4>
                <p class="text-muted" style="margin:0">
                    Aucun code vidéo n'est renseigné sur cette fiche.
                    Complétez le champ « Code vidéo » puis enregistrez le média.
                </p>
            </div>
        `;
    }

    renderForm() {
        this.wrapper.innerHTML = `
            <div class="video-hls-input">
                <div class="input-header">
                    <h4>Vidéo HLS <small>OrthoMaster</small></h4>
                </div>

                <div class="hls-form">
                    <label class="mv-label">Code vidéo</label>
                    <input type="text"
                           class="video-hls-field mv-code"
                           value="${this.escape(this.data.code)}"
                           readonly
                           disabled />
                </div>
            </div>
        `;
    }

    escape(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    save() {
        return {
            type: 'hls',
            code: this.videoCode || this.data.code
        };
    }

    static get sanitize() {
        return { type: false, code: false };
    }
}