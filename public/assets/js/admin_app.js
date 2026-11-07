// kelola seluruh modul dashboard admin, master data, analitik margin, po, opname, audit, dan backup
let adminCategories = [];
let adminProducts = [];
let adminSuppliers = [];

// inisialisasi halaman dashboard admin saat dokumen selesai dimuat
document.addEventListener("DOMContentLoaded", () => {
  setupAdminNavigation();
  loadDashboardSummary();
  loadCategoriesDropdown();
});

// atur perpindahan tab menu samping pada panel admin
function setupAdminNavigation() {
  document.querySelectorAll(".sidebar-link").forEach((link) => {
    link.addEventListener("click", () => {
      const tab = link.dataset.tab;
      document.querySelectorAll(".sidebar-link").forEach((l) => l.classList.remove("active"));
      link.classList.add("active");

      document.querySelectorAll(".admin-tab").forEach((t) => t.classList.remove("active"));
      const target = document.getElementById(`tab-${tab}`);
      if (target) target.classList.add("active");

      if (tab === "dashboard") loadDashboardSummary();
      if (tab === "products") {
        loadAdminProducts();
        loadAdminBatches();
      }
      if (tab === "categories") loadAdminCategories();
      if (tab === "users") loadAdminUsers();
      if (tab === "analytics") loadMarginAnalytics();
      if (tab === "po") loadPurchaseOrdersAndSuppliers();
      if (tab === "opname") loadStockOpnameRecords();
      if (tab === "promos") loadAdminPromotions();
      if (tab === "transactions") loadAdminTransactionsAndShifts();
      if (tab === "audit") loadAuditAndBackups();
    });
  });

  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", async () => {
      await fetch("/api/auth/logout", { method: "POST" });
      window.location.href = "/login";
    });
  }
}

// muat ringkasan statistik, peringatan stok rendah, dan peringatan kadaluarsa fefo
async function loadDashboardSummary() {
  try {
    const res = await fetch("/api/admin/dashboard");
    const data = await res.json();
    const s = data.summary || {};

    document.getElementById("statsGrid").innerHTML = `
      <div class="stat-card">
        <div class="stat-value">Rp ${formatRupiah(s.today_revenue)}</div>
        <div class="stat-label">Pendapatan Hari Ini (${s.today_transactions} Struk)</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">Rp ${formatRupiah(s.total_revenue)}</div>
        <div class="stat-label">Total Pendapatan Kumulatif</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#059669;">Rp ${formatRupiah(s.total_gross_profit)}</div>
        <div class="stat-label">Total Laba Kotor (Gross Profit)</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#d97706;">${s.low_stock} Produk</div>
        <div class="stat-label">Peringatan Stok Rendah (Habis: ${s.out_of_stock})</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:#dc2626;">${s.near_expiry_count} Batch</div>
        <div class="stat-label">Mendekati Kadaluarsa (≤ 3 Hari)</div>
      </div>
    `;

    const lowBody = document.getElementById("lowStockAlertsBody");
    const lowItems = data.low_stock_alerts || [];
    lowBody.innerHTML =
      lowItems.length === 0
        ? '<tr><td colspan="4" style="text-align:center;">Seluruh produk berada di atas batas minimum</td></tr>'
        : lowItems
            .map(
              (item) => `
          <tr>
            <td><strong>${escapeHtml(item.nama_barang)}</strong><br><small>${escapeHtml(item.barcode)}</small></td>
            <td>${escapeHtml(item.category_name)}</td>
            <td><span class="badge ${item.stok <= 0 ? "badge-red" : "badge-yellow"}">${item.stok} / Min ${item.stok_minimum}</span></td>
            <td>Rp ${formatRupiah(item.harga_jual)}</td>
          </tr>
        `
            )
            .join("");

    const expBody = document.getElementById("expiryAlertsBody");
    const expItems = data.near_expiry_alerts || [];
    expBody.innerHTML =
      expItems.length === 0
        ? '<tr><td colspan="4" style="text-align:center;">Tidak ada batch yang mendekati tanggal kadaluarsa</td></tr>'
        : expItems
            .map(
              (b) => `
          <tr>
            <td><strong>${escapeHtml(b.nomor_batch)}</strong><br><small>${escapeHtml(b.nama_barang)}</small></td>
            <td>${escapeHtml(b.tanggal_kadaluarsa)}</td>
            <td><span class="badge ${b.sisa_hari < 0 ? "badge-red" : "badge-yellow"}">${b.sisa_hari} hari lagi</span></td>
            <td>${b.stok_tersedia} unit</td>
          </tr>
        `
            )
            .join("");

    const recentBody = document.getElementById("recentTxnBody");
    recentBody.innerHTML = (data.recent_transactions || [])
      .map(
        (t) => `
        <tr>
          <td><strong>${escapeHtml(t.no_struk)}</strong></td>
          <td>${escapeHtml(t.nama_kasir)}</td>
          <td>Rp ${formatRupiah(t.total_harga)}</td>
          <td><span class="badge badge-blue">${escapeHtml(t.metode_pembayaran)}</span></td>
          <td><small>${escapeHtml(t.tanggal_transaksi)}</small></td>
        </tr>
      `
      )
      .join("");

    const bestBody = document.getElementById("bestsellersBody");
    bestBody.innerHTML = (data.bestsellers || [])
      .map(
        (b) => `
        <tr>
          <td><strong>${escapeHtml(b.product_name)}</strong></td>
          <td>${b.total_sold} unit</td>
          <td>Rp ${formatRupiah(b.total_revenue)}</td>
        </tr>
      `
      )
      .join("");
  } catch (err) {
    showAdminToast("Gagal memuat ringkasan dashboard", "error");
  }
}

