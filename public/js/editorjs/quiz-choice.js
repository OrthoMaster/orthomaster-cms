class QuizChoice {
    static get toolbox() {
        return {
            title: 'Quiz',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" /></svg>'
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};

        this.data = {
            question: data.question || '',
            answers: data.answers || ['', ''],
            correctAnswers: data.correctAnswers || [],
            multiSelect: data.multiSelect || false
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
                    <label>Question:</label>
                    <input type="text" class="quiz-question" placeholder="Entrez la question ..." value="${this.data.question}">
                </div>
                
                <div class="quiz-config-section">
                    <label class="quiz-config-label">
                        <input type="checkbox" class="quiz-multi-select" ${this.data.multiSelect ? 'checked' : ''} />
                        Choix multiples
                    </label>
                </div>
                
                <div class="quiz-answers-section">
                    <div class="quiz-answers-header">
                        <label>Réponses:</label>
                        <small class="quiz-instruction">
                            ${this.data.multiSelect ? 'Cochez les bonnes réponses' : 'Choisissez une réponse correcte'}
                        </small>
                    </div>
                    <div class="quiz-answers-list"></div>
                    <div class="text-right">
                        <button type="button" class="quiz-add-answer btn btn-sm btn-success">
                            <i class="voyager-list-add"></i>
                            <span class="hidden-xs hidden-sm">Ajouter une réponse</span>
                        </button>
                    </div>
                </div>
            </div>
        `;

        this._renderAnswers();
        this._bindEvents();
    }

    _renderAnswers() {
        const answersList = this.wrapper.querySelector('.quiz-answers-list');
        const inputType = this.data.multiSelect ? 'checkbox' : 'radio';

        answersList.innerHTML = '';

        this.data.answers.forEach((answer, index) => {
            const answerDiv = document.createElement('div');
            answerDiv.classList.add('quiz-answer-item');

            const isCorrect = this.data.correctAnswers.includes(index);
            const showRemoveButton = this.data.answers.length > 1;

            answerDiv.innerHTML = `
            <div class="quiz-answer-input-container">
                <div class="quiz-answer-input-wrapper">
                    <span class="quiz-answer-number">${String.fromCharCode(65 + index)}.</span>
                    <input type="text" 
                           class="quiz-answer-text" 
                           placeholder="Saisissez la réponse ..." 
                           value="${answer}"
                           data-index="${index}">
                    <button type="button" 
                            class="quiz-remove-answer${!showRemoveButton ? ' hidden' : ''}" 
                            data-index="${index}"
                            title="Supprimer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                <div class="quiz-answer-correct">
                    <input type="${inputType}" 
                        name="quiz-correct" 
                        value="${index}" 
                        ${isCorrect ? 'checked' : ''}
                        class="quiz-correct-input">
                    <label class="quiz-correct-label">Vrai</label>
                </div>
            </div>
            `;
            answersList.appendChild(answerDiv);
        });

        // Update instruction text
        const instruction = this.wrapper.querySelector('.quiz-instruction');
        instruction.textContent = this.data.multiSelect ? 'Cochez les bonnes réponses' : 'Choisissez une réponse correcte';
    }

    _bindEvents() {
        // Question input
        const questionInput = this.wrapper.querySelector('.quiz-question');
        questionInput.addEventListener('input', (e) => {
            this.data.question = e.target.value;
        });

        // Multi-select checkbox
        const multiSelectCheckbox = this.wrapper.querySelector('.quiz-multi-select');
        multiSelectCheckbox.addEventListener('change', (e) => {
            this.data.multiSelect = e.target.checked;
            this.data.correctAnswers = []; // Reset correct answers when switching modes
            this._renderAnswers();
        });

        // Add answer button
        const addAnswerBtn = this.wrapper.querySelector('.quiz-add-answer');
        addAnswerBtn.addEventListener('click', () => {
            this.data.answers.push('');
            this._renderAnswers();
        });

        // Answer text inputs
        this.wrapper.addEventListener('input', (e) => {
            if (e.target.classList.contains('quiz-answer-text')) {
                const index = parseInt(e.target.dataset.index);
                this.data.answers[index] = e.target.value;
            }
        });

        // Correct answer selections
        this.wrapper.addEventListener('change', (e) => {
            if (e.target.classList.contains('quiz-correct-input')) {
                const index = parseInt(e.target.value);

                if (this.data.multiSelect) {
                    // Multiple selection mode
                    if (e.target.checked) {
                        if (!this.data.correctAnswers.includes(index)) {
                            this.data.correctAnswers.push(index);
                        }
                    } else {
                        this.data.correctAnswers = this.data.correctAnswers.filter(i => i !== index);
                    }
                } else {
                    // Single selection mode
                    this.data.correctAnswers = e.target.checked ? [index] : [];

                    // Uncheck other radio buttons (backup for browser consistency)
                    const otherInputs = this.wrapper.querySelectorAll('.quiz-correct-input');
                    otherInputs.forEach((input, i) => {
                        if (i !== index) {
                            input.checked = false;
                        }
                    });
                }
            }
        });

        // Remove answer buttons
        this.wrapper.addEventListener('click', (e) => {
            if (e.target.classList.contains('quiz-remove-answer') && !e.target.classList.contains('hidden')) {
                const index = parseInt(e.target.dataset.index);

                // Remove the answer
                this.data.answers.splice(index, 1);

                // Update correct answers indices
                this.data.correctAnswers = this.data.correctAnswers
                    .filter(i => i !== index) // Remove deleted answer from correct answers
                    .map(i => i > index ? i - 1 : i); // Adjust indices for remaining answers

                this._renderAnswers();
            }
        });
    }

    save(blockContent) {
        // Filter out empty answers
        const cleanAnswers = this.data.answers.filter(answer => answer.trim() !== '');

        // Adjust correct answers based on cleaned answers
        const cleanCorrectAnswers = this.data.correctAnswers.filter(index =>
            index < cleanAnswers.length && cleanAnswers[index].trim() !== ''
        );

        return {
            question: this.data.question.trim(),
            answers: cleanAnswers,
            correctAnswers: cleanCorrectAnswers,
            multiSelect: this.data.multiSelect
        };
    }

    static get sanitize() {
        return {
            question: {},
            answers: {},
            correctAnswers: {},
            multiSelect: {}
        };
    }
}