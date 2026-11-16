/**
 * VALENOR HOROLOGY — STOREFRONT INTERACTION SCRIPT
 * Handles live countdown, AJAX Cart/Wishlist/Compare, toast feedback, and newsletter.
 */

document.addEventListener('DOMContentLoaded', function () {

    // -------------------------------------------------------------------------
    // 1. DYNAMIC ANNOUNCEMENT & CAMPAIGN COUNTDOWN TIMER
    // -------------------------------------------------------------------------
    function initCountdown() {
        const daysEl = document.getElementById('cd-days');
        const hoursEl = document.getElementById('cd-hours');
        const minsEl = document.getElementById('cd-mins');
        const secsEl = document.getElementById('cd-secs');

        if (!daysEl || !hoursEl || !minsEl || !secsEl) return;

        // 48 hours rolling campaign target
        let targetMs = Date.now() + (47 * 3600 + 58 * 60 + 30) * 1000;

        function updateTimer() {
            let diff = targetMs - Date.now();
            if (diff <= 0) {
                targetMs = Date.now() + 48 * 3600 * 1000;
                diff = targetMs - Date.now();
            }

            const totalSec = Math.floor(diff / 1000);
            const days = Math.floor(totalSec / 86400);
            const hours = Math.floor((totalSec % 86400) / 3600);
            const mins = Math.floor((totalSec % 3600) / 60);
            const secs = totalSec % 60;

            daysEl.textContent = String(days).padStart(2, '0');
            hoursEl.textContent = String(hours).padStart(2, '0');
            minsEl.textContent = String(mins).padStart(2, '0');
            secsEl.textContent = String(secs).padStart(2, '0');
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    }
    initCountdown();

    // -------------------------------------------------------------------------
    // 2. LUXURY TOAST NOTIFICATIONS
    // -------------------------------------------------------------------------
    window.showToast = function (message, type = 'info', actionHtml = '') {
        let container = document.querySelector('.valenor-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'valenor-toast-container';
            document.body.appendChild(container);
        }

        const iconMap = {
            success: 'bi-check-circle-fill text-success',
            warning: 'bi-exclamation-triangle-fill text-warning',
            info:    'bi-info-circle-fill text-primary',
            danger:  'bi-x-circle-fill text-danger'
        };

        const toast = document.createElement('div');
        toast.className = `valenor-toast valenor-toast-${type}`;
        toast.innerHTML = `
            <i class="bi ${iconMap[type] || 'bi-info-circle-fill'} fs-5"></i>
            <div class="flex-grow-1" style="font-size:0.86rem;">
                <div>${message}</div>
                ${actionHtml ? `<div class="mt-1">${actionHtml}</div>` : ''}
            </div>
            <button type="button" class="btn-close ms-2" aria-label="Close" style="font-size:0.65rem;"></button>
        `;

        toast.querySelector('.btn-close').addEventListener('click', function () {
            toast.remove();
        });

        container.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    };

    // -------------------------------------------------------------------------
    // 3. AJAX ADD TO CART INTERACTION
    // -------------------------------------------------------------------------
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-add-cart, .btn-quick-cart');
        if (!btn) return;

        e.preventDefault();
        const productId = btn.dataset.productId;
        const variantId = btn.dataset.variantId || null;
        const quantity = btn.dataset.quantity || 1;

        if (!productId) return;

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding...';

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        if (variantId) formData.append('variant_id', variantId);
        formData.append('quantity', quantity);

        fetch('ajax/cart.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;

            if (data.success) {
                // Update all cart count badges on the page
                document.querySelectorAll('.cart-badge').forEach(badge => {
                    badge.textContent = data.cart_count;
                    badge.classList.remove('d-none');
                });

                const viewCartLink = data.is_logged_in
                    ? '<a href="cart.php" class="btn btn-sm btn-dark mt-1 py-1 px-2" style="font-size:0.75rem;">View Bag &rarr;</a>'
                    : '<a href="login.php?redirect=cart.php" class="btn btn-sm btn-dark mt-1 py-1 px-2" style="font-size:0.75rem;">Sign In to View Bag &rarr;</a>';

                showToast(data.message, 'success', viewCartLink);
            } else {
                showToast(data.message || 'Could not add product to cart.', 'warning');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showToast('Unable to connect to server. Please try again.', 'danger');
        });
    });

    // -------------------------------------------------------------------------
    // 4. AJAX WISHLIST TOGGLE
    // -------------------------------------------------------------------------
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-toggle-wishlist');
        if (!btn) return;

        e.preventDefault();
        const productId = btn.dataset.productId;
        if (!productId) return;

        const formData = new FormData();
        formData.append('action', 'toggle');
        formData.append('product_id', productId);

        fetch('ajax/wishlist.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Toggle active state and icon
                btn.classList.toggle('active', data.is_wishlisted);
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = data.is_wishlisted ? 'bi bi-heart-fill text-danger' : 'bi bi-heart';
                }

                document.querySelectorAll('.wishlist-badge').forEach(badge => {
                    badge.textContent = data.wishlist_count;
                });

                showToast(data.message, 'info');
            }
        })
        .catch(() => {
            showToast('Wishlist operation failed.', 'danger');
        });
    });

    // -------------------------------------------------------------------------
    // 5. AJAX COMPARE TOGGLE
    // -------------------------------------------------------------------------
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-toggle-compare');
        if (!btn) return;

        e.preventDefault();
        const productId = btn.dataset.productId;
        if (!productId) return;

        const formData = new FormData();
        formData.append('action', 'toggle');
        formData.append('product_id', productId);

        fetch('ajax/compare.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btn.classList.toggle('active', data.is_compared);
                document.querySelectorAll('.compare-badge').forEach(badge => {
                    badge.textContent = data.compare_count;
                });
                const compLink = '<a href="compare.php" class="btn btn-sm btn-dark mt-1 py-1 px-2" style="font-size:0.75rem;">Compare Now &rarr;</a>';
                showToast(data.message, 'info', compLink);
            } else {
                showToast(data.message, 'warning');
            }
        })
        .catch(() => {
            showToast('Comparison request failed.', 'danger');
        });
    });

    // -------------------------------------------------------------------------
    // 6. COUPON COPY INTERACTION
    // -------------------------------------------------------------------------
    document.querySelectorAll('.coupon-copy-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const code = this.dataset.code || 'VALENOR20';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    showToast(`Privilege Code <strong>${code}</strong> copied to clipboard!`, 'success');
                });
            } else {
                showToast(`Use privilege coupon code: <strong>${code}</strong>`, 'info');
            }
        });
    });

    // -------------------------------------------------------------------------
    // 7. NEWSLETTER SUBSCRIPTION
    // -------------------------------------------------------------------------
    const newsForm = document.getElementById('newsletter-form');
    if (newsForm) {
        newsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const emailInput = document.getElementById('newsletter-email');
            const email = (emailInput?.value || '').trim();

            if (!email || !email.includes('@')) {
                showToast('Please enter a valid email address.', 'warning');
                return;
            }

            const btn = newsForm.querySelector('button[type="submit"]');
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Subscribing...';

            const formData = new FormData();
            formData.append('email', email);

            fetch('ajax/newsletter.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = orig;
                if (data.success) {
                    emailInput.value = '';
                    showToast(data.message || 'Thank you for subscribing to VALENOR Horology Gazette.', 'success');
                } else {
                    showToast(data.message || 'Subscription could not be processed.', 'warning');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = orig;
                emailInput.value = '';
                showToast('Thank you for subscribing to VALENOR Horology Gazette!', 'success');
            });
        });
    }

});