async function loadCategoriesDropdown() {
  try {
    const res = await fetch("/api/admin/categories");
    const data = await res.json();
    adminCategories = data.categories || [];
    const sel = document.getElementById("productCategory");
    if (sel) {
      sel.innerHTML = '<option value="">Pilih Kategori</option>';
      adminCategories.forEach((c) => {
        sel.innerHTML += `<option value="${c.id_jenis}">${escapeHtml(c.nama_jenis)}</option>`;
      });
    }
  } catch (err) {}
}

async function loadAdminProducts() {
  const res = await fetch("/api/admin/products");
  const data = await res.json();
  adminProducts = data.products || [];

  const tbody = document.getElementById("productsTableBody");
  tbody.innerHTML = adminProducts
    .map(
      (p) => `
      <tr>
        <td><code>${escapeHtml(p.barcode)}</code></td>
        <td><strong>${escapeHtml(p.nama_barang)}</strong></td>
        <td>${escapeHtml(p.category_name)}</td>
        <td>Rp ${formatRupiah(p.harga_beli)}</td>
        <td><strong>Rp ${formatRupiah(p.harga_jual)}</strong></td>
        <td><span class="badge ${p.stok <= p.stok_minimum ? "badge-yellow" : "badge-green"}">${p.stok} (Min: ${p.stok_minimum})</span></td>
        <td>
          <button class="btn btn-sm" onclick='openProductModal(${JSON.stringify(p)})'>Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteProduct(${p.id_barang}, '${escapeHtml(p.nama_barang)}')">Hapus</button>
        </td>
      </tr>
    `
    )
    .join("");

  populateProductSelects();
}

function populateProductSelects() {
  ["batchProductId", "poProductId", "opnameProductId", "promoProductId"].forEach((id) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.innerHTML = '<option value="">Pilih Produk</option>';
    adminProducts.forEach((p) => {
      el.innerHTML += `<option value="${p.id_barang}">${escapeHtml(p.barcode)} - ${escapeHtml(p.nama_barang)} (Stok: ${p.stok})</option>`;
    });
  });
}

function openProductModal(p = null) {
  const modal = document.getElementById("productModal");
  if (p) {
    document.getElementById("productModalTitle").textContent = "Edit Produk";
    document.getElementById("productId").value = p.id_barang;
    document.getElementById("productBarcode").value = p.barcode;
    document.getElementById("productName").value = p.nama_barang;
    document.getElementById("productCategory").value = p.id_jenis;
    document.getElementById("productCost").value = p.harga_beli;
    document.getElementById("productPrice").value = p.harga_jual;
    document.getElementById("productStock").value = p.stok;
    document.getElementById("productMinStock").value = p.stok_minimum || 15;
  } else {
    document.getElementById("productModalTitle").textContent = "Tambah Produk Baru";
    document.getElementById("productId").value = "";
    document.getElementById("productForm").reset();
  }
  modal.classList.add("active");
}

function closeProductModal() {
  document.getElementById("productModal").classList.remove("active");
}

