// VALENOR Horology — Login, Registration & Password Reset Controller
(() => {
  const AUTH = window.VALENOR_AUTH;

  function showAlert(msg, kind = "error") {
    const el = document.getElementById("auth-alert");
    el.className = `form-alert show ${kind}`;
    el.textContent = msg;
  }

  function clearAlert() {
    const el = document.getElementById("auth-alert");
    el.className = "form-alert";
    el.textContent = "";
  }

  function switchTab(tab) {
    clearAlert();
    document.querySelectorAll("[data-auth-tab]").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.authTab === tab);
    });
    document.getElementById("form-login").style.display = tab === "login" ? "grid" : "none";
    document.getElementById("form-register").style.display = tab === "register" ? "grid" : "none";
    document.getElementById("panel-forgot").style.display = tab === "forgot" ? "block" : "none";
  }

  function redirectAfterAuth() {
    const params = new URLSearchParams(window.location.search);
    const next = params.get("next") || "account.html";
    window.location.href = next;
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (AUTH.isAuthenticated()) {
      window.location.href = "account.html";
      return;
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get("tab")) switchTab(params.get("tab"));

    document.querySelectorAll("[data-auth-tab]").forEach((btn) => {
      btn.addEventListener("click", () => switchTab(btn.dataset.authTab));
    });

    document.getElementById("btn-goto-forgot").addEventListener("click", () => switchTab("forgot"));

    document.getElementById("form-login").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();
      try {
        await AUTH.login({
          identifier: document.getElementById("login-identifier").value,
          password: document.getElementById("login-password").value,
          rememberMe: document.getElementById("login-remember").checked
        });
        redirectAfterAuth();
      } catch (err) {
        showAlert(err.message);
      }
    });

    document.getElementById("form-register").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();
      try {
        await AUTH.register({
          fullName: document.getElementById("reg-fullname").value,
          email: document.getElementById("reg-email").value,
          phone: document.getElementById("reg-phone").value,
          password: document.getElementById("reg-password").value,
          confirmPassword: document.getElementById("reg-confirm").value
        });
        redirectAfterAuth();
      } catch (err) {
        showAlert(err.message);
      }
    });

    document.getElementById("form-forgot-request").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();
      try {
        const res = await AUTH.requestPasswordReset(document.getElementById("forgot-identifier").value);
        showAlert(res.message, "success");
        document.getElementById("form-reset-confirm").style.display = "grid";
      } catch (err) {
        showAlert(err.message);
      }
    });

    document.getElementById("form-reset-confirm").addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();
      try {
        const msg = await AUTH.resetPassword({
          token: document.getElementById("reset-token").value,
          password: document.getElementById("reset-password").value,
          confirmPassword: document.getElementById("reset-confirm").value
        });
        switchTab("login");
        showAlert(msg, "success");
      } catch (err) {
        showAlert(err.message);
      }
    });
  });
})();

