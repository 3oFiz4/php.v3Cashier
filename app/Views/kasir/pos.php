<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Terminal Kasir - <?= htmlspecialchars($posConfig->storeName ?? 'Ramstore POS') ?></title>
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
        <span id="shiftStatusBadge" class="badge badge-green">Shift: Memuat...</span>
      </div>
      <div class="nav-actions">
        <span id="clock" style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500"></span>
        <span style="font-size: 0.82rem; font-weight: 600; display:inline-flex; align-items:center; gap:0.35rem;">
          <img src="/svg/user.svg" alt="" class="svg-icon svg-icon-sm" /> <?= htmlspecialchars($user['full_name'] ?? 'Kasir') ?> (<?= htmlspecialchars($user['shift'] ?? 'Morning') ?>)
        </span>
        <button class="btn btn-sm" onclick="openShiftModal()"><img src="/svg/reconciliation.svg" alt="" class="svg-icon" /> Rekonsiliasi Kas [F6]</button>
        <button class="btn btn-sm" onclick="openHeldListModal()">
          <img src="/svg/hold.svg" alt="" class="svg-icon" /> Keranjang Tunda (<span id="heldCountBadge">0</span>) [F4]
        </button>
        <button class="btn btn-sm" onclick="openCashierHistoryModal()"><img src="/svg/transactions.svg" alt="" class="svg-icon" /> Riwayat Transaksi</button>
        <button class="btn btn-sm" onclick="openCashierShortcutsModal()" title="Pintasan Keyboard [F1]">
          <img src="/svg/dashboard.svg" alt="" class="svg-icon svg-icon-sm" /> Shortcuts [F1]
        </button>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="/admin" class="btn btn-sm btn-dark">Panel Admin</a>
        <?php endif; ?>
        <button class="btn btn-sm" id="themeToggleBtn" onclick="ThemeManager.toggle()">🌙 Dark</button>
        <button class="btn btn-sm btn-danger" onclick="logoutUser()">Keluar</button>
      </div>
    </nav>

    <div class="main-layout">
      <div class="products-panel">
        <div class="search-bar">
          <input
            type="text"
            id="barcodeScannerInput"
            class="search-input"
            style="max-width: 240px; font-family: monospace;"
            placeholder="Scan Barcode + Enter..."
          />
          <input
            type="text"
            id="searchInput"
            class="search-input"
            placeholder="Cari nama produk atau kode barcode..."
          />
          <select id="categoryFilter" class="filter-select">
            <option value="all">Semua Kategori</option>
          </select>
        </div>

        <div class="products-grid" id="productsGrid"></div>
      </div>

      <div class="cart-panel">
        <div class="cart-card">
          <div class="cart-header">
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <h3 style="font-weight:700;">Keranjang Belanja</h3>
              <span class="cart-count" id="cartCount">0</span>
            </div>
            <div style="display:flex;gap:0.35rem;">
              <button class="btn btn-sm" onclick="openParkCartModal()" title="Parkir / Tunda Keranjang (F4)"><img src="/svg/hold.svg" alt="" class="svg-icon svg-icon-sm" /> Tunda</button>
              <button class="btn btn-sm btn-danger" onclick="Cart.requestClearCart()" title="Void Seluruh Keranjang (F9)"><img src="/svg/trash.svg" alt="" class="svg-icon svg-icon-sm" /> Void</button>
            </div>
          </div>

          <div class="cart-items" id="cartItems">
            <div class="cart-empty">
              <img src="/svg/cart.svg" alt="" style="width: 3.5rem; height: 3.5rem; margin-bottom: 0.5rem;" />
              <span>Keranjang belanja kosong</span>
            </div>
          </div>

          <div class="cart-footer">
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.2rem;">
              <span>Subtotal Kotor</span>
              <span id="cartSubtotal">Rp 0</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;color:#059669;margin-bottom:0.45rem;">
              <span>Potongan Bundling / Promo</span>
              <span id="cartDiscount">-Rp 0</span>
            </div>
            <div class="cart-total">
              <span class="cart-total-label" style="font-weight:600;">Total Tagihan</span>
              <span class="cart-total-amount" id="cartTotal">Rp 0</span>
            </div>
            <button class="checkout-btn" id="checkoutBtn" disabled>
              Proses Pembayaran [F8]
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="shortcut-bar">
      <div>
        <span class="kbd-badge">F1</span> Bantuan &nbsp;
        <span class="kbd-badge">F2</span> Qty &nbsp;
        <span class="kbd-badge">F3</span> Cari &nbsp;
        <span class="kbd-badge">F4</span> Parkir &nbsp;
        <span class="kbd-badge">F6</span> Shift Kas &nbsp;
        <span class="kbd-badge">F7</span> Riwayat &nbsp;
        <span class="kbd-badge">F8</span> Bayar &nbsp;
        <span class="kbd-badge">F9</span> Void &nbsp;
        <span class="kbd-badge">F10</span> Tertunda &nbsp;
        <span class="kbd-badge">Esc</span> Tutup &nbsp;
        <span class="kbd-badge">Enter</span> Struk
      </div>
      <div>Emulasi USB/Wireless Barcode Scanner: <strong>Aktif (&lt;45ms burst)</strong></div>
    </div>

    <div class="modal-overlay" id="paymentModal">
      <div class="modal">
        <div class="modal-header">
          <h3 style="display:inline-flex;align-items:center;gap:0.45rem;"><img src="/svg/payment.svg" alt="" class="svg-icon" /> Pembayaran Multi-Metode &amp; QRIS</h3>
          <button class="modal-close" onclick="closePaymentModal()">×</button>
        </div>
        <div class="modal-body">
          <div id="paymentSummary" style="margin-bottom: 1rem;"></div>

          <div class="form-group">
            <label class="form-label">Metode Pembayaran</label>
            <select id="paymentMethodSelect" class="form-input">
              <option value="Cash">Tunai (Cash)</option>
              <option value="QRIS">QRIS Dinamis</option>
              <option value="Split">Split Payment (Tunai + QRIS/Non-Tunai)</option>
              <option value="Debit">Kartu Debit / EDC</option>
              <option value="E-Wallet">Dompet Digital (E-Wallet)</option>
            </select>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            <div class="form-group">
              <label class="form-label">Nominal Tunai (Rp)</label>
              <input type="number" id="cashAmountInput" class="form-input" value="0" min="0" />
            </div>
            <div class="form-group">
              <label class="form-label">Nominal Non-Tunai / QRIS (Rp)</label>
              <input type="number" id="nonCashAmountInput" class="form-input" value="0" min="0" />
            </div>
          </div>

          <div id="qrisContainer" style="display:none;background:var(--accent);padding:1rem;border-radius:10px;margin-bottom:1rem;text-align:center;">
            <div style="font-weight:700;font-size:0.9rem;margin-bottom:0.25rem;display:inline-flex;align-items:center;gap:0.4rem;justify-content:center;"><img src="/svg/qris.svg" alt="" class="svg-icon" /> Pindai Kode QRIS Dinamis</div>
            <div id="qrisAmountDisplay" style="font-size:1.1rem;font-weight:700;color:#059669;margin-bottom:0.4rem;">Rp 0</div>
            <div style="background:#fff;color:#111;padding:0.75rem;border-radius:8px;font-family:monospace;font-size:0.68rem;word-break:break-all;border:2px dashed #111;" id="qrisStringDisplay"></div>
            <input type="hidden" id="qrisRefInput" value="" />
          </div>

          <div id="changeRow" style="display:none;justify-content:space-between;font-weight:700;color:#059669;font-size:1.05rem;padding-top:0.5rem;">
            <span>Kembalian Tunai</span>
            <span id="changeAmount">Rp 0</span>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn" onclick="closePaymentModal()">Batal</button>
          <button class="btn btn-dark" id="confirmPayBtn" onclick="confirmPayment()" disabled>
            Konfirmasi Pembayaran
          </button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="supervisorModal">
      <div class="modal" style="max-width:420px;">
        <div class="modal-header">
          <h3 style="display:inline-flex;align-items:center;gap:0.45rem;"><img src="/svg/supervisor.svg" alt="" class="svg-icon" /> Otorisasi Supervisor (Void Override)</h3>
          <button class="modal-close" onclick="closeSupervisorModal()">×</button>
        </div>
        <div class="modal-body">
          <p id="voidReasonDisplay" style="font-size:0.85rem;margin-bottom:1rem;color:#d97706;font-weight:600;"></p>
          <div class="form-group">
            <label class="form-label">Username Supervisor / Admin</label>
            <input type="text" id="supervisorUsernameInput" class="form-input" value="admin" />
          </div>
          <div class="form-group">
            <label class="form-label">PIN Supervisor (Default: 123456)</label>
            <input type="password" id="supervisorPinInput" class="form-input" placeholder="Masukkan 6 digit PIN" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn" onclick="closeSupervisorModal()">Batal</button>
          <button class="btn btn-danger" onclick="submitSupervisorPin()">Setujui Void</button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="heldModal">
      <div class="modal" style="max-width:600px;">
        <div class="modal-header">
          <h3 style="display:inline-flex;align-items:center;gap:0.45rem;"><img src="/svg/hold.svg" alt="" class="svg-icon" /> Daftar Transaksi Tertunda (Parked Carts)</h3>
          <button class="modal-close" onclick="closeHeldModal()">×</button>
        </div>
        <div class="modal-body">
          <table class="data-table">
            <thead>
              <tr>
                <th>Kode &amp; Waktu</th>
                <th>Pelanggan / Catatan</th>
                <th>Subtotal</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="heldCartsTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="shiftModal">
      <div class="modal" style="max-width:480px;">
        <div class="modal-header">
          <h3 style="display:inline-flex;align-items:center;gap:0.45rem;"><img src="/svg/reconciliation.svg" alt="" class="svg-icon" /> Rekonsiliasi Kas Shift Kasir</h3>
          <button class="modal-close" onclick="closeShiftModal()">×</button>
        </div>
        <div class="modal-body" id="shiftModalBody"></div>
      </div>
    </div>

    <div class="modal-overlay" id="historyModal">
      <div class="modal" style="max-width:680px;">
        <div class="modal-header">
          <h3 style="display:inline-flex;align-items:center;gap:0.45rem;"><img src="/svg/transactions.svg" alt="" class="svg-icon" /> Riwayat Transaksi Kasir</h3>
          <button class="modal-close" onclick="closeHistoryModal()">×</button>
        </div>
        <div class="modal-body">
          <table class="data-table">
            <thead>
              <tr>
                <th>No. Struk</th>
                <th>Metode</th>
                <th>Total</th>
                <th>Status</th>
                <th>Cetak</th>
              </tr>
            </thead>
            <tbody id="cashierHistoryBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="receiptModal">
      <div class="modal" style="max-width: 400px">
        <div class="modal-header">
          <h3 style="display:inline-flex;align-items:center;gap:0.45rem;"><img src="/svg/transactions.svg" alt="" class="svg-icon" /> Struk Pembayaran</h3>
          <button class="modal-close" onclick="closeReceiptModal()">×</button>
        </div>
        <div class="modal-body">
          <div class="receipt" id="receiptContent"></div>
        </div>
        <div class="modal-footer">
          <button class="btn" onclick="closeReceiptModal()">Tutup</button>
          <button class="btn btn-dark" onclick="printReceipt()"><img src="/svg/printer.svg" alt="" class="svg-icon" /> Cetak Struk [Enter]</button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="cashierShortcutsModal">
      <div class="modal" style="max-width: 580px">
        <div class="modal-header">
          <h3 style="display:inline-flex;align-items:center;gap:0.45rem;">
            <img src="/svg/dashboard.svg" alt="" class="svg-icon" />
            <span>Pintasan Keyboard Terminal Kasir</span>
          </h3>
          <button class="modal-close" onclick="closeCashierShortcutsModal()">×</button>
        </div>
        <div class="modal-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.85rem;font-size:0.84rem;">
            <div class="card" style="padding:0.75rem;">
              <div style="font-weight:700;margin-bottom:0.5rem;color:var(--secondary);">Pintasan Transaksi</div>
              <div style="display:flex;flex-direction:column;gap:0.4rem;">
                <div><span class="kbd-badge">F1</span> Buka / Tutup Bantuan</div>
                <div><span class="kbd-badge">F2</span> Ubah Kuantitas (Qty)</div>
                <div><span class="kbd-badge">F3</span> Fokus Pencarian Barang</div>
                <div><span class="kbd-badge">F4</span> Parkir / Tunda Transaksi</div>
                <div><span class="kbd-badge">F6</span> Rekonsiliasi Kas Shift</div>
                <div><span class="kbd-badge">F7</span> Riwayat Transaksi</div>
                <div><span class="kbd-badge">F8</span> Buka Pembayaran</div>
                <div><span class="kbd-badge">F9</span> Void Supervisor</div>
                <div><span class="kbd-badge">F10</span> Panggil Keranjang Tunda</div>
              </div>
            </div>
            <div class="card" style="padding:0.75rem;">
              <div style="font-weight:700;margin-bottom:0.5rem;color:var(--secondary);">Pintasan Pembayaran &amp; Sistem</div>
              <div style="display:flex;flex-direction:column;gap:0.4rem;">
                <div><span class="kbd-badge">1</span> / <span class="kbd-badge">Alt+1</span> Metode Tunai (Cash)</div>
                <div><span class="kbd-badge">2</span> / <span class="kbd-badge">Alt+2</span> Metode QRIS Dinamis</div>
                <div><span class="kbd-badge">3</span> / <span class="kbd-badge">Alt+3</span> Metode Split Payment</div>
                <div><span class="kbd-badge">4</span> / <span class="kbd-badge">Alt+4</span> Kartu Debit / EDC</div>
                <div><span class="kbd-badge">5</span> / <span class="kbd-badge">Alt+5</span> Dompet Digital (E-Wallet)</div>
                <div><span class="kbd-badge">Enter</span> Konfirmasi Bayar / Cetak Struk</div>
                <div><span class="kbd-badge">Alt + B</span> Fokus Emulasi Scanner</div>
                <div><span class="kbd-badge">Alt + D</span> Toggle Mode Gelap / Terang</div>
                <div><span class="kbd-badge">Escape</span> Tutup Dialog / Batal</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-dark" onclick="closeCashierShortcutsModal()">Tutup [Esc]</button>
        </div>
      </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="/assets/js/theme.js"></script>
    <script src="/assets/js/cart.js"></script>
    <script src="/assets/js/barcode_shortcuts.js"></script>
    <script src="/assets/js/pos_app.js"></script>
  </body>
</html>