async function saveProduct() {
  const id = document.getElementById("productId").value;
  const payload = {
    id_barang: id ? parseInt(id) : 0,
    barcode: document.getElementById("productBarcode").value.trim(),
    nama_barang: document.getElementById("productName").value.trim(),
    id_jenis: parseInt(document.getElementById("productCategory").value) || 0,
    harga_beli: parseFloat(document.getElementById("productCost").value) || 0,
    harga_jual: parseFloat(document.getElementById("productPrice").value) || 0,
    stok: parseInt(document.getElementById("productStock").value) || 0,
    stok_minimum: parseInt(document.getElementById("productMinStock").value) || 15,
  };

  const url = id ? "/api/admin/products/update" : "/api/admin/products/create";
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    closeProductModal();
    loadAdminProducts();
    loadDashboardSummary();
    showAdminToast(data.message || "Produk berhasil disimpan");
  } else {
    showAdminToast(data.error || "Gagal menyimpan produk", "error");
  }
}

async function deleteProduct(id, name) {
  if (!confirm(`Hapus produk "${name}"?`)) return;
  const res = await fetch("/api/admin/products/delete", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_barang: id }),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminProducts();
    showAdminToast("Produk dihapus");
  } else {
    showAdminToast(data.error || "Gagal menghapus produk", "error");
  }
}

async function loadAdminBatches() {
  const res = await fetch("/api/admin/batches");
  const data = await res.json();
  const tbody = document.getElementById("batchesTableBody");
  tbody.innerHTML = (data.batches || [])
    .map(
      (b) => `
      <tr>
        <td><strong>${escapeHtml(b.nomor_batch)}</strong></td>
        <td>${escapeHtml(b.nama_barang)}</td>
        <td>${escapeHtml(b.tanggal_masuk)}</td>
        <td><strong>${escapeHtml(b.tanggal_kadaluarsa)}</strong> (${b.sisa_hari} hari)</td>
        <td>${b.stok_tersedia} / ${b.stok_awal}</td>
        <td><span class="badge ${
          b.status_batch === "Active"
            ? "badge-green"
            : b.status_batch === "Near_Expiry"
            ? "badge-yellow"
            : "badge-red"
        }">${escapeHtml(b.status_batch)}</span></td>
      </tr>
    `
    )
    .join("");
}

async function submitNewBatch() {
  const payload = {
    id_barang: parseInt(document.getElementById("batchProductId").value) || 0,
    nomor_batch: document.getElementById("batchNumberInput").value.trim(),
    tanggal_kadaluarsa: document.getElementById("batchExpiryInput").value,
    stok_awal: parseInt(document.getElementById("batchQtyInput").value) || 0,
    harga_beli_batch: parseFloat(document.getElementById("batchCostInput").value) || 0,
  };
  const res = await fetch("/api/admin/batches/create", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminBatches();
    loadAdminProducts();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal menambah batch", "error");
  }
}

async function loadAdminCategories() {
  await loadCategoriesDropdown();
  const tbody = document.getElementById("categoriesTableBody");
  tbody.innerHTML = adminCategories
    .map(
      (c) => `
      <tr>
        <td>${c.id_jenis}</td>
        <td><strong>${escapeHtml(c.nama_jenis)}</strong></td>
        <td>${escapeHtml(c.deskripsi || "-")}</td>
        <td>${c.product_count} produk</td>
        <td>
          <button class="btn btn-sm btn-danger" onclick="deleteCategory(${c.id_jenis})">Hapus</button>
        </td>
      </tr>
    `
    )
    .join("");
}

async function saveCategory() {
  const nama = document.getElementById("categoryNameInput").value.trim();
  const desk = document.getElementById("categoryDescInput").value.trim();
  const res = await fetch("/api/admin/categories/create", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ nama_jenis: nama, deskripsi: desk }),
  });
  const data = await res.json();
  if (data.success) {
    document.getElementById("categoryNameInput").value = "";
    document.getElementById("categoryDescInput").value = "";
    loadAdminCategories();
    showAdminToast("Kategori berhasil ditambahkan");
  } else {
    showAdminToast(data.error || "Gagal menambah kategori", "error");
  }
}

async function deleteCategory(id) {
  const res = await fetch("/api/admin/categories/delete", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_jenis: id }),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminCategories();
    showAdminToast("Kategori dihapus");
  } else {
    showAdminToast(data.error || "Gagal menghapus kategori", "error");
  }
}

