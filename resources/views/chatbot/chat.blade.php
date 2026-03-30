<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orthomaster AI | Research Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .chat-container::-webkit-scrollbar {
            width: 5px;
        }

        .chat-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 99px;
        }

        /* USER */
        .user-row {
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
            gap: 10px;
        }

        .user-bubble {
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            color: #fff;
            border-radius: 18px 18px 4px 18px;
            padding: 10px 16px;
            max-width: 60%;
            font-size: 0.92rem;
            line-height: 1.55;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
            word-break: break-word;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            min-width: 32px;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }

        /* AI */
        .ai-row {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
        }

        .ai-avatar {
            width: 32px;
            height: 32px;
            min-width: 32px;
            background: linear-gradient(135deg, #0ea5e9, #06b6d4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-top: 2px;
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
        }

        .ai-bubble {
            flex: 1;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px 18px 18px 18px;
            padding: 16px 20px;
            font-size: 0.92rem;
            line-height: 1.7;
            color: #1e293b;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            width: 100%;
            min-width: 0;
        }

        /* Labels */
        .msg-label {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .msg-label-user {
            color: #6366f1;
            text-align: right;
            padding-right: 42px;
        }

        .msg-label-ai {
            color: #0891b2;
            padding-left: 44px;
        }

        /* Prose */
        .ai-bubble h1,
        .ai-bubble h2,
        .ai-bubble h3 {
            font-weight: 600;
            margin: 0.8em 0 0.3em;
            color: #0f172a;
        }

        .ai-bubble h2 {
            font-size: 1.05rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .ai-bubble p {
            margin: 0.4em 0;
        }

        .ai-bubble ul,
        .ai-bubble ol {
            padding-left: 1.4em;
            margin: 0.4em 0;
        }

        .ai-bubble li {
            margin: 0.2em 0;
        }

        .ai-bubble strong {
            color: #0f172a;
            font-weight: 600;
        }

        .ai-bubble code {
            font-family: 'JetBrains Mono', monospace;
            background: #f1f5f9;
            border-radius: 4px;
            padding: 1px 5px;
            font-size: 0.82em;
            color: #0369a1;
        }

        .ai-bubble cite {
            color: #2563eb;
            cursor: pointer;
            font-weight: 600;
            font-style: normal;
            background: #eff6ff;
            padding: 1px 5px;
            border-radius: 4px;
            transition: background 0.15s;
        }

        .ai-bubble cite:hover {
            background: #dbeafe;
        }

        /* Quiz — pure CSS, no @apply */
        .quiz-option-btn {
            width: 100%;
            text-align: left;
            font-size: 0.875rem;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            cursor: pointer;
            margin-bottom: 8px;
            color: #1e293b;
            transition: border-color 0.15s, background 0.15s;
            font-family: 'Inter', sans-serif;
        }

        .quiz-option-btn:hover:not(:disabled) {
            border-color: #bfdbfe;
            background: #eff6ff;
        }

        .quiz-option-btn:disabled {
            cursor: default;
        }

        .quiz-correct {
            border-color: #10b981 !important;
            background: #ecfdf5 !important;
            color: #065f46;
            font-weight: 500;
        }

        .quiz-wrong {
            border-color: #f43f5e !important;
            background: #fff1f2 !important;
            color: #9f1239;
        }

        .quiz-badge {
            width: 24px;
            height: 24px;
            min-width: 24px;
            border-radius: 50%;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            transition: all 0.15s;
        }

        .quiz-badge-correct {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }

        .quiz-badge-wrong {
            background: #f43f5e;
            border-color: #f43f5e;
            color: #fff;
        }

        /* Typing */
        .typing-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #94a3b8;
            border-radius: 50%;
            animation: bounce 1.2s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes bounce {

            0%,
            60%,
            100% {
                transform: translateY(0);
            }

            30% {
                transform: translateY(-6px);
            }
        }
    </style>
</head>

<body class="bg-slate-50 h-screen flex flex-col">

    <header class="bg-white border-b px-6 py-3.5 shadow-sm flex justify-between items-center z-10">
        <div class="flex items-center gap-2.5">
            <span class="text-xl">📚</span>
            <h1
                class="text-base font-bold bg-gradient-to-r from-blue-700 to-indigo-600 bg-clip-text text-transparent tracking-tight">
                Orthomaster Research Core
            </h1>
        </div>
        <div class="flex items-center gap-2.5">
            <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></div>
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">System Online</span>
        </div>
    </header>

    <main class="flex-1 flex overflow-hidden">
        <div class="flex-1 flex flex-col min-w-0">
            <div id="chat-window"
                class="chat-container flex-1 overflow-y-auto px-4 md:px-10 lg:px-16 py-8 flex flex-col gap-2">
                <div id="welcome-screen" class="flex flex-col items-center justify-center h-full text-center space-y-3">
                    <div class="p-4 bg-white rounded-full shadow text-4xl">🤖</div>
                    <h2 class="text-lg font-semibold text-slate-700">How can I help with your studies today?</h2>
                    <p class="text-slate-400 text-sm max-w-xs">Ask anything about your curriculum. I remember our
                        conversation context.</p>
                </div>
            </div>

            <div id="typing-indicator" class="hidden px-4 md:px-10 lg:px-16 pb-2">
                <div class="ai-row">
                    <div class="ai-avatar">🤖</div>
                    <div class="ai-bubble" style="width:auto; padding: 12px 16px;">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            </div>

            <div
                class="px-4 md:px-10 lg:px-16 py-4 bg-white border-t border-slate-100 shadow-[0_-2px_8px_rgba(0,0,0,0.04)]">
                <div class="flex gap-3 items-center">
                    <input type="text" id="user-input" onkeydown="handleKeyPress(event)"
                        class="flex-1 border-2 border-slate-100 rounded-xl px-4 py-3 text-sm text-slate-700
                               focus:border-blue-500 focus:outline-none transition-colors placeholder-slate-300"
                        placeholder="Type your question… (Enter to send)">
                    <button onclick="sendFromButton()" id="btn-send"
                        class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-xl transition-all
                               hover:scale-105 active:scale-95 shadow-md shadow-blue-200 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <aside id="source-panel" class="w-80 bg-white border-l hidden flex flex-col shadow-xl flex-shrink-0">
            <div class="p-4 border-b bg-slate-50 flex justify-between items-center">
                <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2"><span>📑</span> Verified Sources
                </h3>
                <button onclick="closeSource()"
                    class="text-slate-400 hover:text-slate-600 text-lg leading-none">✕</button>
            </div>
            <div id="source-list" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
        </aside>
    </main>

    <script>
        let activeSessionId = null;
        let currentReferences = [];
        let isLoading = false;

        function handleKeyPress(event) {
            if (event.key === "Enter") {
                event.preventDefault();
                const input = document.getElementById('user-input');
                const question = input.value.trim();
                if (!question) return;
                input.value = '';
                askAI(question);
            }
        }

        function sendFromButton() {
            const input = document.getElementById('user-input');
            const question = input.value.trim();
            if (!question) return;
            input.value = '';
            askAI(question);
        }

        function closeSource() {
            document.getElementById('source-panel').classList.add('hidden');
        }

        async function askAI(question) {
            if (isLoading) return;
            isLoading = true;

            const chatWindow = document.getElementById('chat-window');
            const welcomeScreen = document.getElementById('welcome-screen');
            const typingIndicator = document.getElementById('typing-indicator');

            welcomeScreen.classList.add('hidden');

            // User message
            const labelUser = document.createElement('div');
            labelUser.className = 'msg-label msg-label-user';
            labelUser.textContent = 'You';
            chatWindow.appendChild(labelUser);

            const userRow = document.createElement('div');
            userRow.className = 'user-row mb-4';
            userRow.innerHTML =
                `<div class="user-bubble">${escapeHtml(question)}</div><div class="user-avatar">👤</div>`;
            chatWindow.appendChild(userRow);

            typingIndicator.classList.remove('hidden');
            scrollBottom(chatWindow);

            try {
                const res = await axios.post('/api/ask-notebook', {
                    question: question,
                    session_id: activeSessionId
                });

                const data = res.data;
                console.log(data);
                typingIndicator.classList.add('hidden');
                if (data.session_id) activeSessionId = data.session_id;

                let answerText = data.status === 'skipped' ?
                    `⚠️ **Out of Knowledge Base:** ${data.message}` :
                    data.answer;

                // Parse quiz
                let quizData = null;
                const jsonRegex = /```json\s*([\s\S]*?)\s*```/g;
                let m;
                while ((m = jsonRegex.exec(answerText)) !== null) {
                    try {
                        const parsed = JSON.parse(m[1]);
                        const items = parsed.questions || parsed.quiz || null;
                        if (items) {
                            quizData = {
                                questions: items
                            };
                            answerText = answerText.replace(m[0], '');
                        }
                    } catch (e) {
                        console.error('Quiz parse error', e);
                    }
                }

                currentReferences = data.references || [];

                // AI message — build bằng DOM để tránh XSS và escaping bug
                const labelAI = document.createElement('div');
                labelAI.className = 'msg-label msg-label-ai';
                labelAI.textContent = 'Orthomaster AI';
                chatWindow.appendChild(labelAI);

                const aiRow = document.createElement('div');
                aiRow.className = 'ai-row mb-6';

                const aiAvatar = document.createElement('div');
                aiAvatar.className = 'ai-avatar';
                aiAvatar.textContent = '🤖';

                const aiBubble = document.createElement('div');
                aiBubble.className = 'ai-bubble';

                // Prose content
                const proseDiv = document.createElement('div');
                const formattedText = answerText.replace(/\[(\d+)\]/g, (match, id) =>
                    `<cite onclick="showReference('${id}')">${match}</cite>`
                );
                proseDiv.innerHTML = marked.parse(formattedText);
                aiBubble.appendChild(proseDiv);

                // Quiz (DOM-based, không dùng innerHTML với data)
                if (quizData) {
                    const quizContainer = document.createElement('div');
                    renderQuizDOM(quizData, quizContainer);
                    aiBubble.appendChild(quizContainer);
                }

                aiRow.appendChild(aiAvatar);
                aiRow.appendChild(aiBubble);
                chatWindow.appendChild(aiRow);

                if (currentReferences.length > 0) renderSources(currentReferences);

            } catch (error) {
                typingIndicator.classList.add('hidden');
                console.error(error);

                const labelAI = document.createElement('div');
                labelAI.className = 'msg-label msg-label-ai';
                labelAI.textContent = 'Orthomaster AI';
                chatWindow.appendChild(labelAI);

                const aiRow = document.createElement('div');
                aiRow.className = 'ai-row mb-6';
                aiRow.innerHTML =
                    `<div class="ai-avatar">🤖</div><div class="ai-bubble">❌ <strong>System Error:</strong> Unable to reach AI Core.</div>`;
                chatWindow.appendChild(aiRow);
            } finally {
                isLoading = false;
                scrollBottom(chatWindow);
            }
        }

        // ── Render Quiz hoàn toàn bằng DOM — không truyền data qua onclick attribute ──
        function renderQuizDOM(quiz, container) {
            const questions = quiz.questions || [];
            if (!questions.length) return;

            const wrapper = document.createElement('div');
            Object.assign(wrapper.style, {
                marginTop: '20px',
                padding: '20px',
                background: '#f8f7ff',
                borderRadius: '16px',
                border: '1px solid #e0e7ff'
            });

            // Header
            const header = document.createElement('div');
            Object.assign(header.style, {
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                marginBottom: '16px'
            });
            header.innerHTML =
                `<span style="font-size:18px">📝</span><span style="font-size:11px;font-weight:700;color:#3730a3;text-transform:uppercase;letter-spacing:0.06em">Knowledge Check</span>`;
            wrapper.appendChild(header);

            questions.forEach((q, qIdx) => {
                const qBlock = document.createElement('div');
                qBlock.style.marginBottom = '20px';

                const qText = document.createElement('p');
                Object.assign(qText.style, {
                    fontWeight: '600',
                    fontSize: '0.875rem',
                    color: '#1e293b',
                    marginBottom: '10px'
                });
                qText.textContent = `${qIdx + 1}. ${q.question}`;
                qBlock.appendChild(qText);

                const feedbackEl = document.createElement('div');
                Object.assign(feedbackEl.style, {
                    marginTop: '10px',
                    fontSize: '0.78rem',
                    lineHeight: '1.55',
                    display: 'none'
                });

                q.options.forEach((opt, oIdx) => {
                    const btn = document.createElement('button');
                    btn.className = 'quiz-option-btn';

                    const badge = document.createElement('span');
                    badge.className = 'quiz-badge';
                    badge.textContent = String.fromCharCode(65 + oIdx); // A, B, C...

                    const label = document.createElement('span');
                    label.textContent = opt;

                    btn.appendChild(badge);
                    btn.appendChild(label);

                    btn.addEventListener('click', function() {
                        const allBtns = qBlock.querySelectorAll('.quiz-option-btn');
                        allBtns.forEach(b => b.disabled = true);

                        const isCorrect = (oIdx === q.correct_index);

                        if (isCorrect) {
                            // Chọn đúng — dùng trực tiếp btn/badge đã có trong closure
                            badge.classList.add('quiz-badge-correct');
                            badge.textContent = '✓';
                            btn.classList.add('quiz-correct');

                            feedbackEl.innerHTML = `
                                <div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;background:#ecfdf5;border-radius:10px;border:1px solid #6ee7b7">
                                    <span style="font-size:15px;line-height:1">✅</span>
                                    <div>
                                        <strong style="color:#065f46;font-size:0.8rem">Correct!</strong>
                                        <p style="color:#374151;margin:3px 0 0;font-size:0.78rem">${escapeHtml(q.explanation || '')}</p>
                                    </div>
                                </div>`;
                        } else {
                            // Chọn sai
                            badge.classList.add('quiz-badge-wrong');
                            badge.textContent = '✗';
                            btn.classList.add('quiz-wrong');

                            // Highlight đáp án đúng (với null guard)
                            const correctBtn = allBtns[q.correct_index] || null;
                            if (correctBtn) {
                                const correctBadge = correctBtn.querySelector('.quiz-badge');
                                correctBtn.classList.add('quiz-correct');
                                if (correctBadge) {
                                    correctBadge.classList.add('quiz-badge-correct');
                                    correctBadge.textContent = '✓';
                                }
                                // Pill "Correct answer"
                                const pill = document.createElement('span');
                                Object.assign(pill.style, {
                                    marginLeft: 'auto',
                                    fontSize: '10px',
                                    fontWeight: '700',
                                    background: '#10b981',
                                    color: '#fff',
                                    padding: '2px 8px',
                                    borderRadius: '99px',
                                    whiteSpace: 'nowrap'
                                });
                                pill.textContent = 'Correct answer';
                                correctBtn.appendChild(pill);
                            }

                            feedbackEl.innerHTML = `
                                <div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;background:#fff1f2;border-radius:10px;border:1px solid #fda4af">
                                    <span style="font-size:15px;line-height:1">❌</span>
                                    <div>
                                        <strong style="color:#9f1239;font-size:0.8rem">Incorrect.</strong>
                                        <p style="color:#374151;margin:3px 0 0;font-size:0.78rem">${escapeHtml(q.explanation || '')}</p>
                                    </div>
                                </div>`;
                        }
                        feedbackEl.style.display = 'block';
                    });

                    qBlock.appendChild(btn);
                });

                qBlock.appendChild(feedbackEl);
                wrapper.appendChild(qBlock);
            });

            container.appendChild(wrapper);
        }

        function renderSources(references) {
            const sourcePanel = document.getElementById('source-panel');
            const sourceList = document.getElementById('source-list');
            sourcePanel.classList.remove('hidden');
            sourceList.innerHTML = references.map(ref => `
                <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 shadow-sm transition-all hover:border-blue-200">
                    <span class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded block mb-2 w-fit">
                        REF [${ref.chunkInfo.documentMetadata.title}]
                    </span>
                    <p class="text-[11px] text-slate-600 italic leading-relaxed">"${ref.chunkInfo.content}"</p>
                </div>
            `).join('');
        }

        function showReference(id) {
            document.getElementById('source-panel').classList.remove('hidden');
        }

        function scrollBottom(el) {
            el.scrollTo({
                top: el.scrollHeight,
                behavior: 'smooth'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
    </script>
</body>

</html>
