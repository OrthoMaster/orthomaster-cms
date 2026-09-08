/**
 * Blocs renvoyant vers un contenu rattaché à la même vidéo.
 *
 * La liste des contenus disponibles est fournie à l'initialisation de
 * l'éditeur : elle ne dépend que de la vidéo en cours d'édition.
 * Le titre est réenregistré à chaque sélection, afin que le bloc reflète
 * l'intitulé courant du contenu visé.
 */
class MediaLinkedBlock {
    /** Redéfini par les sous-classes */
    static get kind() {
        return 'quiz';
    }

    static get label() {
        return 'Contenu';
    }

    static get icon() {
        return '';
    }

    static get toolbox() {
        return {
            title: this.label,
            icon: this.icon,
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, api, config, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};

        // Contenus proposés, injectés à l'initialisation de l'éditeur
        this.items = Array.isArray(this.config.items) ? this.config.items : [];

        this.data = {
            id: data?.id ? Number(data.id) : null,
            title: data?.title || '',
        };

        // Le contenu a pu être renommé ou supprimé depuis le dernier
        // enregistrement : le titre est resynchronisé à l'ouverture
        const current = this.items.find((item) => item.id === this.data.id);

        if (current) {
            this.data.title = current.title;
        }

        this.wrapper = null;
    }

    get kind() {
        return this.constructor.kind;
    }

    get label() {
        return this.constructor.label;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('linked-block', 'linked-block-' + this.kind);

        if (this.items.length === 0) {
            this.renderEmpty();
        } else {
            this.renderSelector();
        }

        return this.wrapper;
    }

    /** Aucun contenu de ce type n'est rattaché à la vidéo */
    renderEmpty() {
        this.wrapper.innerHTML = `
            <div class="linked-head">
                <span class="linked-kind">${this.label}</span>
            </div>
            <p class="linked-empty">
                Aucun élément de ce type n'est rattaché à cette vidéo.
            </p>
        `;
    }

    renderSelector() {
        const missing = this.data.id !== null
            && !this.items.some((item) => item.id === this.data.id);

        const options = this.items
            .map((item) => `<option value="${item.id}"${item.id === this.data.id ? ' selected' : ''}>${this.escape(item.title)}</option>`)
            .join('');

        this.wrapper.innerHTML = `
            <div class="linked-head">
                <span class="linked-kind">${this.label}</span>
                ${missing ? '<span class="linked-missing">Contenu introuvable</span>' : ''}
            </div>
            <select class="form-control linked-select" ${this.readOnly ? 'disabled' : ''}>
                <option value="">— Sélectionnez un élément —</option>
                ${options}
            </select>
        `;

        if (this.readOnly) return;

        this.wrapper.querySelector('.linked-select').addEventListener('change', (e) => {
            const id = e.target.value ? Number(e.target.value) : null;
            const item = this.items.find((entry) => entry.id === id);

            this.data = {
                id,
                title: item ? item.title : '',
            };

            // Le libellé « introuvable » disparaît dès qu'un choix valide est fait
            const flag = this.wrapper.querySelector('.linked-missing');
            if (flag) flag.remove();
        });
    }

    escape(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    save() {
        return {
            id: this.data.id,
            title: this.data.title,
        };
    }

    static get sanitize() {
        return { id: false, title: false };
    }
}

/* ---------- Quiz ---------- */

class MediaQuizBlock extends MediaLinkedBlock {
    static get kind() {
        return 'quiz';
    }

    static get label() {
        return 'Quiz';
    }

    static get icon() {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tool-icon-custom"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>`;
    }
}

/* ---------- Podcast ---------- */

class MediaPodcastBlock extends MediaLinkedBlock {
    static get kind() {
        return 'podcast';
    }

    static get label() {
        return 'Podcast';
    }

    static get icon() {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tool-icon-custom"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>`;
    }
}

/* ---------- Infographie ---------- */

class MediaInfographicBlock extends MediaLinkedBlock {
    static get kind() {
        return 'infographic';
    }

    static get label() {
        return 'Infographie';
    }

    static get icon() {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tool-icon-custom"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`;
    }
}