async function loadAdminUsers() {
  const res = await fetch("/api/admin/users");
  const data = await res.json();

  document.getElementById("adminsTableBody").innerHTML = (data.admins || [])
    .map(
      (a) => `
      <tr>
        <td>${a.id_admin}</td>
        <td><strong>${escapeHtml(a.nama_lengkap)}</strong></td>
        <td><code>${escapeHtml(a.username)}</code></td>
        <td>${escapeHtml(a.tanggal_registrasi)}</td>
        <td><button class="btn btn-sm btn-danger" onclick="deleteAdminAccount(${a.id_admin})">Hapus</button></td>
      </tr>
    `
    )
    .join("");

  document.getElementById("cashiersTableBody").innerHTML = (data.cashiers || [])
    .map(
      (k) => `
      <tr>
        <td>${k.id_kasir}</td>
        <td><strong>${escapeHtml(k.nama_lengkap)}</strong></td>
        <td><code>${escapeHtml(k.username)}</code></td>
        <td>${escapeHtml(k.no_telepon)}</td>
        <td><span class="badge badge-blue">${escapeHtml(k.shift)}</span></td>
        <td><span class="badge ${k.status_aktif == 1 ? "badge-green" : "badge-red"}">${k.status_aktif == 1 ? "Aktif" : "Nonaktif"}</span></td>
        <td><button class="btn btn-sm btn-danger" onclick="deactivateKasir(${k.id_kasir})">Nonaktifkan</button></td>
      </tr>
    `
    )
    .join("");
}

async function submitSaveAdmin() {
  const payload = {
    nama_lengkap: document.getElementById("newAdminName").value.trim(),
    username: document.getElementById("newAdminUser").value.trim(),
    password: document.getElementById("newAdminPass").value,
    pin_supervisor: document.getElementById("newAdminPin").value || "123456",
  };
  const res = await fetch("/api/admin/users/admin/save", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminUsers();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal menyimpan admin", "error");
  }
}

async function deleteAdminAccount(id) {
  const res = await fetch("/api/admin/users/admin/delete", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_admin: id }),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminUsers();
    showAdminToast("Admin dihapus");
  } else {
    showAdminToast(data.error || "Gagal menghapus admin", "error");
  }
}

async function submitSaveKasir() {
  const payload = {
    nama_lengkap: document.getElementById("newKasirName").value.trim(),
    username: document.getElementById("newKasirUser").value.trim(),
    password: document.getElementById("newKasirPass").value,
    no_telepon: document.getElementById("newKasirPhone").value.trim(),
    shift: document.getElementById("newKasirShift").value,
  };
  const res = await fetch("/api/admin/users/kasir/save", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminUsers();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal menyimpan kasir", "error");
  }
}

async function deactivateKasir(id) {
  await fetch("/api/admin/users/kasir/delete", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_kasir: id }),
  });
  loadAdminUsers();
}

async function loadMarginAnalytics() {
  const res = await fetch("/api/admin/analytics/margin");
  const data = await res.json();

  document.getElementById("marginByCategoryBody").innerHTML = (data.by_category || [])
    .map(
      (c) => `
      <tr>
        <td><strong>${escapeHtml(c.kategori)}</strong></td>
        <td>${c.unit_terjual} unit</td>
        <td>Rp ${formatRupiah(c.pendapatan_bersih)}</td>
        <td>Rp ${formatRupiah(c.total_hpp)}</td>
        <td style="color:#059669;font-weight:700;">Rp ${formatRupiah(c.laba_kotor)}</td>
        <td><span class="badge badge-green">${c.margin_persen}%</span></td>
      </tr>
    `
    )
    .join("");

  document.getElementById("marginByProductBody").innerHTML = (data.by_product || [])
    .map(
      (p) => `
      <tr>
        <td><strong>${escapeHtml(p.nama_barang)}</strong></td>
        <td>${p.unit_terjual} unit</td>
        <td>Rp ${formatRupiah(p.pendapatan_bersih)}</td>
        <td>Rp ${formatRupiah(p.total_hpp)}</td>
        <td style="color:#059669;font-weight:700;">Rp ${formatRupiah(p.laba_kotor)}</td>
        <td><span class="badge badge-blue">${p.margin_persen}%</span></td>
      </tr>
    `
    )
    .join("");
}

