@extends('quiz.app')

@section('title', $topic->name . ' — OrthoMaster')

@section('styles')
/* ---------- intro ---------- */
.intro-top{display:flex;justify-content:center;margin-bottom:18px}
.intro h1{font-family:'Bricolage Grotesque',sans-serif;font-size:clamp(1.9rem,8vw,2.5rem);line-height:1.08;text-align:center;margin:8px 0 6px}
.intro h1 span{background:linear-gradient(120deg,var(--amber),var(--orange));-webkit-background-clip:text;background-clip:text;color:transparent}
.intro p.sub{text-align:center;color:#8a6c3d;font-weight:400;font-size:.95rem;margin-bottom:22px}
.field{margin-bottom:16px;text-align:left}
.field label{display:block;font-weight:600;font-size:.9rem;margin-bottom:7px}
.meta-row{display:flex;gap:10px;justify-content:center;margin:16px 0 24px;flex-wrap:wrap}
.chip{background:#FFF3E0;color:#A4631B;font-size:.8rem;font-weight:600;padding:7px 13px;border-radius:99px;display:flex;align-items:center;gap:6px}
.err{color:var(--danger);font-size:.82rem;margin-top:6px;display:none}

/* ---------- logo dans le brandmark ---------- */
.brandmark .logo{
  width:20px;height:20px;border-radius:6px;object-fit:contain;
  display:block;flex-shrink:0;
}

/* ---------- quiz ---------- */
.quiz-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px}
.qcount{font-weight:600;color:#A4631B;font-size:.9rem}
.timer{width:56px;height:56px;position:relative;display:grid;place-items:center;flex-shrink:0}
.timer svg{transform:rotate(-90deg)}
.timer .track{fill:none;stroke:#FFE6C0;stroke-width:6}
.timer .bar{fill:none;stroke:var(--orange);stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset 1s linear, stroke .3s}
.timer .num{position:absolute;font-weight:700;font-size:1.1rem}
.progress{height:9px;background:#FFE6C0;border-radius:99px;overflow:hidden;margin-bottom:22px}
.progress .fill{height:100%;background:linear-gradient(90deg,var(--amber),var(--orange));border-radius:99px;transition:width .5s cubic-bezier(.2,.9,.3,1.2)}
.qtext{font-family:'Bricolage Grotesque',sans-serif;font-size:clamp(1.25rem,5.5vw,1.6rem);line-height:1.25;margin-bottom:22px}

/* réponses : une par ligne (peuvent être longues) */
.answers{display:flex;flex-direction:column;gap:12px}
.answer{
  font-family:inherit;font-size:1rem;font-weight:500;text-align:left;cursor:pointer;
  border:none;color:#fff;padding:16px 16px;border-radius:16px;width:100%;
  display:flex;align-items:center;gap:13px;line-height:1.35;
  transition:transform .12s, filter .12s;
  box-shadow:0 8px 16px -8px rgba(0,0,0,.32);
}
.answer:hover{transform:translateY(-2px)}
.answer:active{transform:scale(.99)}
.answer .ic{width:32px;height:32px;border-radius:9px;background:rgba(255,255,255,.28);display:grid;place-items:center;font-weight:800;flex-shrink:0}
.answer .lbl{flex:1}
.answer.c0{background:linear-gradient(135deg,#FF6B6B,#E84545)}
.answer.c1{background:linear-gradient(135deg,#4DA3FF,#2B7DE0)}
.answer.c2{background:linear-gradient(135deg,#FFAF32,#FF9600)}
.answer.c3{background:linear-gradient(135deg,#36B37E,#1E9E68)}
.answer.c4{background:linear-gradient(135deg,#9B6BFF,#6E37E0)}
.answer.c5{background:linear-gradient(135deg,#2BC4C4,#159E9E)}
.answer.dim{filter:grayscale(.5) brightness(.85);opacity:.5;transform:none}
.answer.chosen{outline:3px solid #fff;outline-offset:2px;transform:scale(1.01)}
.answers.locked .answer{pointer-events:none}

/* ---------- résultat ---------- */
.result{text-align:center;display:flex;flex-direction:column}
.score-ring{width:180px;height:180px;margin:4px auto 16px;position:relative}
.score-ring svg{transform:rotate(-90deg)}
.score-ring .track{fill:none;stroke:#FFE6C0;stroke-width:15}
.score-ring .bar{fill:none;stroke:url(#grad);stroke-width:15;stroke-linecap:round;transition:stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1)}
.score-ring .val{position:absolute;inset:0;display:grid;place-content:center;align-items:center}
.score-ring .val b{font-family:'Bricolage Grotesque',sans-serif;font-size:2.9rem;line-height:1}
.score-ring .val small{font-weight:600;color:#A4631B;font-size:.72rem;letter-spacing:1px}
.verdict-badge{display:inline-block;font-weight:700;font-size:.9rem;padding:7px 18px;border-radius:99px;color:#fff;margin-bottom:12px;align-self:center}
.verdict-title{font-family:'Bricolage Grotesque',sans-serif;font-size:1.5rem;margin-bottom:9px}
.verdict-text{color:#6f5733;font-size:.95rem;line-height:1.6;margin:0 auto 24px}
.contact{background:#FFF7EC;border:2px dashed #FFD79A;border-radius:18px;padding:20px;text-align:left}
.contact h3{font-size:1.05rem;margin-bottom:5px}
.contact p{color:#8a6c3d;font-size:.85rem;margin-bottom:14px;line-height:1.5}
.checkbox-row{display:flex;align-items:flex-start;gap:9px;margin:12px 0 16px;font-size:.85rem;color:#6f5733;line-height:1.45}
.checkbox-row input{width:19px;height:19px;accent-color:var(--orange);margin-top:1px;flex-shrink:0}
.success-msg{display:none;background:#E7F8F0;color:#1E7A52;border-radius:13px;padding:13px 16px;font-weight:600;margin-top:13px;text-align:center;font-size:.9rem}
.restart{margin-top:16px;background:none;color:#A4631B;font-weight:600;border:none;cursor:pointer;font-family:inherit;font-size:.9rem;text-decoration:underline}
.spacer{flex:1}
@endsection

@section('content')

  {{-- ===== ÉCRAN INTRO ===== --}}
  <section class="screen active" id="screen-intro">
    <div class="spacer"></div>
    <div class="card intro">
      <div class="intro-top"><div class="brandmark"><img class="logo" src="{{ setting('system.web_icon') }}" alt="OrthoMaster">OrthoMaster</div></div>
      <h1>{{ $topic->name }}</h1>
      <p class="sub">{{ $topic->description ?? 'Répondez honnêtement — il n’y a ni bonne ni mauvaise réponse.' }}</p>
      <div class="meta-row">
        <div class="chip">🧩 {{ count($questionsJson) }} questions</div>
        <div class="chip">⏱️ {{ $timePerQuestion }}s par question</div>
      </div>
      <div class="field">
        <label for="name">Votre prénom</label>
        <input class="input" id="name" placeholder="Saisissez votre prénom…" autocomplete="off" maxlength="120">
        <div class="err" id="nameErr">Veuillez saisir votre prénom pour commencer.</div>
      </div>
      <button class="btn" id="startBtn">Commencer →</button>
    </div>
    <div class="spacer"></div>
  </section>

  {{-- ===== ÉCRAN QUIZ ===== --}}
  <section class="screen" id="screen-quiz">
    <div class="card" style="margin-top:6px">
      <div class="quiz-head">
        <div class="qcount">Question <b id="qNow">1</b> / <span id="qTotal">{{ count($questionsJson) }}</span></div>
        <div class="timer">
          <svg width="56" height="56" viewBox="0 0 56 56">
            <circle class="track" cx="28" cy="28" r="24"></circle>
            <circle class="bar" id="timerBar" cx="28" cy="28" r="24"></circle>
          </svg>
          <div class="num" id="timerNum">{{ $timePerQuestion }}</div>
        </div>
      </div>
      <div class="progress"><div class="fill" id="progressFill" style="width:0"></div></div>
      <h2 class="qtext" id="qText">…</h2>
      <div class="answers" id="answers"></div>
    </div>
  </section>

  {{-- ===== ÉCRAN RÉSULTAT ===== --}}
  <section class="screen" id="screen-result">
    <div class="spacer"></div>
    <div class="card result">
      <div class="intro-top" style="margin-bottom:16px"><div class="brandmark"><img class="logo" src="{{ setting('system.web_icon') }}" alt="OrthoMaster">OrthoMaster</div></div>
      <div class="score-ring">
        <svg width="180" height="180" viewBox="0 0 180 180">
          <defs><linearGradient id="grad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#FFAF32"/><stop offset="100%" stop-color="#FF9600"/>
          </linearGradient></defs>
          <circle class="track" cx="90" cy="90" r="76"></circle>
          <circle class="bar" id="scoreBar" cx="90" cy="90" r="76"></circle>
        </svg>
        <div class="val"><div><b id="scoreVal">0</b><br><small>/ 100 POINTS</small></div></div>
      </div>
      <div class="verdict-badge" id="verdictBadge">—</div>
      <div class="verdict-title" id="verdictTitle">—</div>
      <p class="verdict-text" id="verdictText">—</p>

      <div class="contact">
        <h3>📩 Gardons le contact</h3>
        <p>Laissez votre e-mail pour recevoir des informations sur OrthoMaster et des newsletters sur le développement de votre pôle mobilité.</p>
        <input class="input" id="email" type="email" placeholder="votre@email.com" maxlength="180">
        <div class="err" id="emailErr">Veuillez saisir une adresse e-mail valide.</div>
        <label class="checkbox-row">
          <input type="checkbox" id="allowContact" checked>
          <span>J’accepte qu’OrthoMaster m’envoie mon résultat et me contacte.</span>
        </label>
        <button class="btn" id="sendBtn">Envoyer</button>
        <div class="success-msg" id="successMsg">✓ C’est noté ! OrthoMaster vous contactera bientôt.</div>
      </div>
      <button class="restart" id="restartBtn">Recommencer le questionnaire</button>
    </div>
    <div class="spacer"></div>
  </section>

@endsection

@section('scripts')
<script>
const QUESTIONS = @json($questionsJson);
const TIME_PER_Q = {{ (int) $timePerQuestion }};
const SUBMIT_URL = "{{ route('quiz.submit', $topic->slug) }}";
const CONTACT_URL_TPL = "{{ url('/submissions') }}/__ID__/contact";
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

const $ = s => document.querySelector(s);
const show = id => { document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active')); $('#'+id).classList.add('active'); window.scrollTo(0,0); };

let state = { name:"", idx:0, answers:[], timer:null, timeLeft:0, submissionId:null, finalScore:0 };

/* ---------- INTRO ---------- */
$('#startBtn').addEventListener('click', start);
$('#name').addEventListener('keydown', e=>{ if(e.key==='Enter') start(); });
$('#name').addEventListener('input', ()=>{ $('#name').style.borderColor=''; $('#nameErr').style.display='none'; });

function start(){
  const name = $('#name').value.trim();
  if(!name){ $('#name').style.borderColor='var(--danger)'; $('#nameErr').style.display='block'; $('#name').focus(); return; }
  state.name = name; state.idx=0; state.answers=[];
  show('screen-quiz'); renderQuestion();
}

/* ---------- QUIZ ---------- */
const TIMER_CIRC = 2*Math.PI*24;
$('#timerBar').style.strokeDasharray = TIMER_CIRC;

// Mélange Fisher–Yates
function shuffle(arr){
  for(let i=arr.length-1;i>0;i--){
    const j = Math.floor(Math.random()*(i+1));
    [arr[i],arr[j]] = [arr[j],arr[i]];
  }
  return arr;
}

function renderQuestion(){
  const q = QUESTIONS[state.idx];
  $('#qNow').textContent = state.idx+1;
  $('#qText').textContent = q.content;
  $('#progressFill').style.width = (state.idx/QUESTIONS.length*100)+'%';

  // Mélange les réponses (copie), sans toucher aux données d'origine.
  const shuffled = shuffle([...q.answers]);

  const wrap = $('#answers'); wrap.className='answers'; wrap.innerHTML='';
  shuffled.forEach((a,i)=>{
    const btn = document.createElement('button');
    btn.className = 'answer c'+(i%6);
    btn.innerHTML = `<span class="ic">${String.fromCharCode(65+i)}</span><span class="lbl"></span>`;
    btn.querySelector('.lbl').textContent = a.content;
    btn.addEventListener('click', ()=> pickAnswer(a.id, i));
    wrap.appendChild(btn);
  });
  startTimer();
}

function startTimer(){
  clearInterval(state.timer);
  state.timeLeft = TIME_PER_Q;
  updateTimer();
  state.timer = setInterval(()=>{
    state.timeLeft--; updateTimer();
    if(state.timeLeft<=0){ clearInterval(state.timer); pickAnswer(null, null); }
  },1000);
}
function updateTimer(){
  $('#timerNum').textContent = state.timeLeft;
  const ratio = state.timeLeft/TIME_PER_Q;
  $('#timerBar').style.strokeDashoffset = TIMER_CIRC*(1-ratio);
  $('#timerBar').style.stroke = state.timeLeft<=5 ? 'var(--danger)' : 'var(--orange)';
}

function pickAnswer(answerId, idx){
  clearInterval(state.timer);
  const wrap = $('#answers'); wrap.classList.add('locked');
  const btns = [...wrap.children];
  if(idx!==null){
    btns.forEach((b,k)=>{ if(k!==idx) b.classList.add('dim'); });
    btns[idx].classList.add('chosen');
  }

  state.answers.push({
    question_id: QUESTIONS[state.idx].id,
    answer_id: answerId, // null si temps écoulé
  });

  setTimeout(()=>{
    state.idx++;
    if(state.idx < QUESTIONS.length){ renderQuestion(); }
    else{ finish(); }
  }, 650);
}

/* ---------- SOUMISSION ---------- */
async function finish(){
  $('#progressFill').style.width='100%';
  try{
    const res = await fetch(SUBMIT_URL, {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
      body: JSON.stringify({ user_name: state.name, answers: state.answers })
    });
    if(!res.ok) throw new Error('submit failed');
    const data = await res.json();
    state.submissionId = data.submission_id;
    state.finalScore = data.final_score;
    showResult(data.final_score, data.verdict);
  }catch(e){
    alert("Une erreur est survenue lors de l’enregistrement. Veuillez réessayer.");
    console.error(e);
  }
}

/* ---------- RÉSULTAT ---------- */
const RING_CIRC = 2*Math.PI*76;
$('#scoreBar').style.strokeDasharray = RING_CIRC;
$('#scoreBar').style.strokeDashoffset = RING_CIRC;

function showResult(final, verdict){
  show('screen-result');
  const f = Math.round(final);
  if(verdict){
    $('#verdictBadge').textContent = verdict.label || '';
    $('#verdictBadge').style.background = verdict.color || 'var(--orange)';
    $('#verdictTitle').textContent = verdict.title || '';
    $('#verdictText').textContent = verdict.comment || '';
  }else{
    $('#verdictBadge').style.display='none';
  }

  setTimeout(()=>{
    $('#scoreBar').style.strokeDashoffset = RING_CIRC*(1-f/100);
    let cur=0; const step=Math.max(1,Math.round(f/40));
    const cnt=setInterval(()=>{ cur=Math.min(f,cur+step); $('#scoreVal').textContent=cur; if(cur>=f)clearInterval(cnt); },28);
  },200);

  if(f>=70 && window.confetti){
    setTimeout(()=> confetti({particleCount:130,spread:75,origin:{y:.4},colors:['#FFAF32','#FF9600','#36B37E','#fff']}),700);
  }
}

/* ---------- E-MAIL / CONTACT ---------- */
$('#email').addEventListener('input', ()=>{ $('#email').style.borderColor=''; $('#emailErr').style.display='none'; });
$('#sendBtn').addEventListener('click', async ()=>{
  const email = $('#email').value.trim();
  if(!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){
    $('#email').style.borderColor='var(--danger)'; $('#emailErr').style.display='block'; $('#email').focus(); return;
  }
  if(!state.submissionId){ return; }
  $('#sendBtn').disabled=true;
  try{
    const url = CONTACT_URL_TPL.replace('__ID__', state.submissionId);
    const res = await fetch(url, {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':CSRF, 'Accept':'application/json' },
      body: JSON.stringify({ email, allow_contact: $('#allowContact').checked })
    });
    if(!res.ok) throw new Error('contact failed');
    $('#successMsg').style.display='block';
  }catch(e){
    $('#sendBtn').disabled=false;
    alert("Impossible d’enregistrer votre e-mail. Veuillez réessayer.");
    console.error(e);
  }
});

$('#restartBtn').addEventListener('click', ()=>{ window.location.reload(); });
</script>
@endsection