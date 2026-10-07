import { initScene } from './engine.js';

const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
requestAnimationFrame(() => document.body.classList.add('ready'));

// ---- Timeline state shared with the 3D scene ----
// All <li> of every .tl on the page, in page order (newest first).
// The 3D model has 9 light groups; entries are spread over them whatever their count.
const state = { active: -1, omZone: false };
let items = [];
const toGroup = i => items.length > 1 ? Math.round((1 - i / (items.length - 1)) * 8) : 8;

const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (!e.isIntersecting) return;
        items.forEach(li => li.classList.remove('on'));
        e.target.classList.add('on');
        state.active = toGroup(items.indexOf(e.target));
    });
}, { rootMargin: '-42% 0px -42% 0px' });

// (Re)collect the timeline entries: called again by the editor when entries are added/moved/removed
function bindTimeline() {
    items.forEach(li => io.unobserve(li));
    items = [...document.querySelectorAll('ol.tl li')];
    items.forEach(li => io.observe(li));
}
bindTimeline();

// First OrthoMaster block on the page (videos or join). Absent when ?inapp != WEB.
const omZone = document.querySelector('[data-om-zone]');
const onScroll = () => {
    if (scrollY < innerHeight * 0.5) {
        state.active = -1;
        items.forEach(li => li.classList.remove('on'));
    }
    state.omZone = !!omZone && omZone.getBoundingClientRect().top < innerHeight * 0.55;
};
addEventListener('scroll', onScroll, { passive: true });
onScroll();

const canvas = document.getElementById('scene');
if (canvas) {
    window.OrthoBrochure = Object.assign(initScene({
        canvas,
        state,
        reduce,
        model: document.body.dataset.model || 'spine',
        modelsUrl: document.body.dataset.modelsUrl || '/brochures/models',
    }) || {}, { refreshTimeline: bindTimeline });
}