async function loadPurchaseOrdersAndSuppliers() {
  await loadAdminProducts();
  const supRes = await fetch("/api/admin/suppliers");
  const supData = await supRes.json();
  adminSuppliers = supData.suppliers || [];

  const supSelect = document.getElementById("poSupplierId");
  if (supSelect) {
    supSelect.innerHTML = '<option value="">Pilih Pemasok</option>';
    adminSuppliers.forEach((s) => {
      supSelect.innerHTML += `<option value="${s.id_pemasok}">${escapeHtml(s.kode_pemasok)} - ${escapeHtml(s.nama_pemasok)}</option>`;
    });
  }

  document.getElementById("suppliersTableBody").innerHTML = adminSuppliers
    .map(
      (s) => `
      <tr>
        <td><code>${escapeHtml(s.kode_pemasok)}</code></td>
        <td><strong>${escapeHtml(s.nama_pemasok)}</strong></td>
        <td>${escapeHtml(s.kontak_person)} (${escapeHtml(s.no_telepon)})</td>
        <td>${escapeHtml(s.alamat || "-")}</td>
      </tr>
    `
    )
    .join("");

  const poRes = await fetch("/api/admin/purchase-orders");
  const poData = await poRes.json();
  document.getElementById("poTableBody").innerHTML = (poData.purchase_orders || [])
    .map(
      (po) => `
      <tr>
        <td><strong>${escapeHtml(po.no_faktur_masuk)}</strong><br><small>${escapeHtml(po.tanggal_pembelian)}</small></td>
        <td>${escapeHtml(po.nama_pemasok)}</td>
        <td>+${po.total_unit_masuk} unit</td>
        <td>Rp ${formatRupiah(po.total_biaya)}</td>
        <td>${escapeHtml(po.catatan || "-")}</td>
      </tr>
    `
    )
    .join("");
}

async function submitNewSupplier() {
  const payload = {
    nama_pemasok: document.getElementById("supNameInput").value.trim(),
    kontak_person: document.getElementById("supContactInput").value.trim(),
    no_telepon: document.getElementById("supPhoneInput").value.trim(),
    alamat: document.getElementById("supAddressInput").value.trim(),
  };
  const res = await fetch("/api/admin/suppliers/create", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    loadPurchaseOrdersAndSuppliers();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal menambah pemasok", "error");
  }
}

async function submitNewPurchaseOrder() {
  const payload = {
    no_faktur_masuk: document.getElementById("poInvoiceInput").value.trim(),
    id_pemasok: parseInt(document.getElementById("poSupplierId").value) || 0,
    id_barang: parseInt(document.getElementById("poProductId").value) || 0,
    jumlah_kemasan: parseInt(document.getElementById("poCratesInput").value) || 1,
    unit_per_kemasan: parseInt(document.getElementById("poUnitsPerCrateInput").value) || 24,
    harga_beli_satuan: parseFloat(document.getElementById("poUnitCostInput").value) || 0,
    tanggal_kadaluarsa: document.getElementById("poExpiryInput").value,
    catatan: document.getElementById("poNotesInput").value.trim(),
  };
  const res = await fetch("/api/admin/purchase-orders/create", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    loadPurchaseOrdersAndSuppliers();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal menyimpan PO", "error");
  }
}

async function loadStockOpnameRecords() {
  await loadAdminProducts();
  const res = await fetch("/api/admin/opname");
  const data = await res.json();
  document.getElementById("opnameTableBody").innerHTML = (data.opname_records || [])
    .map(
      (o) => `
      <tr>
        <td><strong>${escapeHtml(o.kode_opname)}</strong><br><small>${escapeHtml(o.tanggal_opname)}</small></td>
        <td>${escapeHtml(o.nama_barang)}</td>
        <td>${o.stok_sistem}</td>
        <td><strong>${o.stok_fisik}</strong></td>
        <td><span class="badge ${o.selisih_unit < 0 ? "badge-red" : "badge-green"}">${o.selisih_unit} unit (Rp ${formatRupiah(o.nominal_selisih)})</span></td>
        <td>${escapeHtml(o.alasan_selisih)}</td>
      </tr>
    `
    )
    .join("");
}

async function submitStockOpname() {
  const payload = {
    id_barang: parseInt(document.getElementById("opnameProductId").value) || 0,
    stok_fisik: parseInt(document.getElementById("opnamePhysicalInput").value) || 0,
    alasan_selisih: document.getElementById("opnameReasonInput").value.trim(),
  };
  const res = await fetch("/api/admin/opname/create", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    loadStockOpnameRecords();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal menyimpan stok opname", "error");
  }
}

