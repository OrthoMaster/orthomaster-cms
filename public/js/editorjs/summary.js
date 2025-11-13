class Summary {
    static get toolbox() {
        return {
            title: 'Résumé',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="tool-icon-custom">
  <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
</svg>
`
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};
        this.config.placeholder = this.config.placeholder || 'Résumé …';
        // On/off mini-toolbar B/I (default On)
        this.config.toolbar = this.config.toolbar !== false;

        this.data = {
            text: (data && data.text) || ''
        };

        this.wrapper = null;
        this.contentEl = null;
        this.toolbarEl = null;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'summary-tool';

        const header = document.createElement('label');
        header.className = 'summary-header';
        header.textContent = 'Résumé';

        const body = document.createElement('div');
        body.className = 'summary-body';

        if (!this.readOnly && this.config.toolbar) {
            this.toolbarEl = this._createToolbar();
            body.appendChild(this.toolbarEl);
        }

        this.contentEl = document.createElement('div');
        this.contentEl.className = 'summary-content';
        this.contentEl.contentEditable = (!this.readOnly).toString();
        this.contentEl.setAttribute('data-placeholder', this.config.placeholder);
        if (this.data.text) {
            this.contentEl.innerHTML = this.data.text;
        }

        body.appendChild(this.contentEl);
        this.wrapper.appendChild(header);
        this.wrapper.appendChild(body);

        if (!this.readOnly) {
            this._bindEvents();
        }

        return this.wrapper;
    }

    _createToolbar() {
        const bar = document.createElement('div');
        bar.className = 'summary-toolbar';

        const btnBold = document.createElement('button');
        btnBold.type = 'button';
        btnBold.className = 'summary-btn';
        btnBold.title = 'Gras';
        btnBold.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M9 12L9 7.1C9 7.04477 9.04477 7 9.1 7H10.4C11.5 7 14 7.1 14 9.5C14 9.5 14 12 11 12M9 12V16.8C9 16.9105 9.08954 17 9.2 17H12.5C14 17 15 16 15 14.5C15 11.7046 11 12 11 12M9 12H11"></path></svg>';
        btnBold.addEventListener('click', () => {
            document.execCommand('bold', false);
            this.contentEl && this.contentEl.focus();
        });

        const btnItalic = document.createElement('button');
        btnItalic.type = 'button';
        btnItalic.className = 'summary-btn';
        btnItalic.title = 'Italique';
        btnItalic.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M13.34 10C12.4223 12.7337 11 17 11 17"></path><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M14.21 7H14.2"></path></svg>';
        btnItalic.addEventListener('click', () => {
            document.execCommand('italic', false);
            this.contentEl && this.contentEl.focus();
        });

        bar.appendChild(btnBold);
        bar.appendChild(btnItalic);
        return bar;
    }

    _bindEvents() {
        this.contentEl.addEventListener('input', () => {
            this.data.text = this.contentEl.innerHTML;
        });
    }

    save() {
        const text = (this.contentEl && this.contentEl.innerHTML || '').trim();
        return { text };
    }

    static get sanitize() {
        return {
            text: {
                b: true,
                strong: true,
                i: true,
                em: true,
                br: true,
                a: {
                    href: true,
                    target: '_blank',
                    rel: 'nofollow noopener noreferrer'
                },
                span: true
            },
        };
    }
}
