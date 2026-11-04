/*
 * Éditeur /demo/{slug} : édition en place, enregistrement automatique (AJAX JSON -> brouillon en base).
 * Chargé après app.js (qui expose window.OrthoBrochure.setModel / refreshTimeline).
 */
const bar = document.getElementById('edBar');
const panel = document.getElementById('picker');
const html = document.documentElement;
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

if (bar && panel) init();

function init() {
    const saveUrl = bar.dataset.saveUrl;
    const avatarUrl = bar.dataset.avatarUrl;
    const domainUrl = bar.dataset.domainUrl;
    const publishUrl = bar.dataset.publishUrl || null;
    const csrf = $('meta[name="csrf-token"]')?.content || '';
    const AUTOSAVE_DELAY = 1500;
    const MAX_PLACES = 4, MAX_PILLS = 12;

    const jsonHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    };
    const httpError = (res, json, fallback) =>
        json?.errors ? Object.values(json.errors)[0][0]
        : json?.message && res.status !== 500 ? json.message
        : res.status === 419 ? 'Session expirée : rechargez la page.'
        : res.status === 403 ? 'Lien expiré ou non valide.'
        : fallback;

    // ---------- contenteditable : texte brut, une seule ligne ----------
    const probe = document.createElement('div');
    probe.contentEditable = 'plaintext-only';
    const EDITABLE = probe.contentEditable === 'plaintext-only' ? 'plaintext-only' : 'true';
    const prepare = root => $$('[data-placeholder]', root).forEach(el => { el.contentEditable = EDITABLE; });

    const isEditable = el => el instanceof HTMLElement && el.isContentEditable;
    const tidy = el => { if (el.textContent.trim() === '') el.textContent = ''; }; // garde :empty pour le placeholder

    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveNow(); return; }
        if (e.key === 'Enter' && isEditable(e.target)) { e.preventDefault(); e.target.blur(); }
    });
    document.addEventListener('paste', e => {
        if (!isEditable(e.target)) return;
        e.preventDefault();
        const text = (e.clipboardData?.getData('text/plain') || '').replace(/\s+/g, ' ');
        document.execCommand('insertText', false, text);
    });
    document.addEventListener('input', e => {
        if (!isEditable(e.target)) return;
        if (e.target.dataset.max) limitLength(e.target);
        changed();
    });

    // ---------- longueur maximale (présentation : même limite que l'application) ----------
    const ledeCount = $('#edLedeCount');
    function limitLength(el) {
        const max = Number(el.dataset.max);
        if (max && el.textContent.length > max) {
            el.textContent = el.textContent.slice(0, max);
            // curseur en fin de texte après la coupe
            const range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            const sel = getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
        if (el.dataset.field === 'lede' && ledeCount) {
            ledeCount.textContent = `${el.textContent.trim().length} / ${max}`;
        }
    }
    const ledeEl = $('[data-field="lede"]');
    if (ledeEl) limitLength(ledeEl);

    // ---------- titre : liste fermée (Madame, Monsieur, Docteur, Professeur) ----------
    const honorificSelect = $('select[data-field="honorific"]');
    honorificSelect?.addEventListener('change', () => {
        honorificSelect.closest('.ed-dr')?.classList.toggle('is-empty', honorificSelect.value === '');
        changed();
    });
    honorificSelect?.closest('.ed-dr')?.classList.toggle('is-empty', honorificSelect.value === '');
    document.addEventListener('focusout', e => { if (isEditable(e.target)) tidy(e.target); });

    // ---------- état de l'enregistrement ----------
    const status = $('#edStatus');
    const statusTxt = $('.ed-save-txt', status);
    function setStatus(state, text) {
        status.dataset.state = state; // idle | pending | saving | saved | error
        statusTxt.textContent = text;
    }

    // ---------- état de la publication ----------
    const pubState = $('#edPubState');
    const pubTxt = pubState ? $('.ed-pub-txt', pubState) : null;
    const publishBtn = $('#edPublish');
    function setPub(state, label) {
        if (pubState) {
            pubState.dataset.state = state; // new | pending | published
            pubTxt.textContent = label;
        }
        if (publishBtn) publishBtn.disabled = state === 'published';
    }

    // ---------- modes Édition / Aperçu ----------
    const modeBtns = $$('.ed-seg-btn', bar);
    function refreshTimeline() { window.OrthoBrochure?.refreshTimeline?.(); }
    function setMode(mode) {
        const edit = mode === 'edit';
        document.body.classList.toggle('ed-mode', edit);
        document.body.classList.toggle('ed-preview', !edit);
        modeBtns.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.mode === mode)));
        $$('[data-placeholder]').forEach(el => { el.contentEditable = edit ? EDITABLE : 'false'; });
        if (honorificSelect) honorificSelect.disabled = !edit;
        if (!edit && document.activeElement && isEditable(document.activeElement)) document.activeElement.blur();
        refreshTimeline();
    }
    modeBtns.forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));
    setMode('edit');

    // ---------- enregistrement automatique ----------
    let dirty = false, saving = false, again = false, timer = null;

    function changed() {
        dirty = true;
        setStatus('pending', 'Modifications en attente…');
        clearTimeout(timer);
        timer = setTimeout(saveNow, AUTOSAVE_DELAY);
    }

    async function saveNow() {
        clearTimeout(timer);
        if (saving) { again = true; return false; }
        if (!dirty) return true;
        saving = true;
        dirty = false;
        setStatus('saving', 'Enregistrement…');
        let ok = false;
        try {
            const res = await post(collect());
            if (res.status === 419) { dirty = true; setStatus('error', 'Session expirée : rechargez la page.'); return false; }
            if (res.status === 403) { dirty = true; setStatus('error', 'Lien expiré ou non valide.'); return false; }
            if (res.status === 429) { dirty = true; setStatus('error', 'Trop d’enregistrements, nouvel essai dans 10 s…'); timer = setTimeout(saveNow, 10000); return false; }
            if (!res.ok) throw new Error(String(res.status));
            const json = await res.json();
            ok = true;
            setStatus('saved', `Enregistré à ${json.saved_at}`);
            if (!json.published) setPub('pending', 'Modifications non publiées');
            return true;
        } catch (err) {
            dirty = true;
            setStatus('error', 'Échec de l’enregistrement, nouvel essai dans 5 s…');
            timer = setTimeout(saveNow, 5000);
            return false;
        } finally {
            saving = false;
            if (again) { again = false; if (dirty) saveNow(); }
            else if (ok && dirty) changed(); // modifié pendant l'envoi
        }
    }

    function post(data, keepalive = false) {
        return fetch(saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive,
            headers: jsonHeaders,
            body: JSON.stringify(data),
        });
    }

    // Quitter la page : envoyer ce qui reste, et prévenir si un envoi est en cours
    addEventListener('pagehide', () => { if (dirty) post(collect(), true); });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden' && dirty) saveNow(); });
    addEventListener('beforeunload', e => { if (saving) { e.preventDefault(); e.returnValue = ''; } });

    // ---------- panneau : ouvrir / fermer ----------
    const toggle = $('#pkToggle');
    const setOpen = open => { panel.dataset.open = open; toggle.setAttribute('aria-expanded', open); };
    toggle.addEventListener('click', () => setOpen(panel.dataset.open !== 'true'));
    if (innerWidth < 820) setOpen(false);

    // ---------- couleur ----------
    const swatches = $$('.sw', panel);
    swatches.forEach((b, i) => {
        b.addEventListener('click', () => {
            swatches.forEach(x => x.setAttribute('aria-checked', String(x === b)));
            html.dataset.palette = b.dataset.palette;
            $('#palName').textContent = b.dataset.label;
            changed();
        });
        b.addEventListener('keydown', e => {
            const d = ['ArrowRight', 'ArrowDown'].includes(e.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(e.key) ? -1 : 0;
            if (!d) return;
            e.preventDefault();
            const n = swatches[(i + d + swatches.length) % swatches.length];
            n.focus();
            n.click();
        });
    });

    // ---------- modèle 3D ----------
    const modelSelect = $('#edModel');
    const credit = $('[data-model-credit]');
    modelSelect.addEventListener('change', () => {
        window.OrthoBrochure?.setModel?.(modelSelect.value);
        if (credit) credit.hidden = credit.dataset.modelCredit !== modelSelect.value;
        changed();
    });

    // ---------- liens : le bouton n'apparaît que si l'adresse est valide ----------
    $$('input[data-link]', panel).forEach(input => {
        const btn = $(`[data-link-btn="${input.dataset.link}"]`);
        input.addEventListener('input', () => {
            const v = input.value.trim();
            const ok = /^https?:\/\/[^\s.]+\.\S+$/i.test(v);
            if (btn) {
                btn.href = ok ? v : '#';
                btn.hidden = !ok;
            }
            input.setCustomValidity(v === '' || ok ? '' : 'Adresse commençant par https://');
            changed();
        });
    });

    // ---------- listes : ajouter, déplacer, supprimer ----------
    const tplEntry = $('#tplEntry'), tplPill = $('#tplPill'), tplPlace = $('#tplPlace');
    const clone = tpl => {
        const node = tpl.content.firstElementChild.cloneNode(true);
        prepare(node);
        return node;
    };

    document.addEventListener('click', e => {
        if (!document.body.classList.contains('ed-mode')) return;
        const add = e.target.closest('[data-add]');
        if (add) {
            const node = clone(tplEntry);
            $(`ol[data-list="${add.dataset.add}"]`).prepend(node); // de la plus récente à la plus ancienne
            refreshTimeline();
            $('[contenteditable]', node).focus();
            changed();
            return;
        }
        if (e.target.closest('[data-add-place]')) {
            const ul = $('ul[data-list="places"]');
            if (ul.children.length >= MAX_PLACES) return;
            const node = clone(tplPlace);
            ul.append(node);
            updatePlacesButton();
            $('[contenteditable]', node).focus();
            changed();
            return;
        }
        const act = e.target.closest('[data-act]');
        const item = act?.closest('[data-entry], [data-pill], [data-place]');
        if (!item) return;
        if (act.dataset.act === 'del') {
            const filled = item.matches('[data-entry], [data-place]') && item.textContent.trim() !== '';
            if (filled && !confirm(item.matches('[data-place]') ? 'Supprimer ce lieu ?' : 'Supprimer cette entrée ?')) return;
            if (item.matches('[data-pill]')) setChecked(item.dataset.id, false);
            item.remove();
            updatePlacesButton();
        } else if (act.dataset.act === 'up' && item.previousElementSibling) {
            item.parentNode.insertBefore(item, item.previousElementSibling);
            act.focus();
        } else if (act.dataset.act === 'down' && item.nextElementSibling) {
            item.parentNode.insertBefore(item.nextElementSibling, item);
            act.focus();
        } else {
            return;
        }
        if (item.matches('[data-entry]')) refreshTimeline();
        changed();
    });

    // ---------- lieux d'exercice ----------
    const addPlaceBtn = $('[data-add-place]');
    function updatePlacesButton() {
        if (addPlaceBtn) addPlaceBtn.hidden = $('ul[data-list="places"]').children.length >= MAX_PLACES;
    }
    updatePlacesButton();

    // ---------- domaines d'expertise : choix dans la liste, ou ajout d'un nouveau domaine ----------
    const pillsUl = $('ul[data-list="expertise"]');
    const pop = $('#edExpertise'), popBtn = $('#edExpertiseBtn'), search = $('#edExpertiseSearch');
    const optList = $('.ed-pop-list', pop), newBtn = $('#edExpertiseNew');
    const count = $('#edExpertiseCount');
    const boxes = () => $$('input[type="checkbox"]', optList);
    const norm = v => v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

    function setChecked(id, on) {
        const b = boxes().find(x => x.value === String(id));
        if (b) b.checked = on;
        refreshPop();
    }
    function refreshPop() {
        const all = boxes(), n = all.filter(b => b.checked).length;
        count.textContent = `${n} / ${MAX_PILLS} sélectionné${n > 1 ? 's' : ''}`;
        all.forEach(b => { b.disabled = !b.checked && n >= MAX_PILLS; });
        if (newBtn) newBtn.disabled = n >= MAX_PILLS;
    }
    function filter(q) {
        const nq = norm(q);
        let exact = false;
        boxes().forEach(b => {
            const l = norm(b.dataset.label);
            b.closest('.ed-opt').hidden = nq !== '' && !l.includes(nq);
            if (l === nq) exact = true;
        });
        if (!newBtn) return;
        // proposer l'ajout seulement si le texte ne correspond exactement à aucun domaine
        const label = q.trim().replace(/\s+/g, ' ');
        newBtn.hidden = label.length < 2 || exact;
        newBtn.dataset.label = label;
        newBtn.textContent = `+ Ajouter « ${label} »`;
    }
    function openPop(open) {
        pop.hidden = !open;
        popBtn.setAttribute('aria-expanded', String(open));
        if (open) { search.value = ''; filter(''); search.focus(); }
    }
    function togglePill(b) {
        const pill = $(`[data-pill][data-id="${b.value}"]`, pillsUl);
        if (b.checked && !pill) {
            const node = tplPill.content.firstElementChild.cloneNode(true);
            node.dataset.id = b.value;
            $('span', node).textContent = b.dataset.label;
            pillsUl.insertBefore(node, $('.ed-add-li', pillsUl));
        } else if (!b.checked && pill) {
            pill.remove();
        }
    }

    async function addDomain() {
        if (!newBtn || !domainUrl) return;
        const label = newBtn.dataset.label;
        if (!label || newBtn.hidden || newBtn.disabled) return;
        newBtn.disabled = true;
        try {
            const res = await fetch(domainUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: jsonHeaders,
                body: JSON.stringify({ label }),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(httpError(res, json, 'Impossible d’ajouter ce domaine.'));

            // domaine nouveau (ou déjà existant) : l'ajouter à la liste s'il n'y est pas, puis le cocher
            let b = boxes().find(x => x.value === String(json.id));
            if (!b) {
                const opt = document.createElement('label');
                opt.className = 'ed-opt';
                b = document.createElement('input');
                b.type = 'checkbox';
                b.value = String(json.id);
                b.dataset.label = json.label;
                const span = document.createElement('span');
                span.textContent = json.label;
                opt.append(b, span);
                optList.append(opt);
            }
            if (!b.checked) {
                b.checked = true;
                togglePill(b);
                changed();
            }
            search.value = '';
            filter('');
            search.focus();
        } catch (err) {
            setStatus('error', err.message);
        } finally {
            refreshPop();
        }
    }

    popBtn.addEventListener('click', e => { e.stopPropagation(); openPop(pop.hidden); });
    $('#edExpertiseClose').addEventListener('click', () => { openPop(false); popBtn.focus(); });
    search.addEventListener('input', () => filter(search.value));
    search.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addDomain(); }
    });
    newBtn?.addEventListener('click', addDomain);
    document.addEventListener('click', e => { if (!pop.hidden && !pop.contains(e.target) && e.target !== popBtn) openPop(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !pop.hidden) { openPop(false); popBtn.focus(); } });
    optList.addEventListener('change', e => {
        const b = e.target;
        if (!(b instanceof HTMLInputElement) || b.type !== 'checkbox') return;
        togglePill(b);
        refreshPop();
        changed();
    });
    refreshPop();

    // ---------- avatar : upload direct, l'ancien fichier est supprimé côté serveur ----------
    const avatarBox = $('#edAvatar'), avatarInput = $('#edAvatarInput');
    if (avatarBox && avatarInput) {
        const avatarImg = $('img', avatarBox), avatarSil = $('.silhouette', avatarBox);
        avatarInput.addEventListener('change', async () => {
            const file = avatarInput.files[0];
            avatarInput.value = '';
            if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { setStatus('error', 'Formats acceptés : JPG, PNG ou WebP.'); return; }
            if (file.size > 5 * 1024 * 1024) { setStatus('error', 'L’image ne doit pas dépasser 5 Mo.'); return; }

            const previous = { src: avatarImg.getAttribute('src'), hidden: avatarImg.hidden };
            const preview = URL.createObjectURL(file);
            avatarImg.src = preview; avatarImg.hidden = false; avatarSil.hidden = true;
            avatarBox.classList.add('is-uploading');
            setStatus('saving', 'Envoi de la photo…');

            const form = new FormData();
            form.append('avatar', file);
            try {
                const res = await fetch(avatarUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(httpError(res, json, 'Échec de l’envoi de la photo.'));
                if (json.url) avatarImg.src = json.url;
                setStatus('saved', 'Photo enregistrée');
            } catch (err) {
                if (previous.src) avatarImg.src = previous.src;
                avatarImg.hidden = previous.hidden; avatarSil.hidden = !previous.hidden;
                setStatus('error', err.message || 'Échec de l’envoi de la photo.');
            } finally {
                avatarBox.classList.remove('is-uploading');
                URL.revokeObjectURL(preview);
            }
        });
    }

    // ---------- collecte des données ----------
    const text = el => (el?.textContent || '').trim();
    const entries = key => $$(`ol[data-list="${key}"] > li[data-entry]`).map(li => ({
        year: text($('[data-k="year"]', li)),
        title: text($('[data-k="title"]', li)),
        place: text($('[data-k="place"]', li)),
    }));
    function collect() {
        const data = {
            palette: html.dataset.palette,
            model: modelSelect.value,
            honorific: honorificSelect ? honorificSelect.value : '',
            credentials: text($('[data-field="credentials"]')),
            lede: text($('[data-field="lede"]')),
            expertise: $$('ul[data-list="expertise"] [data-pill]').map(li => Number(li.dataset.id)).filter(Boolean),
            places: $$('ul[data-list="places"] > li[data-place]').map(li => ({
                name: text($('[data-k="name"]', li)),
                address: text($('[data-k="address"]', li)),
                city: text($('[data-k="city"]', li)),
            })),
            titles: {},
        };
        $$('input[data-link]', panel).forEach(i => { data[i.dataset.link] = i.value.trim(); });
        $$('[data-title]').forEach(h => { data.titles[h.dataset.title] = text(h); });
        $$('ol[data-list]').forEach(ol => { data[ol.dataset.list] = entries(ol.dataset.list); });
        return data;
    }

    // ---------- publication (admin) ----------
    publishBtn?.addEventListener('click', async () => {
        if (!publishUrl) {
            setStatus('error', 'Publication réservée aux administrateurs.');
            return;
        }
        publishBtn.disabled = true;
        setStatus('saving', 'Publication…');
        // publier la dernière version : attendre l'enregistrement en cours puis enregistrer le reste
        while (saving) await new Promise(r => setTimeout(r, 150));
        if (dirty && !(await saveNow())) { publishBtn.disabled = false; return; }
        try {
            const res = await fetch(publishUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(httpError(res, json, 'Échec de la publication.'));
            setPub('published', `Publié le ${json.published_at}`);
            const publicLink = $('#edPublic');
            if (publicLink) publicLink.hidden = false;
            setStatus('saved', 'Page publiée');
        } catch (err) {
            setStatus('error', err.message);
            publishBtn.disabled = false;
        }
    });

    // ---------- administration ----------
    $('#edShare')?.addEventListener('click', async e => {
        const btn = e.currentTarget, url = btn.dataset.url;
        try {
            await navigator.clipboard.writeText(url);
            btn.textContent = 'Lien copié ✓';
            setTimeout(() => { btn.textContent = 'Copier le lien pour le médecin'; }, 2500);
        } catch {
            prompt('Lien à envoyer au médecin :', url);
        }
    });
}