async function loadAdminPromotions() {
  await loadAdminProducts();
  const res = await fetch("/api/admin/promotions");
  const data = await res.json();
  document.getElementById("promotionsTableBody").innerHTML = (data.promotions || [])
    .map(
      (p) => `
      <tr>
        <td><code>${escapeHtml(p.kode_promo)}</code></td>
        <td><strong>${escapeHtml(p.nama_promo)}</strong></td>
        <td>${escapeHtml(p.nama_barang)}</td>
        <td><span class="badge badge-blue">${escapeHtml(p.tipe_promo)} (Min: ${p.min_kuantitas})</span></td>
        <td>${
          p.tipe_promo === "Bundle_Pair"
            ? `Paket Rp ${formatRupiah(p.harga_paket)}`
            : p.tipe_promo === "Min_Qty_Cut"
            ? `Potongan Rp ${formatRupiah(p.potongan_rupiah)}`
            : `Diskon ${p.persen_diskon}%`
        }</td>
        <td>${escapeHtml(p.tanggal_mulai)} s/d ${escapeHtml(p.tanggal_akhir)}</td>
        <td>
          <button class="btn btn-sm ${p.status_aktif == 1 ? "btn-success" : "btn-danger"}" onclick="togglePromo(${p.id_promo})">
            ${p.status_aktif == 1 ? "Aktif" : "Nonaktif"}
          </button>
        </td>
      </tr>
    `
    )
    .join("");
}

async function submitNewPromo() {
  const payload = {
    nama_promo: document.getElementById("promoNameInput").value.trim(),
    id_barang: parseInt(document.getElementById("promoProductId").value) || 0,
    tipe_promo: document.getElementById("promoTypeSelect").value,
    min_kuantitas: parseInt(document.getElementById("promoMinQtyInput").value) || 2,
    harga_paket: parseFloat(document.getElementById("promoBundlePriceInput").value) || 0,
    potongan_rupiah: parseFloat(document.getElementById("promoCutInput").value) || 0,
    persen_diskon: parseFloat(document.getElementById("promoPercentInput").value) || 0,
    tanggal_mulai: document.getElementById("promoStartInput").value,
    tanggal_akhir: document.getElementById("promoEndInput").value,
  };
  const res = await fetch("/api/admin/promotions/create", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminPromotions();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal menyimpan promo", "error");
  }
}

async function togglePromo(idPromo) {
  await fetch("/api/admin/promotions/toggle", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_promo: idPromo }),
  });
  loadAdminPromotions();
}

async function loadAdminTransactionsAndShifts() {
  const res = await fetch("/api/admin/transactions");
  const data = await res.json();

  document.getElementById("transactionsTableBody").innerHTML = (data.transactions || [])
    .map(
      (t) => `
      <tr>
        <td><strong>${escapeHtml(t.no_struk)}</strong><br><small>${escapeHtml(t.tanggal_transaksi)}</small></td>
        <td>${escapeHtml(t.nama_kasir)} (${escapeHtml(t.shift_kasir)})</td>
        <td>${t.total_item} item</td>
        <td>Rp ${formatRupiah(t.total_harga)}</td>
        <td><span class="badge badge-blue">${escapeHtml(t.metode_pembayaran)}</span></td>
        <td><span class="badge ${t.status_transaksi === "Completed" ? "badge-green" : "badge-red"}">${escapeHtml(t.status_transaksi)}</span></td>
        <td>
          ${
            t.status_transaksi === "Completed"
              ? `<button class="btn btn-sm btn-danger" onclick="refundReceipt(${t.id_transaksi}, '${escapeHtml(t.no_struk)}')">Refund</button>`
              : "-"
          }
        </td>
      </tr>
    `
    )
    .join("");

  document.getElementById("voidLogsTableBody").innerHTML = (data.void_logs || [])
    .map(
      (v) => `
      <tr>
        <td><small>${escapeHtml(v.waktu_otorisasi)}</small></td>
        <td><span class="badge badge-yellow">${escapeHtml(v.jenis_void)}</span></td>
        <td>${escapeHtml(v.nama_kasir)}</td>
        <td><strong>${escapeHtml(v.nama_supervisor)}</strong></td>
        <td>Rp ${formatRupiah(v.nominal_terdampak)}</td>
        <td>${escapeHtml(v.alasan_void)}</td>
      </tr>
    `
    )
    .join("");

  const shiftRes = await fetch("/api/admin/shifts");
  const shiftData = await shiftRes.json();
  document.getElementById("shiftsTableBody").innerHTML = (shiftData.shifts || [])
    .map(
      (s) => `
      <tr>
        <td><strong>${escapeHtml(s.nama_kasir)}</strong> (${escapeHtml(s.shift)})</td>
        <td><small>${escapeHtml(s.waktu_buka)} - ${escapeHtml(s.waktu_tutup || "Aktif")}</small></td>
        <td>Rp ${formatRupiah(s.modal_awal)}</td>
        <td>Rp ${formatRupiah(s.penjualan_tunai_sistem)}</td>
        <td>${s.kas_fisik_akhir !== null ? `Rp ${formatRupiah(s.kas_fisik_akhir)}` : "-"}</td>
        <td>
          ${
            s.selisih_kas !== null
              ? `<span class="badge ${parseFloat(s.selisih_kas) < 0 ? "badge-red" : "badge-green"}">Rp ${formatRupiah(s.selisih_kas)}</span>`
              : '<span class="badge badge-blue">Open</span>'
          }
        </td>
        <td>${escapeHtml(s.catatan || "-")}</td>
      </tr>
    `
    )
    .join("");
}

