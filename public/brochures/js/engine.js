/*
 * OrthoMaster partner pages: 3D scene.
 * three.js 0.128.0 is loaded from ./vendor/three.module.js (the colour handling below is written for that version).
 *
 * Models are loaded on demand from {modelsUrl}/{key}.json + {key}.bin
 *   .bin = Int16 quantised positions (nv*3) followed by Uint16 indices (ni)
 */
import * as THREE from './vendor/three.module.js';

const BRAND = '#FF9600'; // OrthoMaster orange: fixed, used for the "OrthoMaster" phase

export function initScene({ canvas, state, reduce, model, modelsUrl }) {
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    } catch (e) {
        return null;
    }
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.outputEncoding = THREE.sRGBEncoding;

    const root = document.documentElement;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 100);
    camera.position.set(0, 0, 15);
    scene.add(new THREE.HemisphereLight(0xe6eef5, 0x1b3044, 0.85));
    const key = new THREE.DirectionalLight(0xfff6ea, 1.15);
    key.position.set(5, 7, 8);
    scene.add(key);
    const rim = new THREE.PointLight(0x4cbcb0, 1.6, 30);
    rim.position.set(-5, 1, -4);
    scene.add(rim);

    // ---------- colours ----------
    const lin = h => new THREE.Color(h).convertSRGBToLinear();
    const BONE = lin('#ebe4d4');
    const teal = new THREE.Color(); // doctor's primary colour, read from --teal
    const om = lin(BRAND);
    const mix = new THREE.Color();
    const SHADE_OFF = [[0, 0], [-.012, -.05], [.01, .06], [-.02, -.09], [.006, .03], [.015, .08], [-.025, -.12], [0, 0], [-.008, -.03]];
    const OM_SHADES = SHADE_OFF.map(() => new THREE.Color());
    const OM_EMIS = SHADE_OFF.map(() => new THREE.Color());
    (() => {
        const base = new THREE.Color(BRAND), hsl = {};
        base.getHSL(hsl);
        SHADE_OFF.forEach(([dh, dl], i) => {
            OM_SHADES[i].setHSL((hsl.h + dh + 1) % 1, hsl.s, Math.min(.85, Math.max(.2, hsl.l + dl))).convertSRGBToLinear();
            OM_EMIS[i].copy(OM_SHADES[i]).multiplyScalar(.45);
        });
    })();
    const readPrimary = () => {
        const v = getComputedStyle(root).getPropertyValue('--teal').trim();
        if (v) teal.set(v).convertSRGBToLinear();
    };
    readPrimary();
    // follow palette / theme changes (e.g. a demo page switching data-palette)
    new MutationObserver(readPrimary).observe(root, { attributes: true, attributeFilter: ['data-palette', 'data-theme', 'style'] });
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', readPrimary);

    let omK = 0;
    const ease = t => 1 - Math.pow(1 - Math.min(Math.max(t, 0), 1), 3);

    // ---------- data loading ----------
    const cache = {};
    function load(k) {
        if (!cache[k]) {
            cache[k] = Promise.all([
                fetch(`${modelsUrl}/${k}.json`).then(r => r.json()),
                fetch(`${modelsUrl}/${k}.bin`).then(r => r.arrayBuffer()),
            ]).then(([meta, raw]) => ({ meta, raw }));
        }
        return cache[k];
    }
    function partGeo(posQ, idxAll, q, vo, nv, io, ni) {
        const pos = new Float32Array(nv * 3), s = q / 32767;
        for (let k = 0; k < nv * 3; k++) pos[k] = posQ[vo * 3 + k] * s;
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        geo.setIndex(new THREE.BufferAttribute(idxAll.slice(io, io + ni), 1));
        geo.computeVertexNormals();
        return geo;
    }

    // ---------- model builders ----------
    // Spine: BodyParts3D vertebrae + measured intervertebral discs (23 discs -> 9 groups)
    function buildSpine({ meta: M, raw }) {
        const posQ = new Int16Array(raw, 0, M.nv * 3), idxAll = new Uint16Array(raw, M.nv * 6, M.ni);
        const g0 = new THREE.Group(), segs = [], discs = [];
        const boneMat = new THREE.MeshStandardMaterial({ color: BONE, roughness: .52, metalness: .04 });
        M.parts.forEach(([vo, nv, io, ni], i) => {
            const g = new THREE.Group();
            g.add(new THREE.Mesh(partGeo(posQ, idxAll, M.q, vo, nv, io, ni), boneMat));
            g.userData = { delay: i * .055 };
            g0.add(g);
            segs.push(g);
        });
        const ND = M.discs.length, bandY = Array(9).fill(0), bandN = Array(9).fill(0);
        M.discs.forEach((dd, j) => {
            const sh = new THREE.Shape(), p = dd.pts;
            for (let i = 0; i < p.length; i += 2) i === 0 ? sh.moveTo(p[i], p[i + 1]) : sh.lineTo(p[i], p[i + 1]);
            const bev = dd.h * .28, depth = Math.max(.002, dd.h - 2 * bev);
            const geo = new THREE.ExtrudeGeometry(sh, { depth, bevelEnabled: true, bevelThickness: bev, bevelSize: bev * .35, bevelSegments: 3, curveSegments: 4 });
            geo.translate(0, 0, -depth / 2);
            geo.computeVertexNormals();
            const m = new THREE.MeshStandardMaterial({ color: teal.clone(), emissive: teal.clone(), emissiveIntensity: .12, roughness: .35, transparent: true, opacity: .94 });
            const d = new THREE.Mesh(geo, m);
            d.quaternion.setFromRotationMatrix(new THREE.Matrix4().makeBasis(new THREE.Vector3(...dd.u), new THREE.Vector3(...dd.w), new THREE.Vector3(...dd.d)));
            const grp = Math.min(8, Math.floor(j * 9 / ND));
            bandY[grp] += dd.c[1];
            bandN[grp]++;
            d.userData = { x: dd.c[0], y: dd.c[1], z: dd.c[2], grp, glow: .12, delay: j * .055 + .08 };
            g0.add(d);
            discs.push(d);
        });
        for (let g = 0; g < 9; g++) bandY[g] /= Math.max(1, bandN[g]);
        return {
            root: g0,
            look: i => bandY[i] * .35,
            update(tl, t) {
                const { active, omZone } = state;
                segs.forEach(g => {
                    const k = reduce ? 1 : ease((tl - .3 - g.userData.delay) / 1.1);
                    g.position.set(0, (1 - k) * 4, 0);
                    g.rotation.y = (1 - k) * 1.2;
                    g.scale.setScalar(.001 + k * .999);
                });
                discs.forEach((d, i) => {
                    const du = d.userData, k = reduce ? 1 : ease((tl - .5 - du.delay) / 1.1);
                    d.position.set(du.x, du.y + (1 - k) * 4, du.z);
                    let target = du.grp === active ? 1.35 : (active >= 0 && du.grp > active ? .45 : .12);
                    if (omZone) target = .55 + .25 * Math.sin(t * 2 - i * .35);
                    du.glow += (target - du.glow) * .08;
                    d.material.emissiveIntensity = du.glow;
                    d.material.color.copy(teal).lerp(OM_SHADES[i % 9], omK);
                    d.material.emissive.copy(teal).lerp(OM_EMIS[i % 9], omK);
                    const f = (.001 + k * .999) * (1 + (du.glow - .12) * .04);
                    d.scale.set(f, f, .001 + k * .999);
                });
            },
        };
    }

    // Knee / hand: one fused mesh, glow painted by height (shader band) + CT-style reveal
    function buildBand({ meta: M, raw }) {
        const geo = partGeo(new Int16Array(raw, 0, M.nv * 3), new Uint16Array(raw, M.nv * 6, M.ni), M.q, 0, M.nv, 0, M.ni);
        const U = {
            uBandY: { value: M.yMax }, uBandW: { value: .42 }, uBand: { value: 0 }, uPassed: { value: 0 }, uOm: { value: 0 },
            uTime: { value: 0 }, uReveal: { value: reduce ? 99 : M.yMin - .2 }, uTeal: { value: teal }, uOrange: { value: om },
        };
        const mat = new THREE.MeshStandardMaterial({ color: BONE.clone(), roughness: .5, metalness: .04, side: THREE.DoubleSide });
        mat.onBeforeCompile = sh => {
            Object.assign(sh.uniforms, U);
            sh.vertexShader = 'varying float vY;\n' + sh.vertexShader.replace('#include <begin_vertex>', '#include <begin_vertex>\n  vY = position.y;');
            sh.fragmentShader = 'varying float vY;\nuniform float uBandY,uBandW,uBand,uPassed,uOm,uTime,uReveal;\nuniform vec3 uTeal,uOrange;\nfloat kBand,kPassed,kWave,kEdge;\n' +
                sh.fragmentShader
                    .replace('#include <color_fragment>', `#include <color_fragment>
  if(vY > uReveal) discard;
  kEdge = smoothstep(.14, 0., uReveal - vY);
  kBand = exp(-pow((vY-uBandY)/uBandW, 2.)) * uBand;
  kPassed = smoothstep(uBandY + uBandW*.4, uBandY + uBandW*1.6, vY) * uPassed;
  kWave = .5 + .5*sin(vY*2.6 - uTime*2.);
  vec3 tealTint = mix(diffuseColor.rgb, uTeal, clamp(kBand*.92 + kPassed*.38, 0., 1.));
  vec3 omTint = mix(diffuseColor.rgb, uOrange, .45 + .35*kWave);
  diffuseColor.rgb = mix(tealTint, omTint, uOm);`)
                    .replace('#include <emissivemap_fragment>', `#include <emissivemap_fragment>
  totalEmissiveRadiance += mix(uTeal*(kBand*.55 + kPassed*.08), uOrange*(.12 + .14*kWave), uOm) + uTeal*kEdge*1.6;`);
        };
        const g0 = new THREE.Group();
        g0.add(new THREE.Mesh(geo, mat));
        const span = M.yMax - M.yMin, bandY = [...Array(9)].map((_, g) => M.yMin + span * (.07 + .86 * g / 8));
        return {
            root: g0,
            look: i => bandY[i] * .35,
            update(tl, t, hasBand) {
                U.uTime.value = t;
                if (!reduce) U.uReveal.value = M.yMin - .2 + Math.max(0, (tl - .3) / 2.4) * (span + .6);
                if (hasBand) U.uBandY.value += (bandY[state.active] - U.uBandY.value) * .08;
                U.uBand.value += ((hasBand ? 1 : 0) - U.uBand.value) * .06;
                U.uPassed.value = U.uBand.value;
                U.uOm.value = omK;
            },
        };
    }

    // Foot / lower limbs: separate bones, each in a light group (0..8)
    function buildBones({ meta: M, raw }, opt) {
        const posQ = new Int16Array(raw, 0, M.nv * 3), idxAll = new Uint16Array(raw, M.nv * 6, M.ni);
        const g0 = new THREE.Group(), inner = new THREE.Group();
        inner.scale.setScalar(opt.scale || 1);
        g0.add(inner);
        const bones = [], c1 = new THREE.Color(), c2 = new THREE.Color();
        M.bones.forEach(([vo, nv, io, ni, grp], i) => {
            const mat = new THREE.MeshStandardMaterial({ color: BONE.clone(), emissive: new THREE.Color(0), roughness: .5, metalness: .04 });
            const m = new THREE.Mesh(partGeo(posQ, idxAll, M.q, vo, nv, io, ni), mat);
            m.userData = { g: opt.rev ? 8 - grp : grp, glow: 0, tint: 0, delay: i * opt.stagger };
            inner.add(m);
            bones.push(m);
        });
        return {
            root: g0,
            look: i => (opt.look && M.bandY) ? M.bandY[opt.rev ? 8 - i : i] * opt.look : 0,
            update(tl, t) {
                const { active, omZone } = state;
                const on = !omZone && active >= 0;
                bones.forEach((m, i) => {
                    const u = m.userData, k = reduce ? 1 : ease((tl - .3 - u.delay) / 1.0);
                    m.position.set(0, (1 - k) * 3, 0);
                    m.scale.setScalar(.001 + k * .999);
                    const tT = on ? (u.g === active ? .85 : (u.g > active ? .3 : 0)) : 0;
                    const tG = on && u.g === active ? .5 : 0;
                    u.tint += (tT - u.tint) * .08;
                    u.glow += (tG - u.glow) * .08;
                    const wave = .5 + .5 * Math.sin(t * 2 - i * .45), shade = OM_SHADES[i % 9];
                    m.material.color.copy(BONE).lerp(teal, u.tint).lerp(c1.copy(BONE).lerp(shade, .45 + .35 * wave), omK);
                    m.material.emissive.copy(teal).multiplyScalar(u.glow).lerp(c2.copy(shade).multiplyScalar(.12 + .12 * wave), omK);
                });
            },
        };
    }

    // base: starting angle, s: [desktop, mobile] scale, lean: fixed tilt, px: horizontal position in the 1280px frame
    const MODELS = {
        spine: { build: buildSpine, base: 1.15, s: [1, .82], lean: [0, 0, -.06], px: .72 },
        knee: { build: buildBand, base: .55, s: [1, .85], lean: [0, 0, 0], px: .72 },
        hand: { build: buildBand, base: .55, s: [1, .85], lean: [0, 0, 0], px: .72 },
        foot: { build: d => buildBones(d, { rev: true, stagger: .07, scale: 1.12, look: 0 }), base: .9, s: [1, .85], lean: [.38, 0, -.16], px: .77 },
        legs: { build: d => buildBones(d, { rev: false, stagger: .03, scale: 1, look: .2 }), base: .9, s: [.92, .82], lean: [0, 0, 0], px: .72 },
    };

    // ---------- scene graph ----------
    const lean = new THREE.Group();
    scene.add(lean);
    const spin = new THREE.Group();
    lean.add(spin);

    const P = 320, ppos = new Float32Array(P * 3), spd = new Float32Array(P);
    for (let i = 0; i < P; i++) {
        const a = Math.random() * Math.PI * 2, r = 1.4 + Math.random() * 2.6;
        ppos[i * 3] = Math.cos(a) * r;
        ppos[i * 3 + 1] = (Math.random() - .5) * 11;
        ppos[i * 3 + 2] = Math.sin(a) * r;
        spd[i] = .15 + Math.random() * .35;
    }
    const pGeo = new THREE.BufferGeometry();
    pGeo.setAttribute('position', new THREE.BufferAttribute(ppos, 3));
    const pMat = new THREE.PointsMaterial({ color: teal.clone(), size: .045, transparent: true, opacity: .55, depthWrite: false });
    const dust = new THREE.Points(pGeo, pMat);
    scene.add(dust);

    const clock = new THREE.Clock();
    let cur = null, def = MODELS[model] || MODELS.spine, t0 = 0, requested = null;
    const disposeTree = o => o.traverse(n => {
        if (n.geometry) n.geometry.dispose();
        if (n.material) n.material.dispose();
    });

    async function setModel(k) {
        if (!MODELS[k]) return;
        requested = k;
        const data = await load(k);
        if (requested !== k) return; // a newer request won
        if (cur) {
            spin.remove(cur.root);
            disposeTree(cur.root);
        }
        def = MODELS[k];
        cur = def.build(data);
        spin.add(cur.root);
        lean.rotation.set(...def.lean);
        t0 = clock.getElapsedTime();
        resize();
    }

    let mobile = false, deskX = 2.7;
    function resize() {
        const w = innerWidth, h = innerHeight;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        mobile = w < 820;
        const frameW = Math.min(w, 1280), frameLeft = (w - frameW) / 2;
        const px = frameLeft + frameW * def.px;
        const visW = 2 * 15 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) * camera.aspect;
        deskX = ((px / w) - .5) * visW / .65;
    }
    addEventListener('resize', resize);
    resize();
    setModel(def === MODELS[model] ? model : 'spine');

    let mx = 0, my = 0, tx = 0, ty = 0;
    addEventListener('pointermove', e => {
        tx = (e.clientX / innerWidth - .5) * 2;
        ty = (e.clientY / innerHeight - .5) * 2;
    }, { passive: true });
    let lookY = 0, spinX = deskX;

    function frame() {
        const t = clock.getElapsedTime(), tl = t - t0;
        mx += (tx - mx) * .05;
        my += (ty - my) * .05;
        const maxScroll = Math.max(1, document.documentElement.scrollHeight - innerHeight);
        const sp = scrollY / maxScroll;
        omK += ((state.omZone ? 1 : 0) - omK) * .05;
        mix.copy(teal).lerp(om, omK);
        const hasBand = !state.omZone && state.active >= 0;
        if (cur) cur.update(tl, t, hasBand);

        const arr = pGeo.attributes.position.array;
        if (!reduce) {
            for (let i = 0; i < P; i++) {
                arr[i * 3 + 1] += spd[i] * .006;
                if (arr[i * 3 + 1] > 5.5) arr[i * 3 + 1] = -5.5;
            }
            pGeo.attributes.position.needsUpdate = true;
        }
        pMat.color.copy(mix);
        rim.color.copy(mix);

        spinX += ((mobile ? 0 : deskX) - spinX) * .08;
        lean.position.x = spinX;
        dust.position.x = spinX;
        dust.rotation.y = spin.rotation.y;
        spin.scale.setScalar(mobile ? def.s[1] : def.s[0]);
        spin.rotation.y = (reduce ? 0 : t * .12) + sp * Math.PI * 1.4 + mx * .45 + def.base;
        spin.rotation.x = my * .12;

        const ay = hasBand && cur ? cur.look(state.active) : 0;
        lookY += (ay - lookY) * .05;
        camera.position.y = lookY;
        camera.lookAt(spinX * .35, lookY, 0);

        renderer.render(scene, camera);
        requestAnimationFrame(frame);
    }
    frame();

    return { setModel, models: Object.keys(MODELS) };
}