<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - <?= htmlspecialchars($posConfig->storeName ?? 'Ramstore POS') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css" />
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body>
    <nav class="nav">
      <div class="nav-brand">
        <span>🏪 Ramstore Admin Dashboard</span>
        <span class="badge badge-blue">v3Cashier CI4</span>
      </div>
      <div class="nav-actions">
        <span style="font-size: 0.84rem; color: var(--text-muted)">
          Halo, <strong><?= htmlspecialchars($user['full_name'] ?? 'Administrator') ?></strong>
        </span>
        <a href="/pos" class="btn btn-sm">🛒 Terminal Kasir</a>
        <button class="btn btn-sm" id="themeToggleBtn" onclick="ThemeManager.toggle()">🌙 Dark</button>
        <button class="btn btn-sm btn-danger" id="logoutBtn">Keluar</button>
      </div>
    </nav>

    <div class="admin-layout">
      <div class="admin-sidebar">
        <nav class="sidebar-nav">
          <button class="sidebar-link active" data-tab="dashboard">📊 Dashboard &amp; Peringatan</button>
          <button class="sidebar-link" data-tab="products">📦 Produk &amp; Batch FEFO</button>
          <button class="sidebar-link" data-tab="categories">🏷️ Kategori Barang</button>
          <button class="sidebar-link" data-tab="users">👥 Akun Admin &amp; Kasir</button>
          <button class="sidebar-link" data-tab="analytics">📈 Analitik Laba &amp; Margin</button>
          <button class="sidebar-link" data-tab="po">🚚 PO &amp; Pemasok</button>
          <button class="sidebar-link" data-tab="opname">📋 Stok Opname &amp; Selisih</button>
          <button class="sidebar-link" data-tab="promos">🎁 Promosi &amp; Bundling</button>
          <button class="sidebar-link" data-tab="transactions">🧾 Transaksi, Shift &amp; Void</button>
          <button class="sidebar-link" data-tab="audit">🛡️ Audit Trail &amp; Backup</button>
        </nav>
      </div>

      <div class="admin-content">
        <div class="admin-tab active" id="tab-dashboard">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 1.15rem">Ringkasan Operasional &amp; Peringatan Stok</h2>
          <div class="stats-grid" id="statsGrid"></div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem">
            <div class="card">
              <div class="section-header">
                <h3 style="font-weight:700;">⚠️ Peringatan Stok Rendah (Low-Stock Alerts)</h3>
              </div>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Sisa / Min</th>
                    <th>Harga Jual</th>
                  </tr>
                </thead>
                <tbody id="lowStockAlertsBody"></tbody>
              </table>
            </div>

            <div class="card">
              <div class="section-header">
                <h3 style="font-weight:700;">⏳ Peringatan Batch Mendekati Kadaluarsa (FEFO ≤ 3 Hari)</h3>
              </div>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Batch &amp; Produk</th>
                    <th>Tgl Exp</th>
                    <th>Status</th>
                    <th>Stok</th>
                  </tr>
                </thead>
                <tbody id="expiryAlertsBody"></tbody>
              </table>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem">
            <div class="card">
              <div class="section-header"><h3 style="font-weight:700;">Transaksi Terbaru</h3></div>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Struk</th>
                    <th>Kasir</th>
                    <th>Total</th>
                    <th>Metode</th>
                    <th>Waktu</th>
                  </tr>
                </thead>
                <tbody id="recentTxnBody"></tbody>
              </table>
            </div>
            <div class="card">
              <div class="section-header"><h3 style="font-weight:700;">Produk Terlaris</h3></div>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th>Terjual</th>
                    <th>Pendapatan</th>
                  </tr>
                </thead>
                <tbody id="bestsellersBody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="admin-tab" id="tab-products">
          <div class="section-header">
            <h2 style="font-size: 1.4rem; font-weight: 700">Manajemen Katalog Barang &amp; Batch FEFO</h2>
            <button class="btn btn-dark" onclick="openProductModal()">+ Tambah Produk</button>
          </div>
          <div class="card" style="margin-bottom:1.5rem;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Barcode</th>
                  <th>Nama Barang</th>
                  <th>Kategori</th>
                  <th>Harga Beli</th>
                  <th>Harga Jual</th>
                  <th>Stok</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="productsTableBody"></tbody>
            </table>
          </div>

          <div class="card">
            <h3 style="font-weight:700;margin-bottom:1rem;">Registrasi Batch Baru &amp; Pelacakan Kadaluarsa (FEFO)</h3>
            <div style="display:grid;grid-template-columns:repeat(5, 1fr);gap:0.65rem;margin-bottom:1rem;">
              <select id="batchProductId" class="form-input"></select>
              <input type="text" id="batchNumberInput" class="form-input" placeholder="Nomor Batch (mis. BATCH-2611)" />
              <input type="date" id="batchExpiryInput" class="form-input" />
              <input type="number" id="batchQtyInput" class="form-input" placeholder="Jumlah Unit" />
              <input type="number" id="batchCostInput" class="form-input" placeholder="Harga Beli Satuan" />
            </div>
            <button class="btn btn-dark" style="margin-bottom:1rem;" onclick="submitNewBatch()">+ Simpan Batch FEFO</button>
            <table class="data-table">
              <thead>
                <tr>
                  <th>No. Batch</th>
                  <th>Produk</th>
                  <th>Tgl Masuk</th>
                  <th>Tgl Kadaluarsa</th>
                  <th>Stok Batch</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="batchesTableBody"></tbody>
            </table>
          </div>
        </div>

        <div class="admin-tab" id="tab-categories">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom:1rem;">Manajemen Kategori Barang (jenis_barang)</h2>
          <div class="card" style="margin-bottom:1.25rem;">
            <div style="display:grid;grid-template-columns:1fr 2fr auto;gap:0.75rem;">
              <input type="text" id="categoryNameInput" class="form-input" placeholder="Nama Kategori Baru" />
              <input type="text" id="categoryDescInput" class="form-input" placeholder="Deskripsi Kategori" />
              <button class="btn btn-dark" onclick="saveCategory()">+ Tambah Kategori</button>
            </div>
          </div>
          <div class="card">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nama Kategori</th>
                  <th>Deskripsi</th>
                  <th>Jumlah Produk</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="categoriesTableBody"></tbody>
            </table>
          </div>
        </div>

        <div class="admin-tab" id="tab-users">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom:1rem;">Manajemen Akun Admin &amp; Kasir</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
            <div class="card">
              <h3 style="font-weight:700;margin-bottom:0.75rem;">Tambah Administrator / Supervisor</h3>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem;">
                <input type="text" id="newAdminName" class="form-input" placeholder="Nama Lengkap" />
                <input type="text" id="newAdminUser" class="form-input" placeholder="Username" />
                <input type="password" id="newAdminPass" class="form-input" placeholder="Password" />
                <input type="text" id="newAdminPin" class="form-input" placeholder="PIN Void (123456)" />
              </div>
              <button class="btn btn-dark" style="margin-bottom:1rem;" onclick="submitSaveAdmin()">+ Simpan Admin</button>
              <table class="data-table">
                <thead>
                  <tr><th>ID</th><th>Nama</th><th>Username</th><th>Terdaftar</th><th>Aksi</th></tr>
                </thead>
                <tbody id="adminsTableBody"></tbody>
              </table>
            </div>

            <div class="card">
              <h3 style="font-weight:700;margin-bottom:0.75rem;">Tambah Petugas Kasir &amp; Shift</h3>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem;">
                <input type="text" id="newKasirName" class="form-input" placeholder="Nama Lengkap Kasir" />
                <input type="text" id="newKasirUser" class="form-input" placeholder="Username Kasir" />
                <input type="password" id="newKasirPass" class="form-input" placeholder="Password Kasir" />
                <input type="text" id="newKasirPhone" class="form-input" placeholder="No. Telepon" />
                <select id="newKasirShift" class="form-input">
                  <option value="Morning">Morning (Pagi)</option>
                  <option value="Afternoon">Afternoon (Siang)</option>
                  <option value="Night">Night (Malam)</option>
                </select>
                <button class="btn btn-dark" onclick="submitSaveKasir()">+ Simpan Kasir</button>
              </div>
              <table class="data-table">
                <thead>
                  <tr><th>ID</th><th>Nama</th><th>User</th><th>Telepon</th><th>Shift</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody id="cashiersTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="admin-tab" id="tab-analytics">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom:1rem;">Analitik Laba Kotor (Gross Profit) &amp; Margin Penjualan</h2>
          <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Margin Laba Kotor per Kategori Produk</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Kategori</th>
                  <th>Unit Terjual</th>
                  <th>Pendapatan Bersih</th>
                  <th>Total HPP (Harga Beli)</th>
                  <th>Laba Kotor</th>
                  <th>Margin (%)</th>
                </tr>
              </thead>
              <tbody id="marginByCategoryBody"></tbody>
            </table>
          </div>
          <div class="card">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Margin Laba Kotor per Produk</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Produk</th>
                  <th>Unit Terjual</th>
                  <th>Pendapatan Bersih</th>
                  <th>Total HPP</th>
                  <th>Laba Kotor</th>
                  <th>Margin (%)</th>
                </tr>
              </thead>
              <tbody id="marginByProductBody"></tbody>
            </table>
          </div>
        </div>

        <div class="admin-tab" id="tab-po">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom:1rem;">Purchase Order (Stok Masuk) &amp; Manajemen Pemasok</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">
            <div class="card">
              <h3 style="font-weight:700;margin-bottom:0.75rem;">Input Faktur Pengiriman Stok Masuk (PO)</h3>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem;">
                <input type="text" id="poInvoiceInput" class="form-input" placeholder="No. Faktur (mis. INV-SUP-099)" />
                <select id="poSupplierId" class="form-input"></select>
                <select id="poProductId" class="form-input"></select>
                <input type="number" id="poCratesInput" class="form-input" placeholder="Jml Karton/Krat (mis. 50)" value="10" />
                <input type="number" id="poUnitsPerCrateInput" class="form-input" placeholder="Isi per Karton (mis. 24)" value="24" />
                <input type="number" id="poUnitCostInput" class="form-input" placeholder="Harga Beli Satuan (Rp)" value="4800" />
                <input type="date" id="poExpiryInput" class="form-input" value="2027-06-30" />
                <input type="text" id="poNotesInput" class="form-input" placeholder="Catatan Pengiriman" />
              </div>
              <button class="btn btn-dark" onclick="submitNewPurchaseOrder()">+ Terima Barang &amp; Tambah Stok Otomatis</button>
            </div>

            <div class="card">
              <h3 style="font-weight:700;margin-bottom:0.75rem;">Registrasi Pemasok / Distributor Baru</h3>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.75rem;">
                <input type="text" id="supNameInput" class="form-input" placeholder="Nama Perusahaan Pemasok" />
                <input type="text" id="supContactInput" class="form-input" placeholder="Nama Kontak Person" />
                <input type="text" id="supPhoneInput" class="form-input" placeholder="Nomor Telepon" />
                <input type="text" id="supAddressInput" class="form-input" placeholder="Alamat Gudang/Kantor" />
              </div>
              <button class="btn btn-dark" style="margin-bottom:1rem;" onclick="submitNewSupplier()">+ Tambah Pemasok</button>
              <table class="data-table">
                <thead><tr><th>Kode</th><th>Pemasok</th><th>Kontak</th><th>Alamat</th></tr></thead>
                <tbody id="suppliersTableBody"></tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Riwayat Faktur Pembelian Stok (Purchase Orders)</h3>
            <table class="data-table">
              <thead><tr><th>No. Faktur</th><th>Pemasok</th><th>Unit Masuk</th><th>Total Biaya</th><th>Catatan</th></tr></thead>
              <tbody id="poTableBody"></tbody>
            </table>
          </div>
        </div>

        <div class="admin-tab" id="tab-opname">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom:1rem;">Stok Opname &amp; Pelacakan Selisih Fisik (Variance Tracking)</h2>
          <div class="card" style="margin-bottom:1.5rem;">
            <div style="display:grid;grid-template-columns:2fr 1fr 2fr auto;gap:0.75rem;">
              <select id="opnameProductId" class="form-input"></select>
              <input type="number" id="opnamePhysicalInput" class="form-input" placeholder="Jumlah Fisik Dihitung" />
              <input type="text" id="opnameReasonInput" class="form-input" placeholder="Keterangan Selisih / Penyusutan" />
              <button class="btn btn-dark" onclick="submitStockOpname()">Simpan Hasil Opname</button>
            </div>
          </div>
          <div class="card">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Kode &amp; Tanggal</th>
                  <th>Produk</th>
                  <th>Stok Sistem</th>
                  <th>Stok Fisik</th>
                  <th>Selisih (Variance)</th>
                  <th>Alasan</th>
                </tr>
              </thead>
              <tbody id="opnameTableBody"></tbody>
            </table>
          </div>
        </div>

        <div class="admin-tab" id="tab-promos">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom:1rem;">Mesin Harga Promosi &amp; Paket Bundling</h2>
          <div class="card" style="margin-bottom:1.5rem;">
            <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:0.6rem;margin-bottom:0.75rem;">
              <input type="text" id="promoNameInput" class="form-input" placeholder="Nama Promo (mis. Beli 2 Kopi Rp 14rb)" />
              <select id="promoProductId" class="form-input"></select>
              <select id="promoTypeSelect" class="form-input">
                <option value="Bundle_Pair">Bundle_Pair (Harga Paket Kuantitas)</option>
                <option value="Min_Qty_Cut">Min_Qty_Cut (Potongan Rupiah)</option>
                <option value="Percent_Discount">Percent_Discount (Diskon %)</option>
              </select>
              <input type="number" id="promoMinQtyInput" class="form-input" placeholder="Min Qty (mis. 2)" value="2" />
              <input type="number" id="promoBundlePriceInput" class="form-input" placeholder="Harga Paket (mis. 14000)" />
              <input type="number" id="promoCutInput" class="form-input" placeholder="Potongan Rp (mis. 2000)" />
              <input type="number" id="promoPercentInput" class="form-input" placeholder="Diskon % (mis. 10)" />
              <div style="display:flex;gap:0.4rem;">
                <input type="date" id="promoStartInput" class="form-input" value="2026-11-01" />
                <input type="date" id="promoEndInput" class="form-input" value="2026-12-31" />
              </div>
            </div>
            <button class="btn btn-dark" onclick="submitNewPromo()">+ Buat Aturan Promosi</button>
          </div>
          <div class="card">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Nama Promo</th>
                  <th>Produk</th>
                  <th>Tipe</th>
                  <th>Skema Diskon</th>
                  <th>Periode</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="promotionsTableBody"></tbody>
            </table>
          </div>
        </div>

        <div class="admin-tab" id="tab-transactions">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom:1rem;">Laporan Riwayat Transaksi, Rekonsiliasi Shift &amp; Otorisasi Void</h2>
          <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Daftar Seluruh Transaksi Penjualan</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>No. Struk</th>
                  <th>Kasir</th>
                  <th>Item</th>
                  <th>Total</th>
                  <th>Metode</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="transactionsTableBody"></tbody>
            </table>
          </div>

          <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Laporan Rekonsiliasi Kas Shift Kasir</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Kasir &amp; Shift</th>
                  <th>Waktu Shift</th>
                  <th>Modal Awal</th>
                  <th>Tunai Sistem</th>
                  <th>Kas Fisik</th>
                  <th>Selisih Kas</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody id="shiftsTableBody"></tbody>
            </table>
          </div>

          <div class="card">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Log Otorisasi Void &amp; Supervisor Override</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Jenis Void</th>
                  <th>Kasir</th>
                  <th>Supervisor</th>
                  <th>Nominal</th>
                  <th>Alasan</th>
                </tr>
              </thead>
              <tbody id="voidLogsTableBody"></tbody>
            </table>
          </div>
        </div>

        <div class="admin-tab" id="tab-audit">
          <div class="section-header">
            <h2 style="font-size: 1.4rem; font-weight: 700">Jejak Audit Sistem (Audit Trail) &amp; Pencadangan Database (.sql.gz)</h2>
            <button class="btn btn-dark" onclick="runDatabaseBackupNow()">💾 Jalankan Backup Database (.sql.gz) Sekarang</button>
          </div>

          <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Arsip Cadangan Database Terkompresi (.sql.gz)</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Nama File</th>
                  <th>Ukuran</th>
                  <th>Lokasi</th>
                  <th>Pemicu</th>
                  <th>Waktu Backup</th>
                </tr>
              </thead>
              <tbody id="backupsTableBody"></tbody>
            </table>
          </div>

          <div class="card">
            <h3 style="font-weight:700;margin-bottom:0.75rem;">Log Audit Imutabel Aktivitas Pengguna &amp; Perubahan Data</h3>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Pelaku</th>
                  <th>Aksi</th>
                  <th>Modul</th>
                  <th>Perubahan Data (Lama → Baru)</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody id="auditLogsTableBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="productModal">
      <div class="modal">
        <div class="modal-header">
          <h3 id="productModalTitle">Tambah Produk</h3>
          <button class="modal-close" onclick="closeProductModal()">×</button>
        </div>
        <div class="modal-body">
          <form id="productForm">
            <input type="hidden" id="productId" />
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
              <div class="form-group">
                <label class="form-label">Kode Barcode *</label>
                <input type="text" id="productBarcode" class="form-input" placeholder="899100..." required />
              </div>
              <div class="form-group">
                <label class="form-label">Kategori *</label>
                <select id="productCategory" class="form-input" required></select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Nama Barang *</label>
              <input type="text" id="productName" class="form-input" required />
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
              <div class="form-group">
                <label class="form-label">Harga Beli / HPP (Rp) *</label>
                <input type="number" id="productCost" class="form-input" min="0" required />
              </div>
              <div class="form-group">
                <label class="form-label">Harga Jual Ritel (Rp) *</label>
                <input type="number" id="productPrice" class="form-input" min="0" required />
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
              <div class="form-group">
                <label class="form-label">Stok Tersedia *</label>
                <input type="number" id="productStock" class="form-input" min="0" required />
              </div>
              <div class="form-group">
                <label class="form-label">Batas Peringatan Stok Rendah *</label>
                <input type="number" id="productMinStock" class="form-input" value="15" min="1" required />
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn" onclick="closeProductModal()">Batal</button>
          <button class="btn btn-dark" onclick="saveProduct()">Simpan Produk</button>
        </div>
      </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="/assets/js/theme.js"></script>
    <script src="/assets/js/admin_app.js"></script>
  </body>
</html>
