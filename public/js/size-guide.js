document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-size-guide]')
  if (!btn) return
  e.preventDefault()
  var lang = document.documentElement.lang || 'es'

  function buildTable(data) {
    if (!data || !data.rows || !data.rows.length) return null
    var title = lang === 'es' ? data.title_es : data.title_en
    var footer = lang === 'es' ? data.footer_es : data.footer_en
    if (!title) title = data.title_es || 'Size Guide'
    var html = '<h3 class="font-headline text-headline-md mb-4">' + title + '</h3>'
    html += '<table class="w-full text-left border-collapse"><thead><tr class="border-b border-outline-variant">'
    html += '<th class="font-label-caps text-label-caps text-secondary pb-2 pr-4">US</th>'
    html += '<th class="font-label-caps text-label-caps text-secondary pb-2 pr-4">EU</th>'
    html += '<th class="font-label-caps text-label-caps text-secondary pb-2 pr-4">UK</th>'
    html += '<th class="font-label-caps text-label-caps text-secondary pb-2">CM</th>'
    html += '</tr></thead><tbody>'
    for (var i = 0; i < data.rows.length; i++) {
      var r = data.rows[i]
      var cls = i < data.rows.length - 1 ? ' class="border-b border-outline-variant/50"' : ''
      html += '<tr' + cls + '>'
      html += '<td class="py-2 pr-4 font-medium">' + r.us + '</td>'
      html += '<td class="py-2 pr-4 text-secondary">' + r.eu + '</td>'
      html += '<td class="py-2 pr-4 text-secondary">' + r.uk + '</td>'
      html += '<td class="py-2 text-secondary">' + r.cm + '</td></tr>'
    }
    html += '</tbody></table>'
    if (footer) {
      html += '<p class="font-body text-body-md text-secondary mt-4 text-sm">' + footer + '</p>'
    }
    return html
  }

  fetch('/api/size-guide/public.php')
    .then(function (r) { return r.ok ? r.json() : null })
    .then(function (data) {
      var html = buildTable(data)
      if (html && window.VunoModal && window.VunoModal.show) {
        window.VunoModal.show({
          type: 'info',
          title: lang === 'es' ? 'Guía de Talles' : 'Size Guide',
          body: html
        })
      }
    })
    .catch(function () {})
})
