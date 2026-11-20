/* Portada: red de nodos que sigue al mouse, brillo, botones magnéticos y demo jugable de NEXUS. */
(() => {
  const $ = (s, r = document) => r.querySelector(s), land = $('.land'); if (!land) return;
  const quieto = matchMedia('(prefers-reduced-motion: reduce)').matches, tactil = !matchMedia('(hover:hover)').matches;
  const pos = { cx: -999, cy: -999, x: -999, y: -999, gx: -999, gy: -999 };
  addEventListener('mousemove', e => { pos.cx = e.clientX; pos.cy = e.clientY; land.style.setProperty('--px', (e.clientX / innerWidth - .5).toFixed(3)); land.style.setProperty('--py', (e.clientY / innerHeight - .5).toFixed(3)); });
  const prog = $('#prog'); addEventListener('scroll', () => { const h = document.documentElement; if (prog) prog.style.width = (h.scrollTop / Math.max(1, h.scrollHeight - h.clientHeight) * 100) + '%'; }, { passive: true });

  // --- Red de nodos + brillo (un solo ciclo de animación) ---
  const cv = $('#red'), gl = $('#glow');
  if (!quieto && (cv || gl)) {
    const ctx = cv && cv.getContext('2d'), dpr = Math.min(devicePixelRatio || 1, 2); let W = 0, H = 0; const N = [];
    const ajustar = () => { if (!ctx) return; W = land.clientWidth; H = land.clientHeight; cv.width = W * dpr; cv.height = H * dpr; cv.style.width = W + 'px'; cv.style.height = H + 'px'; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      const n = Math.round(Math.min(tactil ? 35 : 70, W * H / 22000)); while (N.length < n) N.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35 }); N.length = n; };
    ajustar(); if (window.ResizeObserver) new ResizeObserver(ajustar).observe(land); else addEventListener('resize', ajustar);
    const frame = () => {
      const r = land.getBoundingClientRect(); pos.x = pos.cx - r.left; pos.y = pos.cy - r.top;
      if (gl && !tactil) { pos.gx += (pos.x - pos.gx) * .12; pos.gy += (pos.y - pos.gy) * .12; gl.style.transform = 'translate3d(' + pos.gx + 'px,' + pos.gy + 'px,0)'; }
      if (ctx) { const c = getComputedStyle(document.documentElement).getPropertyValue('--cobalto').trim() || '#2447e8'; ctx.clearRect(0, 0, W, H); ctx.fillStyle = c; ctx.strokeStyle = c;
        for (let i = 0; i < N.length; i++) { const a = N[i]; a.x += a.vx; a.y += a.vy; if (a.x < 0 || a.x > W) a.vx *= -1; if (a.y < 0 || a.y > H) a.vy *= -1;
          if (a.life !== undefined) { a.vx *= .985; a.vy *= .985; if (--a.life <= 0) { N.splice(i, 1); i--; continue; } }
          const dm = Math.hypot(a.x - pos.x, a.y - pos.y);
          if (dm < 150) { a.x += (pos.x - a.x) * .012; a.y += (pos.y - a.y) * .012; ctx.globalAlpha = (1 - dm / 150) * .55; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(pos.x, pos.y); ctx.stroke(); }
          ctx.globalAlpha = .5 * (a.life !== undefined ? Math.min(1, a.life / 60) : 1); ctx.beginPath(); ctx.arc(a.x, a.y, a.life !== undefined ? 2.6 : 2, 0, 6.283); ctx.fill();
          for (let j = i + 1; j < N.length; j++) { const b = N[j], d = Math.hypot(a.x - b.x, a.y - b.y); if (d < 120) { ctx.globalAlpha = (1 - d / 120) * .25; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); } } }
        ctx.globalAlpha = 1; }
      requestAnimationFrame(frame); };
    frame();
    land.addEventListener('click', e => {   // clic en el fondo: nacen nodos nuevos que se conectan y se desvanecen
      if (e.target.closest('a,button,input,label,.play,.day') || N.length > 150) return; const r = land.getBoundingClientRect();
      for (let k = 0; k < 8; k++) { const a = Math.random() * 6.283, v = 1.4 + Math.random() * 1.6; N.push({ x: e.clientX - r.left, y: e.clientY - r.top, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: 150 + Math.random() * 90 }); } });
    if (!tactil) document.querySelectorAll('.land .btn-lg').forEach(b => { b.addEventListener('mousemove', e => { const r = b.getBoundingClientRect(); b.style.transform = 'translate(' + (e.clientX - r.left - r.width / 2) * .18 + 'px,' + (e.clientY - r.top - r.height / 2) * .28 + 'px)'; }); b.addEventListener('mouseleave', () => b.style.transform = ''); });
  }

  // --- Tarjetas: brillo que sigue al mouse + inclinación ---
  document.querySelectorAll('.feat').forEach(c => {
    c.addEventListener('mousemove', e => { const r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
      c.style.setProperty('--mx', x * 100 + '%'); c.style.setProperty('--my', y * 100 + '%');
      if (!quieto && !tactil) c.style.transform = 'perspective(700px) rotateX(' + ((.5 - y) * 10) + 'deg) rotateY(' + ((x - .5) * 10) + 'deg) translateY(-6px)'; });
    c.addEventListener('mouseleave', () => c.style.transform = ''); });

  // --- Demo jugable: NEXUS pregunta y tú respondes ---
  const msgs = $('#p-msgs'); if (!msgs) return;
  const inp = $('#p-text'), tasks = $('#p-tasks'), bar = $('#p-bar'), pct = $('#p-pct'), plata = $('#p-money');
  const S = { presu: 0, gasto: 0, tareas: [], pend: null }, peso = n => (n < 0 ? '-' : '') + '$' + Math.round(Math.abs(n)).toLocaleString('es-CO');
  // <parser>
  const dur = t => { let m; if (/media hora/.test(t)) return 30; if ((m = t.match(/(\d+(?:[.,]\d+)?)\s*(?:horas?|hrs?|h)\b/))) return Math.round(parseFloat(m[1].replace(',', '.')) * 60);
    if ((m = t.match(/(\d+)\s*(?:minutos?|mins?)\b/))) return +m[1]; if ((m = t.match(/\b(una|dos|tres|cuatro) horas?\b/))) return { una: 60, dos: 120, tres: 180, cuatro: 240 }[m[1]]; return null; };
  const monto = t => { const m = t.match(/\$\s*([\d.,]+)\s*(mil|k)?/) || t.match(/([\d.,]+)\s*(mil|k|pesos|lucas)\b/); if (!m) return null; let n = parseFloat(m[1].replace(/[.,]/g, '')); if (m[2] && /mil|k|lucas/.test(m[2]) && n < 1000) n *= 1000; return n > 0 ? n : null; };
  const pri = t => /urgente|importante|prioridad alta|\balta\b/.test(t) ? 'alta' : /\bbaja\b|sin prisa|cuando pueda/.test(t) ? 'baja' : /\bmedia\b|normal/.test(t) ? 'media' : null;
  const titulo = s => { s = s.replace(/^(hola|hey)[,!\s]*/i, '').replace(/\b(tengo que|tengo|debo|necesito|quiero|voy a|me toca)\b\s*/gi, '').replace(/(?:por|durante|de)?\s*(?:media hora|\d+(?:[.,]\d+)?\s*(?:horas?|hrs?|h|minutos?|mins?)\b|(?:una|dos|tres|cuatro)\s+horas?)/gi, '')
    .replace(/\b(es\s+)?(urgente|importante|prioridad (?:alta|media|baja))\b/gi, '').replace(/\s+/g, ' ').replace(/^[\s,.;:!¡¿?]+|[\s,.;:!¡¿?]+$/g, '').replace(/^(de|por|durante)\s+/i, ''); return s ? s[0].toUpperCase() + s.slice(1) : ''; };
  // </parser>
  const add = (r, t) => { const d = document.createElement('div'); d.className = 'm ' + r; d.textContent = t; msgs.appendChild(d); msgs.scrollTop = msgs.scrollHeight; return d; };
  const decir = t => new Promise(ok => { const w = add('nexus typing', '…'); setTimeout(() => { w.remove(); add('nexus', t); ok(); }, quieto ? 0 : 650); });
  const confeti = el => { if (quieto) return; const r = el.getBoundingClientRect(), l = land.getBoundingClientRect(), cols = ['#2447e8', '#12a37f', '#e89a22', '#c23a78', '#c8372d'];
    for (let i = 0; i < 22; i++) { const c = document.createElement('i'); c.className = 'conf'; c.style.left = (r.left - l.left + r.width / 2) + 'px'; c.style.top = (r.top - l.top + r.height / 2) + 'px'; c.style.background = cols[i % 5];
      const a = Math.random() * 6.283, d = 40 + Math.random() * 70; c.style.setProperty('--x', Math.cos(a) * d + 'px'); c.style.setProperty('--y', Math.sin(a) * d - 30 + 'px'); c.style.setProperty('--r', Math.random() * 540 + 'deg'); land.appendChild(c); setTimeout(() => c.remove(), 900); } };
  const pintar = nueva => {
    tasks.textContent = ''; if (!S.tareas.length) { const v = document.createElement('li'); v.className = 'vacio'; v.textContent = 'Tus tareas aparecerán aquí.'; tasks.appendChild(v); }
    S.tareas.forEach((x, i) => { const li = document.createElement('li'); li.className = 'pri-' + x.p + (x.ok ? ' done' : '') + (nueva && i === S.tareas.length - 1 ? ' nueva' : '');
      const lb = document.createElement('label'), cb = document.createElement('input'), sp = document.createElement('span'), d = document.createElement('div'), b = document.createElement('b'), s = document.createElement('span');
      lb.className = 'chk'; cb.type = 'checkbox'; cb.checked = x.ok; cb.addEventListener('change', () => { x.ok = cb.checked; if (x.ok) confeti(lb); pintar(); });
      b.textContent = x.t; s.textContent = x.d + ' min · prioridad ' + x.p; lb.append(cb, sp); d.append(b, s); li.append(lb, d); tasks.appendChild(li); });
    const h = S.tareas.filter(x => x.ok).length, p = S.tareas.length ? Math.round(h * 100 / S.tareas.length) : 0, rest = S.presu - S.gasto;
    bar.style.width = p + '%'; pct.textContent = p + '%'; plata.textContent = peso(rest); plata.classList.toggle('neg', rest < 0); };
  const completar = async p => {
    if (!p.dur) { S.pend = Object.assign({}, p, { espera: 'dur' }); return decir('¿Cuánto tiempo calculas para «' + p.tit + '»? (ej: 45 minutos, 2 horas)'); }
    if (!p.pri) { S.pend = Object.assign({}, p, { espera: 'pri' }); return decir('¿Qué tan importante es «' + p.tit + '»: alta, media o baja?'); }
    S.pend = null; S.tareas.push({ t: p.tit, d: p.dur, p: p.pri, ok: false }); pintar(true);
    return decir('Listo, agregué «' + p.tit + '» (' + p.dur + ' min, prioridad ' + p.pri + '). Márcala con la casilla cuando la termines 😉'); };
  const resp = async txt => {
    const t = txt.toLowerCase();
    if (S.pend) { const p = S.pend;
      if (p.espera === 'dur') { let d = dur(t); if (d == null && /^\d+$/.test(t)) { const n = +t; d = n <= 8 ? n * 60 : n; } if (d == null && /no s[eé]|normal/.test(t)) d = 30; if (d == null) return decir('Dímelo así: «45 minutos» o «2 horas».'); p.dur = d; }
      else { const q = pri(t) || (/no s[eé]|normal/.test(t) ? 'media' : null); if (!q) return decir('¿Es de prioridad alta, media o baja?'); p.pri = q; }
      return completar(p); }
    const m = monto(t);
    if (/\b(gast[eé]|pagu[eé]|compr[eé])\b/.test(t) && m) { S.gasto += m; pintar(); return decir('Registré el gasto de ' + peso(m) + '. ' + (S.presu ? 'Te quedan ' + peso(S.presu - S.gasto) + ' hoy.' : '¿Cuánta plata tienes para hoy?')); }
    if (m && /tengo|presupuesto|para hoy|disponible/.test(t)) { S.presu = m; pintar(); return decir('Perfecto, tu presupuesto de hoy queda en ' + peso(m) + '.'); }
    const p = { tit: titulo(txt), dur: dur(t), pri: pri(t) };
    if (p.tit.length < 2) return decir('Cuéntame qué tienes que hacer, por ejemplo «estudiar cálculo 2 horas».');
    return completar(p); };
  let ocupado = false;
  const enviar = async txt => { txt = txt.trim(); if (!txt || ocupado) return; ocupado = true; add('tu', txt); inp.value = ''; try { await resp(txt); } finally { ocupado = false; } };
  $('#p-form').addEventListener('submit', e => { e.preventDefault(); enviar(inp.value); });
  document.querySelectorAll('#p-chips [data-say]').forEach(b => b.addEventListener('click', () => enviar(b.dataset.say)));
  add('nexus', 'Hola 👋 Soy NEXUS. Cuéntame qué tienes que hacer hoy y te hago las preguntas que falten.'); pintar();
})();
