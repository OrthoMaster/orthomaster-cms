class QuizMatch {
    static get toolbox() {
        return {
            title: 'Quiz Match',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h7m-7 5h7m-7 5h7M14 7h6M14 12h6M14 17h6"/></svg>'
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};
        // Estimate: config.image?.uploader?.uploadByFile(file) => { success:1, file:{ url } }
        this.imageUploader = this.config.image && this.config.image.uploader ? this.config.image.uploader : null;

        const incomingPairs = Array.isArray(data?.pairs) ? data.pairs : [];
        this.data = {
            question: data?.question || '',
            questionImage: data?.questionImage || null, // { url }
            // Cell: { text: string, imageUrl?: string }
            pairs: incomingPairs.map(p => this._normalizePair(p))
        };
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'quizmatch-tool';

        const q = document.createElement('div');
        q.className = 'qm-question-section';
        q.innerHTML = `
      <label>Question :</label>
      <input type="text" class="qm-question" value="${this._esc(this.data.question)}" ${this.readOnly ? 'disabled' : ''}>
      <div class="qm-question-image-block">
        ${this._renderQuestionImageArea()}
      </div>
    `;
        this.wrapper.appendChild(q);

        // Responses
        const r = document.createElement('div');
        r.className = 'qm-resp-section';
        r.innerHTML = `
      <div class="qm-resp-header">
        <div class="qm-col-title">Gauche</div>
        <div class="qm-col-title">Droite</div>
      </div>
      <div class="qm-resp-body"></div>
      ${this.readOnly ? '' : `
      <div class="qm-resp-actions text-right">
        <button type="button" class="qm-add-pair btn btn-sm btn-primary">Ajouter une réponse</button>
      </div>`}
    `;
        this.wrapper.appendChild(r);

        this.list = r.querySelector('.qm-resp-body');
        this._renderPairs();
        this._bindQuestionEvents();

        if (!this.readOnly) {
            this._bindEditEvents();
        }

        return this.wrapper;
    }

    /* ---------------- Question (giống QuizChoice) ---------------- */

    _renderQuestionImageArea() {
        if (this.data.questionImage?.url) {
            const url = this._esc(this.data.questionImage.url);
            return `
        <div class="qm-qimg-view">
          <img src="${url}" alt="" class="qm-qimg-preview"/>
          ${this.readOnly ? '' : `
          <div class="qm-qimg-actions">
            <button type="button" class="qm-qimg-replace btn btn-sm btn-warning">Changer</button>
            <button type="button" class="qm-qimg-remove btn btn-sm btn-danger">Supprimer</button>
          </div>`}
        </div>
      `;
        }
        if (this.readOnly) return '';
        return `
      <div class="qm-qimg-empty">
        <button type="button" class="qm-qimg-add btn btn-sm btn-primary">Ajouter une image</button>
        <input type="file" accept="image/*" class="qm-qimg-file" style="display:none"/>
      </div>
    `;
    }

    _bindQuestionEvents() {
        const qInput = this.wrapper.querySelector('.qm-question');
        if (qInput) qInput.addEventListener('input', e => this.data.question = e.target.value);

        if (this.readOnly) return;
        const block = this.wrapper.querySelector('.qm-question-section');

        // Add
        const addBtn = block.querySelector('.qm-qimg-add');
        const fileInput = block.querySelector('.qm-qimg-file');
        if (addBtn && fileInput) {
            addBtn.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', async (e) => {
                const f = e.target.files?.[0];
                if (!f) return;
                await this._uploadQuestionImage(f);
            });
        }
        // Replace
        const replaceBtn = block.querySelector('.qm-qimg-replace');
        if (replaceBtn) {
            replaceBtn.addEventListener('click', () => {
                const tmp = document.createElement('input');
                tmp.type = 'file'; tmp.accept = 'image/*'; tmp.style.display = 'none';
                tmp.addEventListener('change', async (ev) => {
                    const f = ev.target.files?.[0];
                    if (!f) return;
                    await this._uploadQuestionImage(f);
                    tmp.remove();
                });
                document.body.appendChild(tmp);
                tmp.click();
            });
        }
        // Remove
        const removeBtn = block.querySelector('.qm-qimg-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                if (this.data.questionImage?._blobUrl) URL.revokeObjectURL(this.data.questionImage._blobUrl);
                this.data.questionImage = null;
                block.querySelector('.qm-question-image-block').innerHTML = this._renderQuestionImageArea();
                this._bindQuestionEvents();
            });
        }
    }

    async _uploadQuestionImage(file) {
        if (!file || !file.type.startsWith('image/')) { alert('File không phải ảnh!'); return; }
        if (this.imageUploader?.uploadByFile) {
            try {
                const resp = await this.imageUploader.uploadByFile(file);
                if (resp?.success && resp.file?.url) {
                    if (this.data.questionImage?._blobUrl) URL.revokeObjectURL(this.data.questionImage._blobUrl);
                    this.data.questionImage = { url: resp.file.url };
                } else { alert('Upload failed!'); return; }
            } catch (e) { console.error(e); alert('Upload failed!'); return; }
        } else {
            const blobUrl = URL.createObjectURL(file);
            this.data.questionImage = { url: blobUrl, _blobUrl: blobUrl };
        }
        const holder = this.wrapper.querySelector('.qm-question-image-block');
        holder.innerHTML = this._renderQuestionImageArea();
        this._bindQuestionEvents();
    }

    /* ---------------- Pairs ---------------- */

    _renderPairs() {
        this.list.innerHTML = '';

        this.data.pairs.forEach((p, idx) => {
            const row = document.createElement('div');
            row.className = 'qm-row';
            row.setAttribute('data-id', p.id);

            const leftCell = document.createElement('div');
            leftCell.className = 'qm-cell';
            leftCell.innerHTML = this._renderEditableCell('left', p.left);

            const rightCell = document.createElement('div');
            rightCell.className = 'qm-cell';
            rightCell.innerHTML = this._renderEditableCell('right', p.right);

            const actions = document.createElement('div');
            actions.className = 'qm-row-actions';
            actions.innerHTML = `<button type="button" class="qm-remove-pair" data-index="${idx}"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg></button>`;

            row.appendChild(leftCell);
            row.appendChild(rightCell);
            row.appendChild(actions);
            this.list.appendChild(row);
        });

        if (!this.data.pairs.length) {
            const empty = document.createElement('div');
            empty.className = 'qm-empty';
            empty.textContent = 'Aucun réponse';
            this.list.appendChild(empty);
        }
    }

    _renderEditableCell(side, cell) {
        const textVal = this._esc(cell?.text || '');
        const hasImg = !!cell?.imageUrl;

        return `
      <div class="qm-cell-inner" data-side="${side}">
        <div class="qm-editor-text">
          <input type="text" class="qm-text-input" placeholder="..." value="${textVal}">
        </div>

        <div class="qm-editor-image" style="margin-top:6px;">
          ${hasImg ? `
            <div class="qm-img-view">
              <img src="${this._esc(cell.imageUrl)}" alt="" class="qm-img-preview"/>
              <div class="qm-img-actions">
                <button type="button" class="qm-img-replace btn btn-sm btn-warning">Changer</button>
                <button type="button" class="qm-img-remove btn btn-sm btn-danger">Supprimer</button>
              </div>
            </div>
          ` : `
            <div class="qm-img-empty">
              <button type="button" class="qm-img-add btn btn-sm btn-primary">Ajouter une image</button>
              <input type="file" accept="image/*" class="qm-img-file" style="display:none"/>
            </div>
          `}
        </div>
      </div>
    `;
    }

    _renderReadOnlyCell(id, cell) {
        const wrap = document.createElement('div');
        wrap.className = 'qm-readonly-cell';
        wrap.setAttribute('data-id', id);

        // Text
        const text = (cell?.text || '').trim();
        if (text) {
            const t = document.createElement('div');
            t.className = 'qm-readonly-text';
            t.textContent = text;
            wrap.appendChild(t);
        }

        // Image
        if (cell?.imageUrl) {
            const img = document.createElement('img');
            img.className = 'qm-readonly-img';
            img.src = cell.imageUrl;
            img.alt = '';
            wrap.appendChild(img);
        }

        // Nếu cả 2 trống, vẫn render khung rỗng cho đều layout
        if (!text && !cell?.imageUrl) {
            const empty = document.createElement('div');
            empty.className = 'qm-readonly-text';
            empty.style.opacity = '0.6';
            empty.textContent = '(trống)';
            wrap.appendChild(empty);
        }

        return wrap;
    }

    _bindEditEvents() {
        // Add pair
        const addBtn = this.wrapper.querySelector('.qm-add-pair');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                this.data.pairs.push({
                    id: this._uid(),
                    left: { text: '', imageUrl: '' },
                    right: { text: '', imageUrl: '' }
                });
                this._renderPairs();
            });
        }

        // Remove pair
        this.list.addEventListener('click', (e) => {
            const btn = e.target.closest('.qm-remove-pair');
            if (!btn) return;
            const idx = parseInt(btn.getAttribute('data-index'), 10);
            if (!Number.isInteger(idx)) return;
            this.data.pairs.splice(idx, 1);
            this._renderPairs();
        });

        // Text input
        this.list.addEventListener('input', (e) => {
            if (!e.target.classList.contains('qm-text-input')) return;
            const row = e.target.closest('.qm-row');
            const side = e.target.closest('.qm-cell-inner').getAttribute('data-side');
            const id = row.getAttribute('data-id');
            const pair = this.data.pairs.find(p => p.id === id);
            if (!pair) return;
            pair[side].text = e.target.value;
        });

        // Add image
        this.list.addEventListener('click', (e) => {
            const add = e.target.closest('.qm-img-add');
            if (!add) return;
            const cellInner = add.closest('.qm-cell-inner');
            const fileInput = cellInner.querySelector('.qm-img-file');
            fileInput && fileInput.click();
        });

        // Select file
        this.list.addEventListener('change', async (e) => {
            if (!e.target.classList.contains('qm-img-file')) return;
            const row = e.target.closest('.qm-row');
            const side = e.target.closest('.qm-cell-inner').getAttribute('data-side');
            const id = row.getAttribute('data-id');
            const pair = this.data.pairs.find(p => p.id === id);
            if (!pair) return;

            const file = e.target.files?.[0];
            if (!file || !file.type.startsWith('image/')) { alert('Image invalide!'); return; }
            const url = await this._uploadAnyImage(file);
            if (!url) return;

            pair[side].imageUrl = url;
            // re-render cell
            const cell = e.target.closest('.qm-cell');
            cell.innerHTML = this._renderEditableCell(side, pair[side]);
        });

        // Replace image
        this.list.addEventListener('click', (e) => {
            const btn = e.target.closest('.qm-img-replace');
            if (!btn) return;

            const cellInner = btn.closest('.qm-cell-inner');
            const row = btn.closest('.qm-row');
            const side = cellInner.getAttribute('data-side');
            const id = row.getAttribute('data-id');
            const pair = this.data.pairs.find(p => p.id === id);
            if (!pair) return;

            const tmp = document.createElement('input');
            tmp.type = 'file'; tmp.accept = 'image/*'; tmp.style.display = 'none';
            tmp.addEventListener('change', async (ev) => {
                const f = ev.target.files?.[0];
                if (!f || !f.type.startsWith('image/')) { tmp.remove(); return; }
                const url = await this._uploadAnyImage(f);
                if (!url) { tmp.remove(); return; }
                pair[side].imageUrl = url;
                const cell = cellInner.parentElement;
                cell.innerHTML = this._renderEditableCell(side, pair[side]);
                tmp.remove();
            });
            document.body.appendChild(tmp);
            tmp.click();
        });

        // Remove image
        this.list.addEventListener('click', (e) => {
            const btn = e.target.closest('.qm-img-remove');
            if (!btn) return;
            const cellInner = btn.closest('.qm-cell-inner');
            const row = btn.closest('.qm-row');
            const side = cellInner.getAttribute('data-side');
            const id = row.getAttribute('data-id');
            const pair = this.data.pairs.find(p => p.id === id);
            if (!pair) return;
            pair[side].imageUrl = '';
            const cell = cellInner.parentElement;
            cell.innerHTML = this._renderEditableCell(side, pair[side]);
        });
    }

    async _uploadAnyImage(file) {
        if (this.imageUploader?.uploadByFile) {
            try {
                const resp = await this.imageUploader.uploadByFile(file);
                if (resp?.success && resp.file?.url) return resp.file.url;
                alert('Upload failed!'); return null;
            } catch (e) { console.error(e); alert('Upload failed!'); return null; }
        } else {
            return URL.createObjectURL(file);
        }
    }

    _normalizePair(p) {
        const id = (p && p.id) ? String(p.id) : this._uid();
        const normSide = (s) => {
            return {
                text: (s && typeof s.text === 'string') ? s.text : '',
                imageUrl: (s && typeof s.imageUrl === 'string') ? s.imageUrl : (s?.url || '')
            };
        };
        return { id, left: normSide(p?.left), right: normSide(p?.right) };
    }

    _shuffle(arr) {
        const a = arr.slice();
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    }

    _uid() {
        return 'm_' + Math.random().toString(36).slice(2, 10);
    }

    _esc(s) {
        return (s || '').toString().replace(/"/g, '&quot;');
    }

    save() {
        const pairs = this.data.pairs.map(p => ({
            id: p.id,
            left: { text: (p.left.text || '').trim(), imageUrl: p.left.imageUrl || '' },
            right: { text: (p.right.text || '').trim(), imageUrl: p.right.imageUrl || '' }
        }));

        const questionImage = this.data.questionImage?.url ? { url: this.data.questionImage.url } : null;

        return {
            question: (this.data.question || '').trim(),
            questionImage,
            pairs
        };
    }

    static get sanitize() {
        return {
            question: {},
            questionImage: { url: {} },
            pairs: {
                id: {},
                left: { text: {}, imageUrl: {} },
                right: { text: {}, imageUrl: {} }
            }
        };
    }
}
