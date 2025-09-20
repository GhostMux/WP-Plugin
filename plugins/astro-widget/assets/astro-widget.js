(function(){
  function $(sel, el){ return (el||document).querySelector(sel); }
  function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  document.addEventListener('DOMContentLoaded', function(){
    const form = $('#astro-form');
    const out  = $('#astro-output');
    if (!form || !out) return;

    form.addEventListener('submit', async function(e){
      e.preventDefault();
      out.textContent = 'Generating…';

      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());

      if (payload.lat === '') delete payload.lat; else payload.lat = String(payload.lat);
      if (payload.lng === '') delete payload.lng; else payload.lng = String(payload.lng);

      const nonce = (window.AstroWidgetCfg && AstroWidgetCfg.nonce) || payload._awnonce;
      delete payload._awnonce;

      try {
        const res = await fetch(AstroWidgetCfg.endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Astro-Nonce': nonce
          },
          body: JSON.stringify(payload),
          credentials: 'same-origin',
          redirect: 'error',
          cache: 'no-store',
          mode: 'same-origin'
        });

        const ct = res.headers.get('content-type') || '';
        let data;
        if (ct.includes('application/json')) data = await res.json();
        else data = { raw: await res.text() };

        if (!res.ok) {
          out.innerHTML = '<div class="error">Error: ' + escapeHtml(data.error || res.statusText) + '</div>';
          return;
        }

        out.innerHTML = '<pre class="json">'+escapeHtml(JSON.stringify(data, null, 2))+'</pre>';
      } catch (err) {
        out.innerHTML = '<div class="error">Request failed: '+escapeHtml(err.message)+'</div>';
      }
    });
  });
})();
