(function () {
  var btn = document.getElementById('whatsapp-btn')
  var popover = document.getElementById('wa-popover')
  var closeBtn = document.getElementById('wa-popover-close')
  var sendBtn = document.getElementById('wa-send')
  var textarea = document.getElementById('wa-message')
  if (!btn || !popover) return

  var waNumber = ''
  var openedByBtn = false

  function openPopover() {
    popover.classList.remove('hidden')
    openedByBtn = true
    setTimeout(function () { textarea.focus() }, 100)
  }

  function closePopover() {
    popover.classList.add('hidden')
    openedByBtn = false
  }

  function sendMessage() {
    if (!waNumber) return
    var msg = textarea.value.trim()
    var url = 'https://wa.me/' + encodeURIComponent(waNumber)
    if (msg) url += '?text=' + encodeURIComponent(msg)
    window.open(url, '_blank')
    closePopover()
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault()
    if (popover.classList.contains('hidden')) {
      openPopover()
    } else {
      closePopover()
    }
  })

  closeBtn.addEventListener('click', closePopover)
  sendBtn.addEventListener('click', sendMessage)

  textarea.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      sendMessage()
    }
  })

  document.addEventListener('click', function (e) {
    if (!openedByBtn) return
    var target = e.target
    if (btn.contains(target) || popover.contains(target)) return
    closePopover()
  })

  fetch('/api/configuracion/public.php')
    .then(function (r) { return r.json() })
    .then(function (data) {
      var wa = data.whatsapp || {}
      if (wa.enabled && wa.number) {
        waNumber = wa.number
        if (wa.message) textarea.value = wa.message
        btn.classList.remove('hidden')
      }
    })
    .catch(function () {})
})()
