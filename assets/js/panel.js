/* Panel interactivo de NEXUS: acciones sin recargar, confirmaciones y avisos propios, confeti,
   temporizador de enfoque por tarea y atajos de teclado. Si algo falla, el panel vuelve a funcionar como antes. */
(() => {
  const main = document.querySelector('.dash-wrap'); if (!main) return;
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const quieto = matchMedia('(prefers-reduced-motion: reduce)').matches, titulo0 = document.title;

  // ---------- Avisos (toasts) y confirmación propia ----------
  const caja = document.createElement('div'); caja.id = 'toasts'; caja.setAttribute('aria-live', 'polite'); document.body.appendChild(caja);
  const toast = (msg, tipo) => { const t = document.createElement('div'); t.className = 'toast ' + (tipo || 'ok'); t.textContent = msg; caja.appendChild(t); setTimeout(() => { t.classList.add('sale'); setTimeout(() => t.remove(), 300); }, 3200); };
  const confirmar = msg => new Promise(ok => {
    const o = document.createElement('div'), b = document.createElement('div'), p = document.createElement('p'), no = document.createElement('button'), si = document.createElement('button');
    o.className = 'modal'; b.setAttribute('role', 'alertdialog'); p.textContent = msg; no.type = si.type = 'button'; no.className = 'btn btn-ghost'; si.className = 'btn btn-peligro'; no.textContent = 'Cancelar'; si.textContent = 'Eliminar';
    const fin = v => { document.removeEventListener('keydown', k, true); o.remove(); ok(v); }, k = e => { if (e.key === 'Escape') fin(false); };
    no.onclick = () => fin(false); si.onclick = () => fin(true); o.addEventListener('click', e => { if (e.target === o) fin(false); }); document.addEventListener('keydown', k, true);
    b.append(p, no, si); o.appendChild(b); document.body.appendChild(o); no.focus(); });

  // ---------- Confeti ----------
  const confeti = (el, n) => { if (quieto || !el) return; const r = el.getBoundingClientRect(), cols = ['#2447e8', '#12a37f', '#e89a22', '#c23a78', '#c8372d'];
    for (let i = 0; i < (n || 22); i++) { const c = document.createElement('i'); c.className = 'cf'; c.style.left = (r.left + r.width / 2) + 'px'; c.style.top = (r.top + r.height / 2) + 'px'; c.style.background = cols[i % 5];
      const a = Math.random() * 6.283, d = 40 + Math.random() * (n ? 160 : 70); c.style.setProperty('--x', Math.cos(a) * d + 'px'); c.style.setProperty('--y', Math.sin(a) * d - 30 + 'px'); c.style.setProperty('--r', Math.random() * 540 + 'deg'); document.body.appendChild(c); setTimeout(() => c.remove(), 1000); } };

  // ---------- Animaciones tras actualizar ----------
  const contar = (el, de, a) => { if (quieto || de === a) return; const con = el.textContent.indexOf('$') > -1, t0 = performance.now();
    const paso = t => { const k = Math.min(1, (t - t0) / 600), v = Math.round(de + (a - de) * (1 - Math.pow(1 - k, 3))); el.textContent = (con ? '$' : '') + v.toLocaleString('es-CO'); if (k < 1) requestAnimationFrame(paso); }; requestAnimationFrame(paso); };
  const numero = t => /^\$?[\d.]+$/.test(t.trim()) ? parseInt(t.replace(/[^\d]/g, ''), 10) : null;
  const animar = antes => {
    $$('.stat .n').forEach((n, i) => { const a = antes.nums[i] != null ? numero(antes.nums[i]) : null, b = numero(n.textContent); if (a !== null && b !== null && a !== b) contar(n, a, b); });
    const fg = $('.ring .r-fg'), ring = $('.ring');
    if (fg && ring) { fg.style.transition = 'none'; fg.style.strokeDashoffset = 100 - antes.pct; void fg.getBoundingClientRect(); fg.style.transition = ''; requestAnimationFrame(() => fg.style.strokeDashoffset = 100 - ring.dataset.pct); }
    $$('.bar i').forEach(i => requestAnimationFrame(() => i.style.width = i.dataset.w + '%'));
    if (antes.pct < 100 && +(ring && ring.dataset.pct) === 100 && $$('.tasks li', $('#tab-tareas') || main).length) { confeti(ring, 70); toast('🎉 ¡Completaste todo tu día!', 'ok'); } };
  const estado = () => ({ pct: +(($('.ring') || {}).dataset || {}).pct || 0, nums: $$('.stat .n').map(n => n.textContent) });
  const aplicar = (doc, antes) => {
    const nuevo = doc.querySelector('.dash-wrap'); if (!nuevo) throw new Error('sin panel');
    ['.hello', '.stats'].forEach(s => { const a = $(s, main), b = $(s, nuevo); if (a && b) a.replaceWith(document.importNode(b, true)); });
    $$('.tabpane', nuevo).forEach(p => { const a = document.getElementById(p.id), b = document.importNode(p, true); if (a) { b.hidden = a.hidden; a.replaceWith(b); } });
    const fl = nuevo.querySelector(':scope > .ok, :scope > .alert'); if (fl) toast(fl.textContent.trim(), fl.classList.contains('alert') ? 'mal' : 'ok');
    animar(antes); decorar(); };
  const refrescar = async () => { const antes = estado(); try { const r = await fetch(location.href.split('#')[0], { credentials: 'same-origin' }); aplicar(new DOMParser().parseFromString(await r.text(), 'text/html'), antes); } catch (_) { location.reload(); } };
  window.nexusRefrescar = refrescar;

  // ---------- Enviar formularios sin recargar ----------
  const enviar = async f => {
    if (f.dataset.busy) return; f.dataset.busy = 1; f.classList.add('busy'); const antes = estado(), pane = f.closest('.tabpane'), accion = (f.querySelector('[name=accion]') || {}).value || '';
    try {
      const r = await fetch(f.action || location.href, { method: 'POST', body: new FormData(f), credentials: 'same-origin' });
      aplicar(new DOMParser().parseFromString(await r.text(), 'text/html'), antes);
      if (/^nuev/.test(accion) && pane) { const i = $('form.inline input[type=text]', document.getElementById(pane.id)); if (i) i.focus(); }
    } catch (_) { HTMLFormElement.prototype.submit.call(f); } };
  document.addEventListener('submit', async e => {
    const f = e.target; if (!main.contains(f) || f.id === 'chat-form') return;
    e.preventDefault(); e.stopPropagation();
    if (f.dataset.confirm && !(await confirmar(f.dataset.confirm))) return; enviar(f); }, true);
  document.addEventListener('change', e => { const c = e.target;
    if (c.matches('form[data-auto] input[type=checkbox]')) { e.stopPropagation(); const li = c.closest('li'); if (li) li.classList.toggle('done', c.checked); if (c.checked) confeti(c.closest('.chk')); enviar(c.form); }
    else if (c.matches('form[data-auto-change] input[type=range]')) { e.stopPropagation(); if (+c.value === 100) confeti(c, 50); enviar(c.form); } }, true);
  document.addEventListener('input', e => { const s = e.target; if (s.matches && s.matches('form[data-auto-change] input[type=range]') && s.nextElementSibling) s.nextElementSibling.textContent = s.value + '%'; });

  // ---------- Temporizador de enfoque ----------
  const el = document.createElement('div'); el.id = 'foco'; el.setAttribute('role', 'timer');
  el.innerHTML = '<h4></h4><div class="t">00:00</div><div class="barra"><i></i></div><div class="acc"><button type="button" data-f="pausa">⏸ Pausa</button><button type="button" data-f="mas">+5 min</button><button type="button" data-f="ok">✓ Terminada</button><button type="button" data-f="x" aria-label="Cerrar">✕</button></div>';
  document.body.appendChild(el);
  let F = null; const guardar = () => { try { F ? localStorage.setItem('nx_foco', JSON.stringify(F)) : localStorage.removeItem('nx_foco'); } catch (_) {} };
  const fmt = s => { s = Math.max(0, Math.ceil(s)); return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); };
  const resto = () => F.pausado ? F.resto : (F.fin - Date.now()) / 1000;
  const pitar = () => { try { const c = new (window.AudioContext || window.webkitAudioContext)(); [0, .28, .56].forEach(t => { const o = c.createOscillator(), g = c.createGain(); o.frequency.value = 880; o.connect(g); g.connect(c.destination);
    g.gain.setValueAtTime(.0001, c.currentTime + t); g.gain.exponentialRampToValueAtTime(.25, c.currentTime + t + .02); g.gain.exponentialRampToValueAtTime(.0001, c.currentTime + t + .22); o.start(c.currentTime + t); o.stop(c.currentTime + t + .25); }); } catch (_) {} };
  const pintar = () => {
    if (!F) { el.classList.remove('on', 'fin'); document.title = titulo0; return; }
    const r = resto(); el.classList.add('on'); $('h4', el).textContent = F.titulo; $('.t', el).textContent = fmt(r); $('.barra i', el).style.width = Math.min(100, (1 - r / F.total) * 100) + '%'; $('[data-f=pausa]', el).textContent = F.pausado ? '▶ Seguir' : '⏸ Pausa';
    document.title = F.pausado ? titulo0 : fmt(r) + ' · ' + F.titulo;
    if (r <= 0 && !F.aviso) { F.aviso = true; guardar(); el.classList.add('fin'); pitar(); toast('⏰ Terminó «' + F.titulo + '»', 'ok'); try { if (document.hidden && Notification.permission === 'granted') new Notification('NEXUS', { body: 'Terminó «' + F.titulo + '»' }); } catch (_) {} } };
  setInterval(pintar, 250);
  try { const g = JSON.parse(localStorage.getItem('nx_foco') || 'null'); if (g && g.titulo) { F = g; if (!F.pausado && F.fin < Date.now()) F.aviso = true; if (F.aviso) el.classList.add('fin'); pintar(); } } catch (_) {}
  el.addEventListener('click', e => { const b = e.target.closest('[data-f]'); if (!b || !F) return; const a = b.dataset.f;
    if (a === 'pausa') { if (F.pausado) { F.fin = Date.now() + F.resto * 1000; F.pausado = false; } else { F.resto = (F.fin - Date.now()) / 1000; F.pausado = true; } }
    if (a === 'mas') { if (F.pausado) F.resto += 300; else F.fin += 300000; F.total += 300; F.aviso = false; el.classList.remove('fin'); }
    if (a === 'ok') { const li = $$('#tab-tareas li').find(l => { const i = l.querySelector('input[name=id]'); return i && i.value === String(F.id); }); const cb = li && li.querySelector('input[type=checkbox]'); if (cb && !cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); } confeti(el, 40); F = null; }
    if (a === 'x') F = null; guardar(); pintar(); });
  document.addEventListener('click', e => { const b = e.target.closest('.play'); if (!b) return; const li = b.closest('li'), id = li.querySelector('input[name=id]'), m = (li.querySelector('div > span') || {}).textContent || '';
    const min = Math.max(1, parseInt((m.match(/(\d+)\s*min/) || [0, 25])[1], 10)); F = { id: id ? id.value : '', titulo: (li.querySelector('b') || {}).textContent || 'Tarea', total: min * 60, fin: Date.now() + min * 60000, pausado: false, resto: 0, aviso: false };
    el.classList.remove('fin'); guardar(); pintar(); try { if (Notification.permission === 'default') Notification.requestPermission(); } catch (_) {} });
  function decorar() { $$('#tab-tareas li').forEach(li => { if (li.classList.contains('done') || li.querySelector('.play') || !li.querySelector('input[name=id]')) return;
    const b = document.createElement('button'); b.type = 'button'; b.className = 'play'; b.title = 'Enfocarme en esta tarea (temporizador)'; b.setAttribute('aria-label', 'Iniciar temporizador de esta tarea'); b.textContent = '▶'; li.insertBefore(b, li.lastElementChild); }); }
  decorar();

  // ---------- Detalles: reloj, brillo en tarjetas, atajos ----------
  setInterval(() => { const r = document.getElementById('reloj'); if (r) r.textContent = new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', hour12: false }); }, 15000);
  document.addEventListener('mousemove', e => { const s = e.target.closest && e.target.closest('.stat'); if (!s) return; const r = s.getBoundingClientRect(); s.style.setProperty('--mx', (e.clientX - r.left) + 'px'); s.style.setProperty('--my', (e.clientY - r.top) + 'px'); }, { passive: true });
  const tabs = $('.tabs'); if (tabs) { const p = document.createElement('p'); p.className = 'atajos'; p.innerHTML = '<kbd>1</kbd>–<kbd>4</kbd> pestañas · <kbd>N</kbd> nueva tarea · <kbd>/</kbd> hablar con NEXUS'; tabs.after(p); }
  document.addEventListener('keydown', e => {
    if (e.ctrlKey || e.metaKey || e.altKey || /INPUT|TEXTAREA|SELECT/.test((e.target || {}).tagName || '') || $('.modal')) return;
    const bt = $$('.tabs button');
    if (/^[1-4]$/.test(e.key) && bt[+e.key - 1]) bt[+e.key - 1].click();
    else if (e.key === 'n' || e.key === 'N') { if (bt[0]) bt[0].click(); const i = $('#tab-tareas form.inline input[type=text]'); if (i) { e.preventDefault(); i.focus(); } }
    else if (e.key === '/') { const i = $('#chat-text'); if (i) { e.preventDefault(); i.focus(); i.scrollIntoView({ behavior: 'smooth', block: 'center' }); } } });
})();
