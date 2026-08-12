/**
 * Chapitres en lecture seule.
 * La source de vérité est le champ « Chapitres » de la fiche média ;
 * ce bloc ne fait que les afficher et les enregistrer tels quels.
 */
class MediaChapters {
    constructor({ data, config, api }) {
        this.api = api;
        this.config = config || {};

        // Les chapitres de la fiche écrasent toujours ceux stockés dans le bloc
        const source = Array.isArray(this.config.chapters) && this.config.chapters.length
            ? this.config.chapters
            : (Array.isArray(data?.chapters) ? data.chapters : []);

        this.data = { chapters: source.map(ch => this._normalize(ch)).filter(Boolean) };
        this.nodes = {};
    }

    static get toolbox() {
        return {
            title: 'Chapitres',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tool-icon-custom"><path d="M21 5H3"/><path d="M10 12H3"/><path d="M10 19H3"/><path d="M15 12.003a1 1 0 0 1 1.517-.859l4.997 2.997a1 1 0 0 1 0 1.718l-4.997 2.997a1 1 0 0 1-1.517-.86z"/></svg>`
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    render() {
        this.nodes.wrapper = document.createElement('div');
        this.nodes.wrapper.className = 'video-chapters-tool';

        const header = document.createElement('div');
        header.className = 'vchap-header';
        header.textContent = 'Chapitres';
        this.nodes.wrapper.appendChild(header);

        const hint = document.createElement('div');
        hint.className = 'vchap-hint text-muted';
        hint.textContent = 'Repris du champ « Chapitres » de la fiche. Non modifiable ici.';
        this.nodes.wrapper.appendChild(hint);

        const body = document.createElement('div');
        body.className = 'vchap-body';
        this.nodes.wrapper.appendChild(body);

        this.nodes.list = document.createElement('div');
        this.nodes.list.className = 'vchap-list';
        body.appendChild(this.nodes.list);

        this._renderList();

        return this.nodes.wrapper;
    }

    _renderList() {
        this.nodes.list.innerHTML = '';

        if (!this.data.chapters.length) {
            const empty = document.createElement('div');
            empty.className = 'vchap-empty';
            empty.textContent = 'Aucun chapitre renseigné sur cette fiche.';
            this.nodes.list.appendChild(empty);
            return;
        }

        this.data.chapters.forEach((ch) => {
            const row = document.createElement('div');
            row.className = 'vchap-item';

            const left = document.createElement('div');
            left.className = 'vchap-time-cell';
            left.textContent = ch.time;

            const mid = document.createElement('div');
            mid.className = 'vchap-text-cell';
            mid.textContent = ch.text;

            row.appendChild(left);
            row.appendChild(mid);

            this.nodes.list.appendChild(row);
        });
    }

    save() {
        return {
            chapters: this.data.chapters.map(ch => ({ time: ch.time, text: ch.text }))
        };
    }

    static get sanitize() {
        return { chapters: { time: {}, text: {} } };
    }

    /* ---------- Helpers ---------- */

    _normalize(ch) {
        if (!ch) return null;

        let seconds = null;
        const text = (ch.text || '').toString().trim();

        if (typeof ch.seconds === 'number') {
            seconds = ch.seconds;
        } else if (typeof ch.time === 'string') {
            seconds = this._parseTime(ch.time.trim());
        }

        if (seconds === null || !text) return null;

        return { seconds, time: this._formatTime(seconds), text };
    }

    _parseTime(str) {
        let m;

        if ((m = /^(\d{1,2}):([0-5]\d):([0-5]\d)$/.exec(str))) {
            return (+m[1]) * 3600 + (+m[2]) * 60 + (+m[3]);
        }

        if ((m = /^(\d{1,2}):([0-5]\d)$/.exec(str))) {
            return (+m[1]) * 60 + (+m[2]);
        }

        return null;
    }

    _formatTime(total) {
        const h = Math.floor(total / 3600),
              m = Math.floor((total % 3600) / 60),
              s = total % 60;

        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }
}