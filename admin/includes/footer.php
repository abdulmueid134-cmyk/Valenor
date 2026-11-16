<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN PANEL FOOTER
// =============================================================================
?>
</main>

<footer class="p-3 bg-white border-top text-center text-muted small" style="border-color:var(--border-beige) !important;">
    &copy; <?= date('Y') ?> <strong>VALENOR Horology</strong> — Dedicated Admin Concierge Management System. Built with Core PHP &amp; MySQL.
</footer>

</div> <!-- .admin-main -->
</div> <!-- .admin-wrapper -->

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Sidebar Toggle for Mobile
const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
const adminSidebar = document.getElementById('adminSidebar');

if (sidebarToggleBtn && adminSidebar) {
    sidebarToggleBtn.addEventListener('click', () => {
        adminSidebar.classList.toggle('show');
    });
}
if (sidebarCloseBtn && adminSidebar) {
    sidebarCloseBtn.addEventListener('click', () => {
        adminSidebar.classList.remove('show');
    });
}
</script>

</body>
</html>
