    </div>
  </div>

  <nav class="bottom-nav" aria-label="Pintasan">
    <?php foreach ($bottom as $n): ?>
      <a href="<?= e(url($area . '/' . $n[1])) ?>" class="<?= $page === $n[0] ? 'on' : '' ?>"><?= icon($n[2]) ?><?= e($n[3]) ?></a>
    <?php endforeach; ?>
    <button type="button" id="menu-btn-2"><?= icon('menu') ?>Menu</button>
  </nav>
</div>
<script>
(function () {
  var app = document.getElementById('app');
  function toggle(open) {
    app.classList.toggle('menu-open', open);
    document.getElementById('menu-btn').setAttribute('aria-expanded', open);
  }
  document.getElementById('menu-btn').addEventListener('click', function () { toggle(!app.classList.contains('menu-open')); });
  document.getElementById('menu-btn-2').addEventListener('click', function () { toggle(true); });
  app.addEventListener('click', function (e) { if (app.classList.contains('menu-open') && !e.target.closest('.side') && !e.target.closest('#menu-btn') && !e.target.closest('#menu-btn-2')) toggle(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });

  // Butang salin
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var text = b.dataset.copy, old = b.innerHTML;
      function ok() { b.textContent = 'Disalin ✓'; setTimeout(function () { b.innerHTML = old; }, 1600); }
      if (navigator.clipboard) { navigator.clipboard.writeText(text).then(ok, function () { prompt('Salin pautan ini:', text); }); }
      else { prompt('Salin pautan ini:', text); }
    });
  });

  // Borang yang perlu pengesahan: dialog sendiri (window.confirm disekat sesetengah pelayar dalam app)
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (f.dataset.ok === '1') return;
      e.preventDefault();
      var d = document.getElementById('confirm-dlg');
      d.querySelector('p').textContent = f.dataset.confirm;
      d.showModal();
      d.querySelector('[data-yes]').onclick = function () { d.close(); f.dataset.ok = '1'; (f.requestSubmit ? f.requestSubmit(e.submitter) : f.submit()); };
    });
  });
})();
</script>
<dialog class="modal" id="confirm-dlg">
  <div class="modal-body"><h3 style="margin-bottom:.4rem">Pasti?</h3><p class="muted" style="margin:0"></p></div>
  <div class="modal-foot"><button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Batal</button><button type="button" class="btn btn-danger" data-yes>Ya, teruskan</button></div>
</dialog>
<?= $footScripts ?? '' ?>
</body>
</html>
