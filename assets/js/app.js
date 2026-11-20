document.addEventListener('DOMContentLoaded', () => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  // Pestañas (recuerdan la última con el hash de la URL)
  const tabs = $$('.tabs button'), panes = $$('.tabpane');
  const show = n => { if (!$('#tab-' + n)) n = 'tareas';
    tabs.forEach(b => b.classList.toggle('on', b.dataset.tab === n));
    panes.forEach(p => p.hidden = p.id !== 'tab-' + n); history.replaceState(null, '', '#' + n); };
  tabs.forEach(b => b.addEventListener('click', () => show(b.dataset.tab)));
  if (tabs.length) show(location.hash.slice(1));
  // Anillo y barras animadas
  const ring = $('.ring'); if (ring) requestAnimationFrame(() => $('.r-fg', ring).style.strokeDashoffset = 100 - ring.dataset.pct);
  $$('.bar i').forEach(i => requestAnimationFrame(() => i.style.width = i.dataset.w + '%'));
  // Reloj en vivo
  const r = $('#reloj'); if (r) setInterval(() => r.textContent = new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', hour12: false }), 15000);
  // Casillas: enviar al marcar; metas: enviar al soltar el control
  $$('form[data-auto] input[type=checkbox]').forEach(c => c.addEventListener('change', () => c.form.submit()));
  $$('form[data-auto-change] input[type=range]').forEach(s => {
    s.addEventListener('input', () => s.nextElementSibling.textContent = s.value + '%');
    s.addEventListener('change', () => s.form.submit()); });
  // Confirmar antes de borrar
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
  // Vista previa del disponible mientras escribes un gasto
  const m = $('input[data-disp]'), p = $('#preview');
  if (m && p) m.addEventListener('input', () => {
    const v = parseFloat(m.value.replace(/\./g, '').replace(',', '.')) || 0, rest = parseInt(m.dataset.disp) - v;
    p.textContent = v ? 'Después de este gasto te quedarían $' + rest.toLocaleString('es-CO') + (rest < 0 ? ' (te pasarías del presupuesto)' : '') : '';
    p.classList.toggle('neg', rest < 0); });
});

