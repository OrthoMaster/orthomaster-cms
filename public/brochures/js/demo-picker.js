/*
 * /expert_demo : choix de la couleur et du modèle 3D (aperçu uniquement, rien n'est enregistré).
 * Chargé après app.js (qui expose window.OrthoBrochure.setModel).
 */
const panel = document.getElementById('picker');

if (panel) {
    const html = document.documentElement;
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

    // Ouvrir / fermer
    const toggle = $('#pkToggle');
    const setOpen = open => { panel.dataset.open = open; toggle.setAttribute('aria-expanded', open); };
    toggle.addEventListener('click', () => setOpen(panel.dataset.open !== 'true'));
    if (innerWidth < 820) setOpen(false);

    // Couleur principale
    const swatches = $$('.sw', panel);
    swatches.forEach((b, i) => {
        b.addEventListener('click', () => {
            swatches.forEach(x => x.setAttribute('aria-checked', String(x === b)));
            html.dataset.palette = b.dataset.palette;
            $('#palName').textContent = b.dataset.label;
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

    // Modèle 3D (+ mention BodyParts3D uniquement pour la colonne vertébrale)
    const select = $('#edModel');
    const credit = $('[data-model-credit]');
    select.addEventListener('change', () => {
        window.OrthoBrochure?.setModel?.(select.value);
        if (credit) credit.hidden = credit.dataset.modelCredit !== select.value;
    });
}