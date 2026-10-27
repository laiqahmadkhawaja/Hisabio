<?php
/**
 * Hisabio - Shared Footer Component
 */
?>
    </main>
  </div>

  <?php include __DIR__ . '/bottom-nav.php'; ?>

  <!-- Non-Modal Toast Container -->
  <div id="toast-container" aria-live="polite"></div>

  <!-- Scripts -->
  <script src="<?= BASE_URL ?>assets/js/app.js?v=<?= APP_VERSION ?>"></script>
  <script src="<?= BASE_URL ?>assets/js/ajax.js?v=<?= APP_VERSION ?>"></script>
  <script src="<?= BASE_URL ?>assets/js/ledger.js?v=<?= APP_VERSION ?>"></script>

  <!-- Trigger PHP Flash Messages -->
  <?php if (!empty($flashes)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($flashes as $flash): ?>
          Hisabio.toast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
        <?php endforeach; ?>
      });
    </script>
  <?php endif; ?>
</body>
</html>