// ===== Chat de NEXUS: texto + dictado por voz =====
document.addEventListener('DOMContentLoaded', () => {
  const chat = document.getElementById('chat'); if (!chat) return;
  const box = document.getElementById('msgs'), inp = document.getElementById('chat-text'), est = document.getElementById('mic-estado');
  const mic = document.getElementById('mic'), vozBtn = document.getElementById('voz'); let hablar = false;
  const add = (r, t) => { const d = document.createElement('div'); d.className = 'm ' + r; d.textContent = t; box.appendChild(d); box.scrollTop = box.scrollHeight; return d; };
  const decir = t => { if (hablar && 'speechSynthesis' in window) { const u = new SpeechSynthesisUtterance(t); u.lang = 'es-CO'; speechSynthesis.speak(u); } };
  box.scrollTop = box.scrollHeight;
  const URL_CHAT = 'dashboard.php';   // el chat responde desde dashboard.php (los formularios normales sí pasan en el hosting)
  const campos = txt => ({ accion: 'chat', mensaje: txt, csrf: chat.dataset.csrf });
  const limpio = t => t.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120);
  // Forma 1: fetch
  const porFetch = async txt => {
    const r = await fetch(URL_CHAT, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams(campos(txt)) });
    const raw = await r.text();
    try { return JSON.parse(raw); } catch (_) { throw new Error('HTTP ' + r.status + ' · ' + limpio(raw)); }
  };
  // Forma 2 (si la 1 falla): formulario normal en un marco invisible
  const porForm = txt => new Promise((ok, no) => {
    const name = 'nx' + Date.now(), ifr = document.createElement('iframe'), f = document.createElement('form');
    ifr.name = name; ifr.hidden = true; f.method = 'post'; f.action = URL_CHAT; f.target = name; f.hidden = true;
    Object.entries(campos(txt)).forEach(([k, v]) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); });
    const fin = () => { clearTimeout(timer); ifr.remove(); f.remove(); };
    const timer = setTimeout(() => { fin(); no(new Error('el servidor tardó demasiado')); }, 35000);
    ifr.addEventListener('load', () => {
      let href, t = '';
      try { href = ifr.contentWindow.location.href; t = ifr.contentDocument.body.innerText || ''; }
      catch (_) { fin(); return no(new Error('el hosting redirigió a otra página (bloqueo de seguridad)')); }
      if (href === 'about:blank') return;
      fin();
      try { ok(JSON.parse(t)); } catch (_) { no(new Error(limpio(t) || 'respuesta vacía')); }
    });
    document.body.append(ifr, f); f.submit();
  });
  const enviarForm = async txt => { try { return await porFetch(txt); } catch (e1) { try { return await porForm(txt); } catch (e2) { throw new Error(e2.message + ' / fetch: ' + e1.message); } } };
  const enviar = async txt => {
    txt = txt.trim(); if (!txt) return; add('tu', txt); inp.value = '';
    const w = add('nexus typing', '…');
    try {
      const j = await enviarForm(txt); w.remove(); if (j.error) throw new Error(j.error);
      j.msgs.forEach(m => { add('nexus', m); decir(m); });
      if (j.refrescar) setTimeout(() => (window.nexusRefrescar ? window.nexusRefrescar() : location.reload()), 1200);
    } catch (e) { w.remove(); add('nexus', 'No pude responder ahora (' + e.message + ').'); }
  };
  document.getElementById('chat-form').addEventListener('submit', e => { e.preventDefault(); enviar(inp.value); });
  document.querySelectorAll('[data-say]').forEach(b => b.addEventListener('click', () => enviar(b.dataset.say)));
  vozBtn.addEventListener('click', () => { hablar = !hablar; vozBtn.textContent = hablar ? '🔊' : '🔇'; vozBtn.setAttribute('aria-pressed', hablar); if (!hablar) speechSynthesis.cancel(); });
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { mic.hidden = true; return; }
  const rec = new SR(); rec.lang = 'es-CO'; rec.interimResults = true; rec.continuous = false; let on = false, fin = '';
  rec.onstart = () => { on = true; mic.classList.add('on'); est.textContent = 'Escuchando… habla ahora.'; };
  rec.onresult = e => { fin = [...e.results].map(r => r[0].transcript).join(''); inp.value = fin; };
  rec.onend = () => { on = false; mic.classList.remove('on'); est.textContent = ''; if (fin.trim()) { const t = fin; fin = ''; enviar(t); } };
  rec.onerror = e => { est.textContent = e.error === 'not-allowed' ? 'Permite el micrófono en el navegador para poder dictar.' : 'No te escuché bien, intenta otra vez.'; };
  mic.addEventListener('click', () => { if (on) rec.stop(); else { fin = ''; try { rec.start(); } catch (_) {} } });
});

