class QuizChoice {
    static get toolbox() {
        return {
            title: 'Quiz',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="tool-icon-custom"><path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" /></svg>'
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

        this.data = {
            question: data?.question || '',
            // Only store { url }
            questionImage: data?.questionImage || null,
            answers: data?.answers || ['', ''],
            correctAnswers: data?.correctAnswers || [],
            multiSelect: data?.multiSelect || false
        };

        this.wrapper = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('quiz-tool');
        this._createQuizInterface();
        return this.wrapper;
    }

    _createQuizInterface() {
        this.wrapper.innerHTML = `
            <div class="quiz-container">
                <div class="quiz-question-section">
                    <label>Question :</label>
                    <input type="text" class="quiz-question" value="${this._escapeAttr(this.data.question)}">
                    <div class="quiz-question-image-block">
                        ${this._renderQuestionImageArea()}
                    </div>
                </div>
                
                <div class="quiz-config-section">
                    <label class="quiz-config-label">
                        <input type="checkbox" class="quiz-multi-select" ${this.data.multiSelect ? 'checked' : ''} ${this.readOnly ? 'disabled' : ''}/>
                        Choix multiples
                    </label>
                </div>
                
                <div class="quiz-answers-section">
                    <div class="quiz-answers-header">
                        <label>Réponses :</label>
                        <small class="quiz-instruction">
                            ${this.data.multiSelect ? 'Cochez les bonnes réponses' : 'Choisissez une réponse correcte'}
                        </small>
                    </div>
                    <div class="quiz-answers-list"></div>
                    ${this.readOnly ? '' : `
                    <div class="text-right">
                        <button type="button" class="quiz-add-answer btn btn-sm btn-primary">
                            <i class="voyager-list-add"></i>
                            <span>Ajouter une réponse</span>
                        </button>
                    </div>`}
                </div>
            </div>
        `;

        this._renderAnswers();
        this._bindEvents();
    }

    _renderQuestionImageArea() {
        if (this.data.questionImage && this.data.questionImage.url) {
            const url = this._escapeAttr(this.data.questionImage.url);
            return `
              <div class="quiz-qimg-view">
                <img src="${url}" alt="" class="quiz-qimg-preview"/>
                ${this.readOnly ? '' : `
                <div class="quiz-qimg-actions">
                  <button type="button" class="quiz-qimg-replace btn btn-sm btn-warning">Changer</button>
                  <button type="button" class="quiz-qimg-remove btn btn-sm btn-danger">Supprimer</button>
                </div>`}
              </div>
            `;
        }

        if (this.readOnly) return '';
        // Chưa có ảnh: chỉ có upload file
        return `
          <div class="quiz-qimg-empty">
            <button type="button" class="quiz-qimg-add btn btn-sm btn-primary">Ajouter une image</button>
            <input type="file" accept="image/*" class="quiz-qimg-file" style="display:none"/>
          </div>
        `;
    }

    _renderAnswers() {
        const answersList = this.wrapper.querySelector('.quiz-answers-list');
        const inputType = this.data.multiSelect ? 'checkbox' : 'radio';
        answersList.innerHTML = '';

        this.data.answers.forEach((answer, index) => {
            const answerDiv = document.createElement('div');
            answerDiv.classList.add('quiz-answer-item');

            const isCorrect = this.data.correctAnswers.includes(index);
            const showRemoveButton = !this.readOnly && this.data.answers.length > 1;

            answerDiv.innerHTML = `
            <div class="quiz-answer-input-container">
                <div class="quiz-answer-input-wrapper">
                    <span class="quiz-answer-number">${String.fromCharCode(65 + index)}.</span>
                    <input type="text" 
                           class="quiz-answer-text" 
                           value="${this._escapeAttr(answer)}"
                           data-index="${index}"
                           ${this.readOnly ? 'disabled' : ''}>
                    ${this.readOnly ? '' : `
                    <button type="button" 
                            class="quiz-remove-answer${!showRemoveButton ? ' hidden' : ''}" 
                            data-index="${index}"
                            title="Supprimer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>`}
                </div>
                <div class="quiz-answer-correct">
                    <input type="${inputType}" 
                        name="quiz-correct" 
                        value="${index}" 
                        ${isCorrect ? 'checked' : ''}
                        class="quiz-correct-input"
                        ${this.readOnly ? 'disabled' : ''}>
                    <label class="quiz-correct-label hidden-xs hidden-sm">Vrai</label>
                </div>
            </div>
            `;
            answersList.appendChild(answerDiv);
        });

        const instruction = this.wrapper.querySelector('.quiz-instruction');
        if (instruction) {
            instruction.textContent = this.data.multiSelect ? 'Cochez les bonnes réponses' : 'Choisissez une réponse correcte';
        }
    }

    _bindEvents() {
        // Question text
        const questionInput = this.wrapper.querySelector('.quiz-question');
        if (questionInput) {
            questionInput.addEventListener('input', (e) => {
                this.data.question = e.target.value;
            });
        }

        // Image: add/replace/remove (chỉ upload file)
        if (!this.readOnly) {
            const section = this.wrapper.querySelector('.quiz-question-section');

            // Add
            const fileInput = section.querySelector('.quiz-qimg-file');
            const addBtn = section.querySelector('.quiz-qimg-add');
            if (addBtn && fileInput) {
                addBtn.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', async (e) => {
                    const file = e.target.files && e.target.files[0];
                    if (!file) return;
                    await this._uploadImageByFile(file);
                });
            }

            // Replace
            const replaceBtn = section.querySelector('.quiz-qimg-replace');
            if (replaceBtn) {
                replaceBtn.addEventListener('click', () => {
                    const tmp = document.createElement('input');
                    tmp.type = 'file';
                    tmp.accept = 'image/*';
                    tmp.style.display = 'none';
                    tmp.addEventListener('change', async (e) => {
                        const f = e.target.files && e.target.files[0];
                        if (!f) return;
                        await this._uploadImageByFile(f);
                        tmp.remove();
                    });
                    document.body.appendChild(tmp);
                    tmp.click();
                });
            }

            // Remove
            const removeBtn = section.querySelector('.quiz-qimg-remove');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    this.data.questionImage = null;
                    const block = section.querySelector('.quiz-question-image-block');
                    block.innerHTML = this._renderQuestionImageArea();
                    this._bindEvents();
                });
            }
        }

        // Multi-select
        const multiSelectCheckbox = this.wrapper.querySelector('.quiz-multi-select');
        if (multiSelectCheckbox) {
            multiSelectCheckbox.addEventListener('change', (e) => {
                this.data.multiSelect = e.target.checked;
                this.data.correctAnswers = [];
                this._renderAnswers();
            });
        }

        if (!this.readOnly) {
            // Add answer
            const addAnswerBtn = this.wrapper.querySelector('.quiz-add-answer');
            if (addAnswerBtn) {
                addAnswerBtn.addEventListener('click', () => {
                    this.data.answers.push('');
                    this._renderAnswers();
                });
            }

            // Answer input
            this.wrapper.addEventListener('input', (e) => {
                if (e.target.classList.contains('quiz-answer-text')) {
                    const index = parseInt(e.target.dataset.index);
                    this.data.answers[index] = e.target.value;
                }
            });

            // Correct change
            this.wrapper.addEventListener('change', (e) => {
                if (e.target.classList.contains('quiz-correct-input')) {
                    const index = parseInt(e.target.value);
                    if (this.data.multiSelect) {
                        if (e.target.checked) {
                            if (!this.data.correctAnswers.includes(index)) {
                                this.data.correctAnswers.push(index);
                            }
                        } else {
                            this.data.correctAnswers = this.data.correctAnswers.filter(i => i !== index);
                        }
                    } else {
                        this.data.correctAnswers = e.target.checked ? [index] : [];
                        const otherInputs = this.wrapper.querySelectorAll('.quiz-correct-input');
                        otherInputs.forEach((input, i) => { if (i !== index) input.checked = false; });
                    }
                }
            });

            // Remove answer
            this.wrapper.addEventListener('click', (e) => {
                if (e.target.classList.contains('quiz-remove-answer') && !e.target.classList.contains('hidden')) {
                    const index = parseInt(e.target.dataset.index);
                    this.data.answers.splice(index, 1);
                    this.data.correctAnswers = this.data.correctAnswers
                        .filter(i => i !== index)
                        .map(i => i > index ? i - 1 : i);
                    this._renderAnswers();
                }
            });
        }
    }

    async _uploadImageByFile(file) {
        if (this.imageUploader && typeof this.imageUploader.uploadByFile === 'function') {
            try {
                const resp = await this.imageUploader.uploadByFile(file);
                if (resp && resp.success && resp.file && resp.file.url) {
                    this.data.questionImage = { url: resp.file.url };
                } else {
                    alert('Upload successfully!');
                    return;
                }
            } catch (e) {
                console.error(e);
                alert('Upload failed!');
                return;
            }
        } else {
            
            const tempUrl = URL.createObjectURL(file);
            this.data.questionImage = { url: tempUrl };
        }
        const section = this.wrapper.querySelector('.quiz-question-section .quiz-question-image-block');
        section.innerHTML = this._renderQuestionImageArea();
        this._bindEvents();
    }

    _escapeAttr(str) {
        return (str || '').toString().replace(/"/g, '&quot;');
    }

    save() {
        const cleanAnswers = this.data.answers.filter(a => a.trim() !== '');
        const cleanCorrect = this.data.correctAnswers.filter(i =>
            i < cleanAnswers.length && cleanAnswers[i].trim() !== ''
        );
        const questionImage = this.data.questionImage && this.data.questionImage.url
            ? { url: this.data.questionImage.url }
            : null;

        return {
            question: this.data.question.trim(),
            questionImage,
            answers: cleanAnswers,
            correctAnswers: cleanCorrect,
            multiSelect: this.data.multiSelect
        };
    }

    static get sanitize() {
        return {
            question: {},
            questionImage: { url: {} },
            answers: {},
            correctAnswers: {},
            multiSelect: {}
        };
    }
}
