document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-shipping], [data-returns], [data-privacy]')
  if (!btn) return
  e.preventDefault()
  var lang = document.documentElement.lang || 'es'
  var attr = ''
  if (btn.hasAttribute('data-shipping')) attr = 'shipping'
  else if (btn.hasAttribute('data-returns')) attr = 'returns'
  else attr = 'privacy'

  var titles = {
    shipping: { es: 'Envíos', en: 'Shipping' },
    returns: { es: 'Devoluciones', en: 'Returns' },
    privacy: { es: 'Política de Privacidad', en: 'Privacy Policy' }
  }

  fetch('/api/configuracion/public.php')
    .then(function (r) { return r.ok ? r.json() : null })
    .then(function (config) {
      if (!config || !config.policies) return
      var text = config.policies[attr + '_' + lang]
      if (!text) text = config.policies[attr + '_es'] || ''
      if (!text) return
      var title = titles[attr][lang] || titles[attr]['es']
      var html = '<div class="prose prose-sm max-w-none"><p class="font-body text-body-md text-secondary leading-relaxed whitespace-pre-line">' + text + '</p></div>'
      if (window.VunoModal && window.VunoModal.show) {
        window.VunoModal.show({ type: 'info', title: title, body: html })
      }
    })
    .catch(function () {})
})
