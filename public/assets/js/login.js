// kelola proses autentikasi halaman login admin dan kasir ramstore pos
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("loginForm");
  if (form) {
    form.addEventListener("submit", handleLoginSubmit);
  }
});

// isi cepat kredensial akun demo saat tombol pintas diklik
function fillDemoAccount(username, password) {
  document.getElementById("username").value = username;
  document.getElementById("password").value = password;
}

// kirim kredensial login ke endpoint api autentikasi codeigniter 4
async function handleLoginSubmit(e) {
  e.preventDefault();
  const username = document.getElementById("username").value.trim();
  const password = document.getElementById("password").value;
  const errorEl = document.getElementById("loginError");

  errorEl.style.display = "none";

  if (!username || !password) {
    errorEl.textContent = "Username dan password wajib diisi";
    errorEl.style.display = "block";
    return;
  }

  try {
    const res = await fetch("/api/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ username, password }),
    });
    const data = await res.json();

    if (data.success) {
      window.location.href = data.redirect || "/pos";
    } else {
      errorEl.textContent = data.error || "Gagal masuk ke sistem";
      errorEl.style.display = "block";
    }
  } catch (err) {
    errorEl.textContent = "Terjadi gangguan koneksi ke server";
    errorEl.style.display = "block";
  }
}
