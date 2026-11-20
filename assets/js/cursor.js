/* Cursor personalizado de NEXUS: punto + aro que lo sigue, estela, ripple al hacer clic. Solo con mouse. */
(() => {
  if (!matchMedia('(hover:hover) and (pointer:fine)').matches) return;
  const raiz = document.documentElement, quieto = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const mk = id => { const d = document.createElement('div'); d.id = id; d.setAttribute('aria-hidden', 'true'); document.body.appendChild(d); return d; };
  const dot = mk('cur-dot'), ring = mk('cur-ring');
  let x = -100, y = -100, rx = -100, ry = -100, ux = 0, uy = 0, n = 0, visto = false;
  const clickable = 'a,button,label,select,summary,[role=button],input[type=checkbox],input[type=range],.chk';
  addEventListener('mousemove', e => {
    x = e.clientX; y = e.clientY; if (!visto) { visto = true; rx = x; ry = y; raiz.classList.add('cur-on'); }
    const t = e.target, el = t.closest ? t : t.parentElement;
    raiz.classList.toggle('cur-link', !!(el && el.closest(clickable)));
    raiz.classList.toggle('cur-text', !!(el && el.closest('input:not([type=checkbox]):not([type=range]),textarea')));
    if (!quieto && Math.hypot(x - ux, y - uy) > 22) { ux = x; uy = y; const p = document.createElement('i'); p.className = 'cur-p'; p.style.left = x + 'px'; p.style.top = y + 'px';
      if (++n % 2) p.style.background = 'var(--cobalto)'; document.body.appendChild(p); setTimeout(() => p.remove(), 620); }
  }, { passive: true });
  raiz.addEventListener('mouseleave', () => raiz.classList.remove('cur-on'));
  raiz.addEventListener('mouseenter', () => { if (visto) raiz.classList.add('cur-on'); });
  addEventListener('mousedown', e => { raiz.classList.add('cur-down'); const r = document.createElement('i'); r.className = 'cur-ripple'; r.style.left = e.clientX + 'px'; r.style.top = e.clientY + 'px'; document.body.appendChild(r); setTimeout(() => r.remove(), 520); });
  addEventListener('mouseup', () => raiz.classList.remove('cur-down'));
  const loop = () => { rx += (x - rx) * (quieto ? 1 : .18); ry += (y - ry) * (quieto ? 1 : .18);
    dot.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)'; ring.style.transform = 'translate3d(' + rx + 'px,' + ry + 'px,0)'; requestAnimationFrame(loop); };
  loop();
})();
