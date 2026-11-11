(function () {
  const TOKEN_KEY = "valenor_admin_token";
  const USER_KEY = "valenor_admin_user";

  function getStoredToken() {
    return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY) || "";
  }

  // Redirect cleanly if already holding an active admin token
  if (getStoredToken()) {
    fetch("/api/admin/me", {
      headers: { Authorization: `Bearer ${getStoredToken()}` }
    })
      .then((res) => {
        if (res.ok) window.location.replace("./index.html");
      })
      .catch(() => {});
  }

  const form = document.getElementById("form-admin-login");
  const alertBox = document.getElementById("admin-login-alert");
  const submitBtn = document.getElementById("btn-admin-signin");

  function showAlert(msg, type = "error") {
    alertBox.textContent = msg;
    alertBox.className = `form-alert ${type}`;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    alertBox.className = "form-alert";

    const identifier = document.getElementById("admin-identifier").value.trim();
    const password = document.getElementById("admin-password").value;
    const rememberMe = document.getElementById("admin-remember").checked;

    if (!identifier || !password) {
      return showAlert("Please enter your administrator email or phone and password.");
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Verifying...";

    try {
      const res = await fetch("/api/admin/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ identifier, password, rememberMe })
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.message || "Authentication failed.");

      const storage = rememberMe ? localStorage : sessionStorage;
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_KEY);
      sessionStorage.removeItem(TOKEN_KEY);
      sessionStorage.removeItem(USER_KEY);

      storage.setItem(TOKEN_KEY, json.token);
      storage.setItem(USER_KEY, JSON.stringify(json.user));

      window.location.href = "./index.html";
    } catch (err) {
      showAlert(err.message);
      submitBtn.disabled = false;
      submitBtn.textContent = "Access Admin Dashboard";
    }
  });
})();