async function refundReceipt(idTransaksi, noStruk) {
  const alasan = prompt(`Masukkan alasan Refund struk ${noStruk}:`, "Permintaan retur pelanggan disetujui supervisor");
  if (!alasan) return;
  const res = await fetch("/api/admin/transactions/refund", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_transaksi: idTransaksi, alasan }),
  });
  const data = await res.json();
  if (data.success) {
    loadAdminTransactionsAndShifts();
    showAdminToast(data.message);
  } else {
    showAdminToast(data.error || "Gagal memproses refund", "error");
  }
}

async function loadAuditAndBackups() {
  const auditRes = await fetch("/api/admin/audit-logs");
  const auditData = await auditRes.json();

  document.getElementById("auditLogsTableBody").innerHTML = (auditData.audit_logs || [])
    .map(
      (l) => `
      <tr>
        <td><small>${escapeHtml(l.waktu_kejadian)}</small></td>
        <td><strong>${escapeHtml(l.username_pelaku)}</strong> (${escapeHtml(l.peran_pelaku)})</td>
        <td><span class="badge badge-blue">${escapeHtml(l.aksi)}</span></td>
        <td><code>${escapeHtml(l.modul)} #${escapeHtml(l.id_entitas || "-")}</code></td>
        <td><small><code>${escapeHtml(l.data_lama || "-")}</code> → <code>${escapeHtml(l.data_baru || "-")}</code></small></td>
        <td><small>${escapeHtml(l.ip_address)}</small></td>
      </tr>
    `
    )
    .join("");

  const backupRes = await fetch("/api/admin/backups");
  const backupData = await backupRes.json();
  document.getElementById("backupsTableBody").innerHTML = (backupData.backups || [])
    .map(
      (b) => `
      <tr>
        <td><strong>${escapeHtml(b.nama_file)}</strong></td>
        <td>${(parseInt(b.ukuran_bytes || 0) / 1024).toFixed(2)} KB</td>
        <td><code>${escapeHtml(b.lokasi_simpan)}</code></td>
        <td><span class="badge badge-green">${escapeHtml(b.tipe_pemicu)}</span></td>
        <td>${escapeHtml(b.waktu_backup)}</td>
      </tr>
    `
    )
    .join("");
}

async function runDatabaseBackupNow() {
  const res = await fetch("/api/admin/backups/run", { method: "POST" });
  const data = await res.json();
  if (data.success) {
    loadAuditAndBackups();
    showAdminToast(`Backup .sql.gz berhasil dibuat: ${data.nama_file}`);
  } else {
    showAdminToast("Gagal menjalankan backup", "error");
  }
}

function formatRupiah(num) {
  return Number(num || 0).toLocaleString("id-ID", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

function escapeHtml(text) {
  if (text === null || text === undefined) return "";
  const div = document.createElement("div");
  div.textContent = String(text);
  return div.innerHTML;
}

function showAdminToast(message, type = "success") {
  const container = document.getElementById("toastContainer");
  if (!container) return;
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}
