@extends('quiz.app')

@section('title', 'OrthoMaster GeneQrCode')

@section('styles')
    /* cette page a besoin de plus de largeur que le cadre mobile par défaut */
    .frame{max-width:980px}

    :root{--line:#FFE0B0;--soft:#FFF7EC;--muted:#8a6c3d}

    .qr-head{margin-bottom:6px}
    .qr-page h1{font-family:'Bricolage Grotesque',sans-serif;font-size:clamp(1.7rem,5vw,2.4rem);line-height:1.1;margin:14px
    0 6px}
    .qr-page h1
    span{background:linear-gradient(120deg,var(--amber),var(--orange));-webkit-background-clip:text;background-clip:text;color:transparent}
    .qr-page .lede{color:var(--muted);margin-bottom:24px;max-width:560px;font-size:.95rem;line-height:1.55}

    .brandmark .logo{width:20px;height:20px;border-radius:6px;object-fit:contain;display:block;flex-shrink:0}
    .brandmark{gap:5px;color:var(--orange);font-weight:400;letter-spacing:.6px}

    .qr-grid{display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start}
    @media(max-width:820px){.qr-grid{grid-template-columns:1fr}}

    .qr-card{background:var(--card);border-radius:22px;padding:24px;box-shadow:var(--shadow);position:relative;overflow:hidden}
    .qr-card::before{content:"";position:absolute;inset:0 0 auto
    0;height:6px;background:linear-gradient(90deg,var(--amber),var(--orange))}

    .qr-page label{display:block;font-weight:600;font-size:.9rem;margin-bottom:8px}
    .qr-page textarea,
    .qr-page select,
    .qr-page input[type=text],
    .qr-page input[type=number]{
    width:100%;font-family:inherit;font-size:1rem;padding:13px 15px;border-radius:13px;
    border:2px solid var(--line);background:#FFFBF4;outline:none;color:var(--ink);
    transition:border .15s,box-shadow .15s;
    }
    .qr-page textarea:focus,
    .qr-page select:focus,
    .qr-page input:focus{border-color:var(--orange);box-shadow:0 0 0 4px rgba(255,150,0,.16)}
    .qr-page textarea{resize:vertical;min-height:96px}

    .qr-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .qr-field{margin-bottom:18px}
    .err{color:var(--danger);font-size:.82rem;margin-top:6px;display:none}

    .adv-toggle{display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;font-weight:600;color:#A4631B;padding:12px
    0;border-top:1px dashed var(--line);margin-top:6px}
    .adv-toggle .arrow{transition:transform .2s}
    .adv-toggle.open .arrow{transform:rotate(180deg)}
    .adv{display:none;padding-top:6px}
    .adv.open{display:block;animation:slidedown .3s ease}
    @keyframes slidedown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}

    .color-field{display:flex;align-items:center;gap:10px}
    .color-field input[type=color]{width:46px;height:46px;border:2px solid
    var(--line);border-radius:11px;background:#fff;cursor:pointer;padding:3px;flex-shrink:0}
    .color-field input[type=text]{flex:1}

    .switch-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:6px 0}
    .switch-row .meta{font-size:.82rem;color:var(--muted);margin-top:3px;font-weight:400}
    .switch{position:relative;width:50px;height:28px;flex-shrink:0}
    .switch input{opacity:0;width:0;height:0}
    .slider{position:absolute;inset:0;background:#E9D3AE;border-radius:99px;transition:.25s;cursor:pointer}
    .slider::before{content:"";position:absolute;width:22px;height:22px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0
    2px 5px rgba(0,0,0,.25)}
    .switch input:checked + .slider{background:linear-gradient(135deg,var(--amber),var(--orange))}
    .switch input:checked + .slider::before{transform:translateX(22px)}

    .btn{font-family:inherit;font-weight:600;font-size:1rem;cursor:pointer;border:none;color:#fff;background:linear-gradient(135deg,var(--amber),var(--orange));padding:13px
    22px;border-radius:13px;box-shadow:0 10px 22px -8px rgba(255,150,0,.7);transition:transform .15s}
    .btn:hover{transform:translateY(-2px)}
    .btn.ghost{background:var(--soft);color:#A4631B;box-shadow:none;border:2px solid var(--line)}

    .preview{position:sticky;top:24px;text-align:center}
    .qr-stage{background:#fff;border-radius:18px;padding:22px;display:grid;place-items:center;min-height:300px;border:2px
    solid var(--line)}
    #qr canvas{max-width:100%;height:auto;display:block;border-radius:6px}
    .empty{color:#C9A56B;font-size:.9rem}
    .dl-row{display:flex;gap:10px;margin-top:16px}
    .dl-row .btn{flex:1}
    .qr-note{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:11px
    14px;font-size:.8rem;color:var(--muted);margin-top:16px;line-height:1.5}
@endsection

@section('content')
    <div class="qr-page">
        <div class="qr-head">
            <div class="brandmark"><img class="logo" src="{{ setting('system.web_icon') }}" alt="OrthoMaster">OrthoMaster
            </div>
        </div>
        <h1>Générateur de <span>QR Code</span> sécurisé</h1>
        <p class="lede">Tout est généré dans votre navigateur. Vos liens ne quittent jamais votre appareil — aucune donnée
            n’est envoyée ni conservée.</p>

        <div class="qr-grid">
            {{-- FORMULAIRE --}}
            <div class="qr-card">
                <div class="qr-field">
                    <label for="content">Lien ou texte</label>
                    <textarea id="content" placeholder="https://exemple.com">https://orthomaster.fr</textarea>
                    <div class="err" id="contentErr">Veuillez saisir un lien ou un texte.</div>
                </div>

                <div class="adv-toggle" id="advToggle">
                    <span>Réglages avancés</span><span class="arrow">▾</span>
                </div>
                <div class="adv" id="advPanel">
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
                            <label for="ecLevel">Correction d’erreur</label>
                            <select id="ecLevel">
                                <option value="L">Faible (L)</option>
                                <option value="M">Moyenne (M)</option>
                                <option value="Q">Élevée (Q)</option>
                                <option value="H" selected>Maximale (H)</option>
                            </select>
                        </div>
                        <div class="qr-field">
                            <label for="size">Taille (px)</label>
                            <input type="number" id="size" value="320" min="120" max="1200"
                                step="20">
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
            </div>

            {{-- APERÇU --}}
            <div class="preview">
                <div class="qr-card">
                    <div class="qr-stage">
                        <div id="qr">
                            <div class="empty">Le QR code apparaîtra ici.</div>
                        </div>
                    </div>
                    <div class="dl-row">
                        <button class="btn" id="dlPng">PNG</button>
                        <button class="btn ghost" id="dlSvg">SVG</button>
                    </div>
                    <div class="qr-note">💡 Astuce : avec un logo au centre, gardez la correction d’erreur sur « Maximale
                        (H) » pour que le code reste lisible.</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        // URL du logo (défini côté serveur, inséré ici)
        const LOGO_URL = @json($logoUrl);

        const $ = s => document.querySelector(s);

        $('#advToggle').addEventListener('click', () => {
            $('#advToggle').classList.toggle('open');
            $('#advPanel').classList.toggle('open');
        });

        function bindColor(picker, hex) {
            $(picker).addEventListener('input', e => {
                $(hex).value = e.target.value;
                render();
            });
            $(hex).addEventListener('input', e => {
                let v = e.target.value.trim();
                if (/^#?[0-9a-fA-F]{6}$/.test(v)) {
                    if (v[0] !== '#') v = '#' + v;
                    $(picker).value = v;
                    render();
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

        function render() {
            const text = $('#content').value.trim();
            $('#contentErr').style.display = text ? 'none' : 'block';
            const stage = $('#qr');
            if (!text) {
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
            canvas.width = dim;
            canvas.height = dim;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, dim, dim);
            ctx.fillStyle = fg;
            for (let r = 0; r < count; r++) {
                for (let c = 0; c < count; c++) {
                    if (qr.isDark(r, c)) ctx.fillRect((c + margin) * cell, (r + margin) * cell, cell, cell);
                }
            }

            const done = () => {
                stage.innerHTML = '';
                stage.appendChild(canvas);
                lastCanvas = canvas;
            };

            if ($('#addLogo').checked) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    const ls = dim * 0.22,
                        lx = (dim - ls) / 2,
                        ly = (dim - ls) / 2,
                        pad = ls * 0.14;
                    ctx.fillStyle = '#ffffff';
                    roundRect(ctx, lx - pad, ly - pad, ls + pad * 2, ls + pad * 2, ls * 0.18);
                    ctx.fill();
                    ctx.drawImage(img, lx, ly, ls, ls);
                    done();
                };
                img.onerror = done;
                img.src = LOGO_URL;
            } else {
                done();
            }
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
            const text = $('#content').value.trim();
            if (!text) return;
            const ec = $('#ecLevel').value || 'H';
            const margin = Math.max(0, Math.min(16, parseInt($('#margin').value) || 4));
            const fg = $('#fgHex').value || '{{ $defaultForeground }}',
                bg = $('#bgHex').value || '#ffffff';
            const qr = qrcode(0, ec);
            qr.addData(text);
            qr.make();
            const count = qr.getModuleCount();
            const total = count + margin * 2;
            let rects = '';
            for (let r = 0; r < count; r++)
                for (let c = 0; c < count; c++)
                    if (qr.isDark(r, c))
                        rects += `<rect x="${c+margin}" y="${r+margin}" width="1" height="1"/>`;
            const svg =
                `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges"><rect width="${total}" height="${total}" fill="${bg}"/><g fill="${fg}">${rects}</g></svg>`;
            const blob = new Blob([svg], {
                type: 'image/svg+xml'
            });
            const a = document.createElement('a');
            a.download = 'orthomaster-qrcode.svg';
            a.href = URL.createObjectURL(blob);
            a.click();
        });

        render();
    </script>
@endsection
