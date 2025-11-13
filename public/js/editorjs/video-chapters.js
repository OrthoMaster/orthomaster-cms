class VideoChapters {
    static get toolbox() {
        return {
            title: 'Chapitres',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-video-icon lucide-list-video tool-icon-custom"><path d="M21 5H3"/><path d="M10 12H3"/><path d="M10 19H3"/><path d="M15 12.003a1 1 0 0 1 1.517-.859l4.997 2.997a1 1 0 0 1 0 1.718l-4.997 2.997a1 1 0 0 1-1.517-.86z"/></svg>`
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};

        const incoming = Array.isArray(data?.chapters) ? data.chapters : [];
        this.data = {
            chapters: incoming
                .map(ch => this._normalizeChapter(ch))
                .filter(Boolean)
                .sort((a, b) => a.seconds - b.seconds)
        };

        this.nodes = {};
    }

    render() {
        this.nodes.wrapper = document.createElement('div');
        this.nodes.wrapper.className = 'video-chapters-tool';

        const header = document.createElement('div');
        header.className = 'vchap-header';
        header.textContent = 'Video Chapters';
        this.nodes.wrapper.appendChild(header);

        const body = document.createElement('div');
        body.className = 'vchap-body';
        this.nodes.wrapper.appendChild(body);

        if (!this.readOnly) {
            const form = document.createElement('div');
            form.className = 'vchap-form';

            const timeWrap = document.createElement('div');
            timeWrap.className = 'vchap-field';
            const timeLabel = document.createElement('label');
            timeLabel.textContent = 'HH:MM:SS';
            this.nodes.timeInput = document.createElement('input');
            this.nodes.timeInput.type = 'text';
            this.nodes.timeInput.placeholder = '00:00:00';
            this.nodes.timeInput.className = 'vchap-time';
            timeWrap.appendChild(timeLabel);
            timeWrap.appendChild(this.nodes.timeInput);

            const textWrap = document.createElement('div');
            textWrap.className = 'vchap-field vchap-field-text';
            const textLabel = document.createElement('label');
            textLabel.textContent = 'Titre';
            this.nodes.textInput = document.createElement('input');
            this.nodes.textInput.type = 'text';
            this.nodes.textInput.placeholder = 'Chapitre/Titre …';
            this.nodes.textInput.className = 'vchap-text';
            textWrap.appendChild(textLabel);
            textWrap.appendChild(this.nodes.textInput);

            this.nodes.addBtn = document.createElement('button');
            this.nodes.addBtn.type = 'button';
            this.nodes.addBtn.className = 'vchap-add btn btn-sm btn-primary';
            this.nodes.addBtn.innerHTML = '<span>Ajouter</span>';

            form.appendChild(timeWrap);
            form.appendChild(textWrap);
            form.appendChild(this.nodes.addBtn);
            body.appendChild(form);

            this.nodes.form = form;
        }

        this.nodes.list = document.createElement('div');
        this.nodes.list.className = 'vchap-list';
        body.appendChild(this.nodes.list);

        this._renderList();
        if (!this.readOnly) this._bindEvents();

        return this.nodes.wrapper;
    }

    _bindEvents() {
        this.nodes.addBtn.addEventListener('click', () => this._handleAdd());
        this.nodes.textInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this._handleAdd();
            }
        });

        this.nodes.list.addEventListener('click', (e) => {
            const btn = e.target.closest('.vchap-remove');
            if (!btn) return;
            const idx = parseInt(btn.getAttribute('data-index'));
            if (Number.isInteger(idx)) {
                this.data.chapters.splice(idx, 1);
                this._renderList();
            }
        });
    }

    _handleAdd() {
        const rawTime = (this.nodes.timeInput.value || '').trim();
        const text = (this.nodes.textInput.value || '').trim();
        const seconds = this._parseTime(rawTime);

        if (seconds === null || text.length === 0) {
            this._bumpInvalid(seconds === null ? this.nodes.timeInput : this.nodes.textInput);
            return;
        }

        const chapter = {
            time: this._formatTime(seconds),
            seconds,
            text
        };

        this.data.chapters.push(chapter);
        this.data.chapters.sort((a, b) => a.seconds - b.seconds);
        this._renderList();

        this.nodes.timeInput.value = '';
        this.nodes.textInput.value = '';
        this.nodes.timeInput.focus();
    }

    _renderList() {
        this.nodes.list.innerHTML = '';
        if (!this.data.chapters.length) {
            const empty = document.createElement('div');
            empty.className = 'vchap-empty';
            empty.textContent = 'Aucun contenu.';
            this.nodes.list.appendChild(empty);
            return;
        }

        this.data.chapters.forEach((ch, index) => {
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

            if (!this.readOnly) {
                const right = document.createElement('div');
                right.className = 'vchap-actions';
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'vchap-remove';
                btn.setAttribute('data-index', String(index));
                btn.title = 'Retirer';
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>`;
                right.appendChild(btn);
                row.appendChild(right);
            }

            this.nodes.list.appendChild(row);
        });
    }

    save() {
        const chapters = this.data.chapters
            .filter(ch => typeof ch.seconds === 'number' && ch.text?.trim())
            .sort((a, b) => a.seconds - b.seconds)
            .map(ch => ({ time: ch.time, text: ch.text.trim() }));
        return { chapters };
    }

    static get sanitize() {
        return {
            chapters: {
                time: {},
                text: {}
            }
        };
    }

    /* ---------- Helpers ---------- */

    _parseTime(str) {
        // Cho phép hh:mm:ss hoặc mm:ss
        const full = /^(\d{1,2}):([0-5]\d):([0-5]\d)$/; // hh:mm:ss
        const short = /^(\d{1,2}):([0-5]\d)$/;          // mm:ss
        let m;

        if ((m = full.exec(str))) {
            const h = parseInt(m[1], 10);
            const min = parseInt(m[2], 10);
            const sec = parseInt(m[3], 10);
            return h * 3600 + min * 60 + sec;
        } else if ((m = short.exec(str))) {
            const min = parseInt(m[1], 10);
            const sec = parseInt(m[2], 10);
            return min * 60 + sec;
        }

        return null;
    }

    _formatTime(totalSec) {
        const h = Math.floor(totalSec / 3600);
        const m = Math.floor((totalSec % 3600) / 60);
        const s = totalSec % 60;
        return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s
            .toString()
            .padStart(2, '0')}`;
    }

    _normalizeChapter(ch) {
        if (!ch) return null;
        let seconds = null;
        let text = (ch.text || '').toString();

        if (typeof ch.seconds === 'number') {
            seconds = ch.seconds;
        } else if (typeof ch.time === 'string') {
            seconds = this._parseTime(ch.time.trim());
        }

        if (seconds === null || !text.trim()) return null;

        return { seconds, time: this._formatTime(seconds), text: text.trim() };
    }

    _bumpInvalid(el) {
        if (!el) return;
        el.classList.add('vchap-invalid');
        setTimeout(() => el.classList.remove('vchap-invalid'), 400);
        el.focus();
    }
}
