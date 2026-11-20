/* Fondo del panel: la misma red de nodos del inicio, que sigue al mouse y reacciona al clic. */
(() => {
  const cv = document.getElementById('fondo-red'), gl = document.getElementById('fondo-glow'); if (!cv) return;
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const ctx = cv.getContext && cv.getContext('2d'); if (!ctx) return;
  const tactil = !matchMedia('(hover:hover)').matches, dpr = Math.min(devicePixelRatio || 1, 2), N = [], raiz = document.documentElement;
  let W = 0, H = 0, mx = -999, my = -999, gx = -999, gy = -999;
  const ajustar = () => { W = innerWidth; H = innerHeight; cv.width = W * dpr; cv.height = H * dpr; cv.style.width = W + 'px'; cv.style.height = H + 'px'; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const n = Math.round(Math.min(tactil ? 28 : 60, W * H / 24000)); while (N.length < n) N.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .3, vy: (Math.random() - .5) * .3 }); N.length = Math.max(n, N.filter(a => a.life !== undefined).length); };
  ajustar(); addEventListener('resize', ajustar);
  addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; raiz.style.setProperty('--px', (mx / W - .5).toFixed(3)); raiz.style.setProperty('--py', (my / H - .5).toFixed(3)); }, { passive: true });
  addEventListener('click', e => { if (e.target.closest && e.target.closest('a,button,input,select,label,form,.panel,.stat,.hello,.tabs,.modal,#foco,header,footer') || N.length > 120) return;
    for (let k = 0; k < 8; k++) { const a = Math.random() * 6.283, v = 1.4 + Math.random() * 1.6; N.push({ x: e.clientX, y: e.clientY, vx: Math.cos(a) * v, vy: Math.sin(a) * v, life: 150 + Math.random() * 90 }); } });
  const frame = () => {
    if (gl && !tactil) { gx += (mx - gx) * .12; gy += (my - gy) * .12; gl.style.transform = 'translate3d(' + gx + 'px,' + gy + 'px,0)'; }
    const c = getComputedStyle(raiz).getPropertyValue('--cobalto').trim() || '#2447e8'; ctx.clearRect(0, 0, W, H); ctx.fillStyle = c; ctx.strokeStyle = c;
    for (let i = 0; i < N.length; i++) { const a = N[i]; a.x += a.vx; a.y += a.vy; if (a.x < 0 || a.x > W) a.vx *= -1; if (a.y < 0 || a.y > H) a.vy *= -1;
      if (a.life !== undefined) { a.vx *= .985; a.vy *= .985; if (--a.life <= 0) { N.splice(i, 1); i--; continue; } }
      const dm = Math.hypot(a.x - mx, a.y - my);
      if (dm < 150) { a.x += (mx - a.x) * .012; a.y += (my - a.y) * .012; ctx.globalAlpha = (1 - dm / 150) * .5; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(mx, my); ctx.stroke(); }
      ctx.globalAlpha = .45 * (a.life !== undefined ? Math.min(1, a.life / 60) : 1); ctx.beginPath(); ctx.arc(a.x, a.y, a.life !== undefined ? 2.6 : 2, 0, 6.283); ctx.fill();
      for (let j = i + 1; j < N.length; j++) { const b = N[j], d = Math.hypot(a.x - b.x, a.y - b.y); if (d < 120) { ctx.globalAlpha = (1 - d / 120) * .22; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); } } }
    ctx.globalAlpha = 1; requestAnimationFrame(frame); };
  frame();
})();