// ===== Portada interactiva =====
document.addEventListener('DOMContentLoaded', () => {
  const demo = document.getElementById('demo'); if (!demo) return;
  const $ = s => document.querySelector(s), peso = n => '$' + Math.round(n).toLocaleString('es-CO');
  const quieto = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const D = {
    estudio: { txt: 'Tengo que estudiar cálculo 2 horas, hacer ejercicio y tengo $40.000 para hoy.', de: 40000, a: 28000, s: [
      ['estudio', '08:00', 'Estudiar cálculo', '2 horas · prioridad alta', 2], ['libre', '10:10', 'Descanso', '10 min', 1],
      ['habito', '10:20', 'Hacer ejercicio', '45 min · hábito', 1], ['trabajo', '11:15', 'Almuerzo', 'gasto estimado $12.000', 1]] },
    ahorro: { txt: 'Quiero ahorrar para un portátil. Tengo $25.000 hoy y gasté $8.000 en transporte.', de: 25000, a: 17000, s: [
      ['trabajo', '07:30', 'Transporte', 'gasto $8.000', 1], ['estudio', '08:00', 'Revisar mi presupuesto', '15 min · meta', 1],
      ['habito', '12:30', 'Almuerzo en casa', 'ahorras cerca de $9.000', 1], ['libre', '19:00', 'Apartar $5.000', 'para la meta del portátil', 1]] },
    habitos: { txt: 'Quiero leer 30 minutos, tomar agua y caminar. Hoy tengo $15.000.', de: 15000, a: 15000, s: [
      ['habito', '06:30', 'Caminar', '30 min · hábito', 1], ['estudio', '08:00', 'Leer', '30 min · hábito', 1],
      ['libre', '10:00', 'Tomar agua', 'cada 2 horas · hábito', 1], ['trabajo', '13:00', 'Almuerzo', 'gasto estimado $10.000', 1]] },
  };
  const orden = Object.keys(D), txt = $('#demo-txt'), linea = $('#demo-line'), plata = $('#demo-money'), botones = [...demo.querySelectorAll('[data-demo]')];
  let actual = 0, timers = [], auto = !quieto;
  const parar = () => { timers.forEach(clearTimeout); timers.forEach(clearInterval); timers = []; };
  const contar = (de, a) => { if (quieto || de === a) { plata.textContent = peso(a); return; } const t0 = performance.now(), dur = 900;
    const paso = t => { const k = Math.min(1, (t - t0) / dur); plata.textContent = peso(de + (a - de) * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(paso); }; requestAnimationFrame(paso); };
  const mostrar = i => {
    parar(); actual = i; const d = D[orden[i]];
    botones.forEach(b => b.classList.toggle('on', b.dataset.demo === orden[i]));
    linea.textContent = ''; plata.textContent = peso(d.de); txt.textContent = '';
    const poner = () => { d.s.forEach((x, n) => timers.push(setTimeout(() => {
      const li = document.createElement('li'); li.className = 'slot slot-' + x[0]; li.style.setProperty('--h', x[4]);
      const t = document.createElement('time'); t.textContent = x[1]; const c = document.createElement('div');
      const b = document.createElement('b'); b.textContent = x[2]; const sp = document.createElement('span'); sp.textContent = x[3];
      c.append(b, sp); li.append(t, c); linea.appendChild(li); if (n === d.s.length - 1) { contar(d.de, d.a); if (auto) timers.push(setTimeout(() => mostrar((i + 1) % orden.length), 6000)); }
    }, quieto ? 0 : n * 420))); };
    if (quieto) { txt.textContent = d.txt; poner(); return; }
    let k = 0; const w = setInterval(() => { txt.textContent = d.txt.slice(0, ++k); if (k >= d.txt.length) { clearInterval(w); timers.push(setTimeout(poner, 350)); } }, 26); timers.push(w);
  };
  botones.forEach(b => b.addEventListener('click', () => { auto = false; mostrar(orden.indexOf(b.dataset.demo)); }));
  mostrar(0);
  // Palabra que rota en el título
  const rota = $('#rota'), pal = ['tu día', 'tus gastos', 'tus metas', 'tus hábitos']; let p = 0;
  if (rota && !quieto) setInterval(() => { rota.classList.add('out'); setTimeout(() => { p = (p + 1) % pal.length; rota.textContent = pal[p]; rota.classList.remove('out'); }, 260); }, 2600);
  // La tarjeta se inclina un poco con el mouse
  if (!quieto && matchMedia('(hover:hover)').matches) {
    demo.addEventListener('mousemove', e => { const r = demo.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      demo.style.transform = 'perspective(900px) rotateY(' + (x * 7) + 'deg) rotateX(' + (-y * 7) + 'deg)'; });
    demo.addEventListener('mouseleave', () => demo.style.transform = '');
  }
  // Aparecer al hacer scroll
  const rev = [...document.querySelectorAll('.rev')];
  rev.forEach((el, i) => el.style.setProperty('--d', (i % 4) * 0.08 + 's'));
  if ('IntersectionObserver' in window) { const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .15 }); rev.forEach(el => io.observe(el)); }
  else rev.forEach(el => el.classList.add('in'));
});
