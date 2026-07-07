@extends('quiz.app')

@section('title', 'OrthoMaster · Outils Marketing')

@section('styles')
    .frame{max-width:980px}
    :root{--line:#FFE0B0;--soft:#FFF7EC;--muted:#8a6c3d}

    /* ---------- en-tête + onglets ---------- */
    .tools-head{margin-bottom:14px}
    .tools-page h1{font-family:'Bricolage Grotesque',sans-serif;font-size:clamp(1.7rem,5vw,2.4rem);line-height:1.1;margin:14px 0 6px}
    .tools-page h1 span{background:linear-gradient(120deg,var(--amber),var(--orange));-webkit-background-clip:text;background-clip:text;color:transparent}
    .tools-page .lede{color:var(--muted);margin-bottom:20px;max-width:560px;font-size:.95rem;line-height:1.55}

    .brandmark .logo{width:20px;height:20px;border-radius:6px;object-fit:contain;display:block;flex-shrink:0}
    .brandmark{gap:5px;color:var(--orange);font-weight:400;letter-spacing:.6px}

    .tabbar{display:flex;gap:8px;background:var(--soft);border:1px solid var(--line);padding:6px;border-radius:16px;margin-bottom:22px;flex-wrap:wrap}
    .tab{flex:1;min-width:140px;text-align:center;font-family:inherit;font-weight:600;font-size:.95rem;cursor:pointer;border:none;background:transparent;color:#A4631B;padding:12px 16px;border-radius:11px;transition:.2s}
    .tab.active{background:linear-gradient(135deg,var(--amber),var(--orange));color:#fff;box-shadow:0 8px 18px -8px rgba(255,150,0,.7)}

    .tool-panel{display:none;animation:slidedown .3s ease}
    .tool-panel.active{display:block}

    /* ---------- champs ---------- */
    .tools-page label{display:block;font-weight:600;font-size:.9rem;margin-bottom:8px}
    .tools-page textarea,
    .tools-page select,
    .tools-page input[type=text],
    .tools-page input[type=url],
    .tools-page input[type=number]{
        width:100%;font-family:inherit;font-size:1rem;padding:13px 15px;border-radius:13px;
        border:2px solid var(--line);background:#FFFBF4;outline:none;color:var(--ink);
        transition:border .15s,box-shadow .15s;
    }
    .tools-page textarea:focus,
    .tools-page select:focus,
    .tools-page input:focus{border-color:var(--orange);box-shadow:0 0 0 4px rgba(255,150,0,.16)}
    .tools-page textarea{resize:vertical;min-height:96px}

    .qr-grid{display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start}
    @media(max-width:820px){.qr-grid{grid-template-columns:1fr}}

    .card2{background:var(--card);border-radius:22px;padding:24px;box-shadow:var(--shadow);position:relative;overflow:hidden}
    .card2::before{content:"";position:absolute;inset:0 0 auto 0;height:6px;background:linear-gradient(90deg,var(--amber),var(--orange))}

    .qr-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .qr-field{margin-bottom:18px}
    .err{color:var(--danger);font-size:.82rem;margin-top:6px;display:none}
    .adv-title{font-weight:700;color:#A4631B;padding:12px 0 4px;border-top:1px dashed var(--line);margin-top:6px}

    .color-field{display:flex;align-items:center;gap:10px}
    .color-field input[type=color]{width:46px;height:46px;border:2px solid var(--line);border-radius:11px;background:#fff;cursor:pointer;padding:3px;flex-shrink:0}
    .color-field input[type=text]{flex:1}

    .switch-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0}
    .switch-row .meta{font-size:.82rem;color:var(--muted);margin-top:3px;font-weight:400}
    .switch{position:relative;width:50px;height:28px;flex-shrink:0}
    .switch input{opacity:0;width:0;height:0}
    .slider{position:absolute;inset:0;background:#E9D3AE;border-radius:99px;transition:.25s;cursor:pointer}
    .slider::before{content:"";position:absolute;width:22px;height:22px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 2px 5px rgba(0,0,0,.25)}
    .switch input:checked + .slider{background:linear-gradient(135deg,var(--amber),var(--orange))}
    .switch input:checked + .slider::before{transform:translateX(22px)}

    .btn{font-family:inherit;font-weight:600;font-size:1rem;cursor:pointer;border:none;color:#fff;background:linear-gradient(135deg,var(--amber),var(--orange));padding:13px 22px;border-radius:13px;box-shadow:0 10px 22px -8px rgba(255,150,0,.7);transition:transform .15s}
    .btn:hover{transform:translateY(-2px)}
    .btn.ghost{background:var(--soft);color:#A4631B;box-shadow:none;border:2px solid var(--line)}
    .btn:disabled{opacity:.6;cursor:progress;transform:none}

    .preview{position:sticky;top:24px;text-align:center}
    .qr-stage{background:#fff;border-radius:18px;padding:22px;display:grid;place-items:center;min-height:300px;border:2px solid var(--line)}
    #qr canvas{max-width:100%;height:auto;display:block;border-radius:6px}
    .empty{color:#C9A56B;font-size:.9rem}
    .dl-row{display:flex;gap:10px;margin-top:16px}
    .dl-row .btn{flex:1}
    .qr-note{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:11px 14px;font-size:.8rem;color:var(--muted);margin-top:16px;line-height:1.5}
    .reused-badge{display:inline-block;font-size:.72rem;font-weight:600;color:#A4631B;background:#FFE9C8;border:1px solid var(--line);border-radius:8px;padding:3px 8px;margin-left:6px}

    /* ---------- résultat short link (card droite) ---------- */
    .sl-result-stage{background:var(--soft);border:2px solid var(--line);border-radius:16px;padding:20px;min-height:200px;display:flex;flex-direction:column;justify-content:center;text-align:left}
    .sl-empty{color:#C9A56B;font-size:.9rem;text-align:center}
    .sl-result-body{display:none}
    .sl-result-body.show{display:block;animation:slidedown .3s ease}
    .sl-result-title{font-weight:700;color:#A4631B;margin-bottom:14px}
    .sl-block{margin-bottom:14px}
    .sl-block-label{font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px}
    .sl-url-text{font-weight:700;color:#A4631B;font-size:1.05rem;word-break:break-all;line-height:1.4}
    .sl-dest-text{font-size:.82rem;color:var(--muted);word-break:break-all;line-height:1.5}

    @keyframes slidedown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
@endsection

@section('content')
    <div class="tools-page">
        <div class="tools-head">
            <div class="brandmark"><img class="logo" src="{{ setting('system.web_icon') }}" alt="OrthoMaster">OrthoMaster</div>
        </div>
        <h1>Outils <span>Marketing</span></h1>
        <p class="lede">Générez des QR codes et des liens courts traçables pour vos campagnes.</p>

        {{-- ONGLETS --}}
        <div class="tabbar">
            <button class="tab active" data-tab="qr">🔳 Générateur QR Code</button>
            <button class="tab" data-tab="link">🔗 Short Link · SEO/Tracking</button>
        </div>

        {{-- ================= TOOL 1 : QR CODE ================= --}}
        <div class="tool-panel active" id="panel-qr">
            <div class="qr-grid">
                {{-- FORMULAIRE --}}
                <div class="card2">
                    <div class="qr-field">
                        <label for="content">Lien ou texte</label>
                        <textarea id="content" placeholder="https://exemple.com">https://orthomaster.fr</textarea>
                        <div class="err" id="contentErr">Veuillez saisir un lien ou un texte.</div>
                    </div>

                    {{-- Toggle tracking pour le QR code --}}
                    <div class="switch-row" style="border-top:1px dashed var(--line);margin-top:4px">
                        <div>
                            <div style="font-weight:600">Tracking des scans</div>
                            <div class="meta">Génère un short link traçable et encode celui-ci dans le QR code.</div>
                        </div>
                        <label class="switch"><input type="checkbox" id="qrTracking"><span class="slider"></span></label>
                    </div>

                    {{-- Options tracking (UTM) affichées quand le toggle est actif --}}
                    <div id="qrTrackingOptions" style="display:none">
                        <div class="qr-row">
                            <div class="qr-field">
                                <label for="qrUtmSource">Source (utm_source)</label>
                                <select id="qrUtmSource">
                                    <option value="">— aucune —</option>
                                    @foreach([
                                        'facebook'  => 'Facebook',
                                        'instagram' => 'Instagram',
                                        'linkedin'  => 'LinkedIn',
                                        'youtube'   => 'YouTube',
                                        'tiktok'    => 'TikTok',
                                        'x'         => 'X (Twitter)',
                                        'snapchat'  => 'Snapchat',
                                        'whatsapp'  => 'WhatsApp',
                                        'messenger' => 'Messenger',
                                        'pinterest' => 'Pinterest',
                                        'doctolib'  => 'Doctolib',
                                        'email'     => 'Email / Newsletter',
                                        'flyer'     => 'Flyer / Print',
                                    ] as $val => $lbl)
                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="qr-field">
                                <label for="qrUtmCampaign">Campagne (utm_campaign)</label>
                                <input type="text" id="qrUtmCampaign" placeholder="ex : rentree-2026">
                            </div>
                        </div>
                        <button class="btn" id="qrApplyTracking" style="width:100%;margin-bottom:6px">
                            Générer le lien de tracking
                        </button>
                    </div>

                    <div class="adv-title">Réglages avancés</div>
                    <div class="qr-row">
                        <div class="qr-field">
                            <label>Couleur du motif</label>
                            <div class="color-field">
                                <input type="color" id="fgColor" value="{{ $defaultForeground }}">
                                <input type="text" id="fgHex" value="{{ $defaultForeground }}" maxlength="7">
                            </div>
                        </div>
                        <div class="qr-field">
                            <label>Couleur de fond</label>
                            <div class="color-field">
                                <input type="color" id="bgColor" value="#ffffff">
                                <input type="text" id="bgHex" value="#ffffff" maxlength="7">
                            </div>
                        </div>
                    </div>

                    <div class="qr-row">
                        <div class="qr-field">
                            <label for="ecLevel">Correction d'erreur</label>
                            <select id="ecLevel">
                                <option value="L">Faible (L)</option>
                                <option value="M">Moyenne (M)</option>
                                <option value="Q">Élevée (Q)</option>
                                <option value="H" selected>Maximale (H)</option>
                            </select>
                        </div>
                        <div class="qr-field">
                            <label for="size">Taille (px)</label>
                            <input type="number" id="size" value="320" min="120" max="1200" step="20">
                        </div>
                    </div>

                    <div class="qr-field">
                        <label for="margin">Marge (modules)</label>
                        <input type="number" id="margin" value="4" min="0" max="16" step="1">
                    </div>

                    <div class="switch-row">
                        <div>
                            <div style="font-weight:600">Ajouter le logo</div>
                            <div class="meta">Place le logo OrthoMaster au centre du QR code.</div>
                        </div>
                        <label class="switch"><input type="checkbox" id="addLogo"><span class="slider"></span></label>
                    </div>
                </div>

                {{-- APERÇU --}}
                <div class="preview">
                    <div class="card2">
                        <div class="qr-stage">
                            <div id="qr"><div class="empty">Le QR code apparaîtra ici.</div></div>
                        </div>
                        <div class="dl-row">
                            <button class="btn" id="dlPng">PNG</button>
                            <button class="btn ghost" id="dlSvg">SVG</button>
                        </div>
                        <div class="qr-note" id="qrTrackNote" style="display:none">
                            🔗 QR tracké : <span id="qrTrackUrl" style="font-weight:600"></span>
                            <span class="reused-badge" id="qrReusedBadge" style="display:none">Lien existant réutilisé</span>
                        </div>
                        <div class="qr-note">💡 Astuce : avec un logo au centre, gardez la correction d'erreur sur « Maximale (H) » pour que le code reste lisible.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= TOOL 2 : SHORT LINK ================= --}}
        <div class="tool-panel" id="panel-link">
            <div class="qr-grid">
                {{-- FORMULAIRE --}}
                <div class="card2">
                    <div class="qr-field">
                        <label for="slUrl">Lien original</label>
                        <input type="url" id="slUrl" placeholder="https://orthomaster.fr/page-a-promouvoir">
                        <div class="err" id="slUrlErr">Veuillez saisir une URL valide.</div>
                    </div>

                    <div class="qr-field">
                        <label for="slTitle">Libellé interne (optionnel)</label>
                        <input type="text" id="slTitle" placeholder="ex : Campagne rentrée Facebook">
                    </div>

                    <div class="adv-title">Paramètres UTM</div>
                    <div class="qr-row">
                        <div class="qr-field">
                            <label for="slSource">Réseau / Source (utm_source)</label>
                            <select id="slSource">
                                <option value="">— aucune —</option>
                                @foreach([
                                    'facebook'  => 'Facebook',
                                    'instagram' => 'Instagram',
                                    'linkedin'  => 'LinkedIn',
                                    'youtube'   => 'YouTube',
                                    'tiktok'    => 'TikTok',
                                    'x'         => 'X (Twitter)',
                                    'snapchat'  => 'Snapchat',
                                    'whatsapp'  => 'WhatsApp',
                                    'messenger' => 'Messenger',
                                    'pinterest' => 'Pinterest',
                                    'doctolib'  => 'Doctolib',
                                    'email'     => 'Email / Newsletter',
                                    'flyer'     => 'Flyer / Print',
                                ] as $val => $lbl)
                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="qr-field">
                            <label for="slMedium">Support (utm_medium)</label>
                            <select id="slMedium">
                                <option value="social">social</option>
                                <option value="cpc">cpc (payant)</option>
                                <option value="email">email</option>
                                <option value="referral">referral</option>
                                <option value="print">print</option>
                                <option value="qrcode">qrcode</option>
                            </select>
                        </div>
                    </div>

                    <div class="qr-row">
                        <div class="qr-field">
                            <label for="slCampaign">Campagne (utm_campaign)</label>
                            <input type="text" id="slCampaign" placeholder="ex : rentree-2026">
                        </div>
                        <div class="qr-field">
                            <label for="slContent">Contenu (utm_content)</label>
                            <input type="text" id="slContent" placeholder="ex : story-1">
                        </div>
                    </div>

                    <button class="btn" id="slGenerate" style="width:100%">Générer le short link</button>
                </div>

                {{-- RÉSULTAT (card droite) --}}
                <div class="preview">
                    <div class="card2">
                        <div class="sl-result-stage">
                            <div class="sl-empty" id="slEmpty">Votre short link apparaîtra ici.</div>
                            <div class="sl-result-body" id="slResult">
                                <div class="sl-result-title">
                                    ✅ Short link prêt
                                    <span class="reused-badge" id="slReusedBadge" style="display:none">Lien existant réutilisé</span>
                                </div>
                                <div class="sl-block">
                                    <div class="sl-block-label">Lien court</div>
                                    <div class="sl-url-text" id="slResultUrl"></div>
                                </div>
                                <div class="sl-block">
                                    <div class="sl-block-label">Destination</div>
                                    <div class="sl-dest-text" id="slResultDest"></div>
                                </div>
                            </div>
                        </div>
                        <div class="dl-row">
                            <button class="btn" id="slCopy" disabled>Copier le lien</button>
                        </div>
                        <div class="qr-note">💡 Le lien court redirige vers votre URL avec les paramètres UTM, et chaque clic est enregistré pour le suivi.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        const LOGO_URL = @json($logoUrl);
        const CSRF = document.querySelector('meta[name=csrf-token]').content;
        const STORE_URL = @json(route('mkt.shortlink.store'));
        const $ = s => document.querySelector(s);

        /* ---------------- ONGLETS ---------------- */
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tool-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                $('#panel-' + tab.dataset.tab).classList.add('active');
            });
        });

        /* ---------------- QR CODE ---------------- */
        function bindColor(picker, hex) {
            $(picker).addEventListener('input', e => { $(hex).value = e.target.value; render(); });
            $(hex).addEventListener('input', e => {
                let v = e.target.value.trim();
                if (/^#?[0-9a-fA-F]{6}$/.test(v)) {
                    if (v[0] !== '#') v = '#' + v;
                    $(picker).value = v; render();
                }
            });
        }
        bindColor('#fgColor', '#fgHex');
        bindColor('#bgColor', '#bgHex');

        ['content', 'ecLevel', 'size', 'margin', 'addLogo'].forEach(id => {
            $('#' + id).addEventListener('input', render);
            $('#' + id).addEventListener('change', render);
        });

        let lastCanvas = null;
        let trackedQrUrl = null; // si tracking activé, le QR encode ce short link

        // Toggle tracking QR : affiche/masque les options UTM
        $('#qrTracking').addEventListener('change', () => {
            const on = $('#qrTracking').checked;
            $('#qrTrackingOptions').style.display = on ? 'block' : 'none';
            if (!on) {
                // désactivé → on revient au lien original
                $('#qrTrackNote').style.display = 'none';
                $('#qrReusedBadge').style.display = 'none';
                trackedQrUrl = null;
                render();
            }
        });

        // Si un champ change après génération, on invalide le lien tracké
        // (l'utilisateur doit re-cliquer pour régénérer un lien à jour)
        ['qrUtmSource', 'qrUtmCampaign', 'content'].forEach(id => {
            $('#' + id).addEventListener('input', () => {
                if (trackedQrUrl) {
                    trackedQrUrl = null;
                    $('#qrTrackNote').style.display = 'none';
                    $('#qrReusedBadge').style.display = 'none';
                    render();
                }
            });
        });

        // Bouton : génère le short link de tracking puis met à jour le QR
        $('#qrApplyTracking').addEventListener('click', async () => {
            const original = $('#content').value.trim();
            if (!original || !/^https?:\/\//i.test(original)) {
                alert('Pour tracker les scans, saisissez une URL commençant par http(s)://');
                return;
            }

            const btn = $('#qrApplyTracking');
            btn.disabled = true; btn.textContent = 'Génération…';

            try {
                const res = await fetch(STORE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        original_url: original,
                        source_type:  'qrcode',
                        utm_source:   $('#qrUtmSource').value || null,
                        utm_medium:   'qrcode',
                        utm_campaign: $('#qrUtmCampaign').value || null,
                    }),
                });
                const data = await res.json();
                if (data.ok) {
                    trackedQrUrl = data.short_url;               // le QR encode désormais ce lien
                    $('#qrTrackUrl').textContent = data.short_url;
                    $('#qrTrackNote').style.display = 'block';
                    $('#qrReusedBadge').style.display = data.reused ? 'inline-block' : 'none';
                    render();                                    // re-render du QR avec le lien tracké
                } else {
                    alert('Erreur : vérifiez le lien.');
                }
            } catch (e) {
                alert('Erreur réseau lors de la génération du lien de tracking.');
            } finally {
                btn.disabled = false; btn.textContent = 'Générer le lien de tracking';
            }
        });

        function render() {
            // Si un lien tracké existe, on l'encode ; sinon on encode le contenu saisi.
            const text = trackedQrUrl || $('#content').value.trim();
            $('#contentErr').style.display = $('#content').value.trim() ? 'none' : 'block';
            const stage = $('#qr');
            if (!$('#content').value.trim()) {
                stage.innerHTML = '<div class="empty">Le QR code apparaîtra ici.</div>';
                lastCanvas = null;
                return;
            }

            const ec = $('#ecLevel').value || 'H';
            const size = Math.max(120, Math.min(1200, parseInt($('#size').value) || 320));
            const margin = Math.max(0, Math.min(16, parseInt($('#margin').value) || 4));
            const fg = $('#fgHex').value || '{{ $defaultForeground }}';
            const bg = $('#bgHex').value || '#ffffff';

            const qr = qrcode(0, ec);
            qr.addData(text);
            qr.make();
            const count = qr.getModuleCount();
            const cell = Math.floor(size / (count + margin * 2));
            const dim = cell * (count + margin * 2);

            const canvas = document.createElement('canvas');
            canvas.width = dim; canvas.height = dim;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, dim, dim);
            ctx.fillStyle = fg;
            for (let r = 0; r < count; r++)
                for (let c = 0; c < count; c++)
                    if (qr.isDark(r, c)) ctx.fillRect((c + margin) * cell, (r + margin) * cell, cell, cell);

            const done = () => { stage.innerHTML = ''; stage.appendChild(canvas); lastCanvas = canvas; };

            if ($('#addLogo').checked) {
                const img = new Image();
                img.onload = () => {
                    const ls = dim * 0.22, lx = (dim - ls) / 2, ly = (dim - ls) / 2, pad = ls * 0.14;
                    ctx.fillStyle = '#ffffff';
                    roundRect(ctx, lx - pad, ly - pad, ls + pad * 2, ls + pad * 2, ls * 0.18);
                    ctx.fill();
                    ctx.drawImage(img, lx, ly, ls, ls);
                    done();
                };
                img.onerror = done;
                img.src = LOGO_URL;
            } else { done(); }
        }

        function roundRect(ctx, x, y, w, h, r) {
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + w, y, x + w, y + h, r);
            ctx.arcTo(x + w, y + h, x, y + h, r);
            ctx.arcTo(x, y + h, x, y, r);
            ctx.arcTo(x, y, x + w, y, r);
            ctx.closePath();
        }

        $('#dlPng').addEventListener('click', () => {
            if (!lastCanvas) return;
            const a = document.createElement('a');
            a.download = 'orthomaster-qrcode.png';
            a.href = lastCanvas.toDataURL('image/png');
            a.click();
        });

        $('#dlSvg').addEventListener('click', () => {
            const text = trackedQrUrl || $('#content').value.trim();
            if (!text) return;
            const ec = $('#ecLevel').value || 'H';
            const margin = Math.max(0, Math.min(16, parseInt($('#margin').value) || 4));
            const fg = $('#fgHex').value || '{{ $defaultForeground }}', bg = $('#bgHex').value || '#ffffff';
            const qr = qrcode(0, ec); qr.addData(text); qr.make();
            const count = qr.getModuleCount(); const total = count + margin * 2;
            let rects = '';
            for (let r = 0; r < count; r++)
                for (let c = 0; c < count; c++)
                    if (qr.isDark(r, c)) rects += `<rect x="${c+margin}" y="${r+margin}" width="1" height="1"/>`;
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges"><rect width="${total}" height="${total}" fill="${bg}"/><g fill="${fg}">${rects}</g></svg>`;
            const blob = new Blob([svg], { type: 'image/svg+xml' });
            const a = document.createElement('a');
            a.download = 'orthomaster-qrcode.svg';
            a.href = URL.createObjectURL(blob);
            a.click();
        });

        render();

        /* ---------------- SHORT LINK ---------------- */
        let slCurrentUrl = '';

        $('#slGenerate').addEventListener('click', async () => {
            const url = $('#slUrl').value.trim();
            const valid = /^https?:\/\/.+/i.test(url);
            $('#slUrlErr').style.display = valid ? 'none' : 'block';
            if (!valid) return;

            const btn = $('#slGenerate');
            btn.disabled = true; btn.textContent = 'Génération…';

            try {
                const res = await fetch(STORE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        original_url: url,
                        title:        $('#slTitle').value || null,
                        source_type:  'shortlink',
                        utm_source:   $('#slSource').value || null,
                        utm_medium:   $('#slMedium').value || null,
                        utm_campaign: $('#slCampaign').value || null,
                        utm_content:  $('#slContent').value || null,
                    }),
                });

                const data = await res.json();
                if (data.ok) {
                    slCurrentUrl = data.short_url;
                    $('#slResultUrl').textContent = data.short_url;
                    $('#slResultDest').textContent = data.destination;
                    $('#slReusedBadge').style.display = data.reused ? 'inline-block' : 'none';
                    $('#slEmpty').style.display = 'none';
                    $('#slResult').classList.add('show');
                    $('#slCopy').disabled = false;
                } else {
                    alert('Erreur : vérifiez les champs.');
                }
            } catch (e) {
                alert('Erreur réseau lors de la génération.');
            } finally {
                btn.disabled = false; btn.textContent = 'Générer le short link';
            }
        });

        $('#slCopy').addEventListener('click', () => {
            if (!slCurrentUrl) return;
            navigator.clipboard.writeText(slCurrentUrl).then(() => {
                const b = $('#slCopy'); const old = b.textContent;
                b.textContent = 'Copié ✓';
                setTimeout(() => b.textContent = old, 1500);
            });
        });
    </script>
@endsection