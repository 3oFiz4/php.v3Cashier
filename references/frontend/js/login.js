/**
 * Login Handler
 */

const API_BASE = "/v2cashier/backend/php";

document.addEventListener("DOMContentLoaded", () => {
  // Check if already logged in
  checkExistingSession();

  document.getElementById("loginForm").addEventListener("submit", handleLogin);
});

async function checkExistingSession() {
  try {
    const res = await fetch(`${API_BASE}/auth.php?action=check`);
    const data = await res.json();
    if (data.authenticated) {
      window.location.href = "admin.html";
    }
  } catch (err) {
    // Not logged in, stay on login page
  }
}

async function handleLogin(e) {
  e.preventDefault();

  const username = document.getElementById("username").value.trim();
  const password = document.getElementById("password").value;
  const errorEl = document.getElementById("loginError");

  errorEl.style.display = "none";

  if (!username || !password) {
    errorEl.textContent = "Please enter username and password";
    errorEl.style.display = "block";
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/auth.php?action=login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ username, password }),
    });

    const data = await res.json();

    if (data.success) {
      window.location.href = "admin.html";
    } else {
      errorEl.textContent = data.error || "Login failed";
      errorEl.style.display = "block";
    }
  } catch (err) {
    errorEl.textContent = "Connection error. Please try again.";
    errorEl.style.display = "block";
  }
}
