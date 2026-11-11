<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login Sistem - Ramstore POS (v3Cashier CI4)</title>
    <link rel="stylesheet" href="/assets/css/style.css" />
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body>
    <nav class="nav">
      <div class="nav-brand">
        <span style="display:inline-flex;align-items:center;gap:0.45rem;">
          <img src="/svg/ramstore-logo.svg" alt="Ramstore POS Logo" class="brand-logo-svg" />
          <span>Ramstore POS</span>
        </span>
        <span class="badge badge-blue">CodeIgniter 4 MVC</span>
      </div>
      <div class="nav-actions">
        <button class="btn btn-sm" id="themeToggleBtn" onclick="ThemeManager.toggle()">🌙 Dark</button>
      </div>
    </nav>

    <div class="login-container">
      <div class="card login-card">
        <div style="text-align: center; margin-bottom: 1.5rem">
          <h2 style="font-size: 1.45rem; font-weight: 700">Masuk ke Ramstore POS</h2>
          <p style="color: var(--text-muted); font-size: 0.86rem; margin-top: 0.35rem">
            Autentikasi Terpadu Admin &amp; Kasir Minimarket
          </p>
        </div>

        <div
          id="loginError"
          style="
            display: none;
            background: #fee2e2;
            color: #991b1b;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            font-size: 0.84rem;
            margin-bottom: 1rem;
          "
        ></div>

        <form id="loginForm">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input
              type="text"
              id="username"
              class="form-input"
              placeholder="Masukkan username (admin / kasir_pagi)"
              autocomplete="username"
              required
            />
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input
              type="password"
              id="password"
              class="form-input"
              placeholder="Masukkan password"
              autocomplete="current-password"
              required
            />
          </div>
          <button type="submit" class="btn btn-dark" style="width: 100%; padding: 0.8rem">
            Masuk Sekarang
          </button>
        </form>

        <div style="margin-top: 1.35rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.78rem; color: var(--text-muted);">
          <div style="margin-bottom: 0.5rem; font-weight: 600;">Pintasan Akun Demo:</div>
          <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
            <button type="button" class="btn btn-sm" onclick="fillDemoAccount('admin', 'admin123')">Admin (admin / admin123)</button>
            <button type="button" class="btn btn-sm" onclick="fillDemoAccount('kasir_pagi', 'kasir123')">Kasir Pagi (kasir_pagi / kasir123)</button>
            <button type="button" class="btn btn-sm" onclick="fillDemoAccount('kasir_siang', 'kasir123')">Kasir Siang</button>
          </div>
          <div style="margin-top: 0.45rem;">PIN Otorisasi Void Supervisor Default: <code>123456</code></div>
        </div>
      </div>
    </div>

    <script src="/assets/js/theme.js"></script>
    <script src="/assets/js/login.js"></script>
  </body>
</html>
