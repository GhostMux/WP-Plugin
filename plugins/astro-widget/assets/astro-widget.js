(function(){
  function $(sel, el){ return (el||document).querySelector(sel); }
  function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  document.addEventListener('DOMContentLoaded', function(){
    const form = $('#astro-form');
    const out  = $('#astro-output');
    if (!form || !out) return;

    // Config from wp_localize_script or shortcode fallbacks
    const cfg = (window.AstroWidgetCfg || {});
    const endpointFromDOM = form.getAttribute('data-endpoint');
    const nonceFromDOM    = form.getAttribute('data-nonce');

    form.addEventListener('submit', async function(e){
      e.preventDefault();
      out.textContent = 'Generating…';

      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());

      if (payload.lat === '') delete payload.lat; else payload.lat = String(payload.lat);
      if (payload.lng === '') delete payload.lng; else payload.lng = String(payload.lng);

      const endpoint = cfg.endpoint || endpointFromDOM;
      const nonce    = (cfg.nonce) || nonceFromDOM || payload._awnonce;
      delete payload._awnonce;

      if (!endpoint || !nonce) {
        out.innerHTML = '<div class="error">Config missing: endpoint/nonce not found.</div>';
        return;
      }

      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Astro-Nonce': nonce },
          body: JSON.stringify(payload),
          credentials: 'same-origin',
          redirect: 'error',
          cache: 'no-store',
          mode: 'same-origin'
        });
        const ct = res.headers.get('content-type') || '';
        const data = ct.includes('application/json') ? await res.json() : { raw: await res.text() };

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
