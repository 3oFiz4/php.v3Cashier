// kelola operasional layar utama kasir, pencarian, qris dinamis, shift, dan struk
let allProducts = [];
let allCategories = [];
let activeShiftData = null;

// inisialisasi komponen kasir saat halaman selesai dimuat
document.addEventListener("DOMContentLoaded", () => {
  loadPosCatalog();
  loadActiveShiftStatus();
  loadHeldCartsCount();
  startClock();
  setupPosListeners();
});

// daftarkan event listener pada input pencarian, filter, dan pembayaran
function setupPosListeners() {
  let searchTimeout;
  document.getElementById("searchInput").addEventListener("input", () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => filterProducts(), 200);
  });

  document.getElementById("categoryFilter").addEventListener("change", () => {
    filterProducts();
  });

  const barcodeInput = document.getElementById("barcodeScannerInput");
  if (barcodeInput) {
    barcodeInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        const code = barcodeInput.value.trim();
        if (code) {
          triggerBarcodeLookup(code);
          barcodeInput.value = "";
        }
      }
    });
  }

  document.getElementById("checkoutBtn").addEventListener("click", openPaymentModal);
  document.getElementById("cashAmountInput").addEventListener("input", recalculatePaymentSplit);
  document.getElementById("nonCashAmountInput").addEventListener("input", recalculatePaymentSplit);
  document.getElementById("paymentMethodSelect").addEventListener("change", handlePaymentMethodChange);
}

// perbarui penunjuk waktu real-time pada bilah navigasi
function startClock() {
  const clockEl = document.getElementById("clock");
  const update = () => {
    const now = new Date();
    clockEl.textContent = now.toLocaleString("id-ID", {
      weekday: "short",
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  };
  update();
  setInterval(update, 1000);
}

// muat data katalog produk, kategori, dan promosi aktif dari server
async function loadPosCatalog() {
  try {
    const res = await fetch("/api/pos/catalog");
    const data = await res.json();
    allProducts = data.products || [];
    allCategories = data.categories || [];

    const select = document.getElementById("categoryFilter");
    select.innerHTML = '<option value="all">Semua Kategori</option>';
    allCategories.forEach((cat) => {
      select.innerHTML += `<option value="${cat.id_jenis}">${escapeHtml(cat.nama_jenis)} (${cat.product_count})</option>`;
    });

    renderProducts(allProducts);
  } catch (err) {
    showToast("Gagal memuat katalog produk", "error");
  }
}

// cari dan tambahkan produk otomatis saat barcode dipindai
async function triggerBarcodeLookup(barcode) {
  const localMatch = allProducts.find((p) => p.barcode === barcode);
  if (localMatch) {
    await Cart.add(localMatch, 1);
    return;
  }

  try {
    const res = await fetch(`/api/pos/barcode/${encodeURIComponent(barcode)}`);
    const data = await res.json();
    if (data.success && data.product) {
      await Cart.add(data.product, 1);
    } else {
      showToast(data.error || `Barcode ${barcode} tidak ditemukan`, "error");
    }
  } catch (err) {
    showToast("Gagal memindai barcode", "error");
  }
}

// saring tampilan kartu produk berdasarkan kata kunci nama/barcode dan kategori
function filterProducts() {
  const search = document.getElementById("searchInput").value.toLowerCase().trim();
  const category = document.getElementById("categoryFilter").value;

  let filtered = allProducts;
  if (search) {
    filtered = filtered.filter(
      (p) =>
        p.nama_barang.toLowerCase().includes(search) ||
        (p.barcode && p.barcode.toLowerCase().includes(search))
    );
  }
  if (category !== "all") {
    filtered = filtered.filter((p) => String(p.id_jenis) === String(category));
  }
  renderProducts(filtered);
}

// tampilkan daftar produk ke dalam grid kartu kasir
function renderProducts(products) {
  const grid = document.getElementById("productsGrid");
  if (products.length === 0) {
    grid.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted);">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
        <div>Produk tidak ditemukan</div>
      </div>
    `;
    return;
  }

  grid.innerHTML = products
    .map((p) => {
      const stok = parseInt(p.stok);
      const minStok = parseInt(p.stok_minimum || 15);
      const isOut = stok <= 0;
      const isLow = stok > 0 && stok <= minStok;

      let stockClass = "";
      let stockText = `Stok: ${stok}`;
      if (isOut) {
        stockClass = "out";
        stockText = "Stok Habis";
      } else if (isLow) {
        stockClass = "low";
        stockText = `Sisa ${stok} (Low)`;
      }

      return `
        <div class="product-card ${isOut ? "out-of-stock" : ""}"
             onclick='Cart.add(${JSON.stringify({
               id_barang: p.id_barang,
               barcode: p.barcode,
               nama_barang: p.nama_barang,
               harga_beli: p.harga_beli,
               harga_jual: p.harga_jual,
               stok: p.stok,
             })})'>
          <div class="product-barcode">🏷️ ${escapeHtml(p.barcode)}</div>
          <div class="product-name">${escapeHtml(p.nama_barang)}</div>
          <div class="product-category">${escapeHtml(p.category_name)}</div>
          ${
            p.nearest_expiry
              ? `<div style="font-size:0.7rem;color:#d97706;">FEFO Exp: ${escapeHtml(p.nearest_expiry)}</div>`
              : ""
          }
          <div class="product-meta">
            <span class="product-price">Rp ${formatRupiah(p.harga_jual)}</span>
            <span class="product-stock ${stockClass}">${stockText}</span>
          </div>
        </div>
      `;
    })
    .join("");
}

// buka jendela pembayaran multi-metode dan qris dinamis (f8)
function openPaymentModal() {
  if (Cart.items.length === 0) return;

  const modal = document.getElementById("paymentModal");
  const summary = document.getElementById("paymentSummary");
  const total = Cart.totalHarga;

  summary.innerHTML =
    Cart.items
      .map(
        (item) => `
      <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.25rem;">
        <span>${escapeHtml(item.nama_barang)} × ${item.jumlah}</span>
        <span>Rp ${formatRupiah(item.subtotal)}</span>
      </div>
    `
      )
      .join("") +
    `
      <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:#059669;margin-top:0.35rem;">
        <span>Total Potongan Promo</span>
        <span>-Rp ${formatRupiah(Cart.totalDiskon)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1.1rem;border-top:1px solid var(--border);padding-top:0.5rem;margin-top:0.35rem;">
        <span>Total Tagihan</span>
        <span>Rp ${formatRupiah(total)}</span>
      </div>
    `;

  document.getElementById("paymentMethodSelect").value = "Cash";
  document.getElementById("cashAmountInput").value = total;
  document.getElementById("nonCashAmountInput").value = 0;
  document.getElementById("qrisContainer").style.display = "none";
  recalculatePaymentSplit();

  modal.classList.add("active");
  setTimeout(() => document.getElementById("cashAmountInput").focus(), 200);
}

// tutup jendela pembayaran
function closePaymentModal() {
  document.getElementById("paymentModal").classList.remove("active");
}

// sesuaikan input nominal tunai dan non-tunai berdasarkan metode pembayaran
async function handlePaymentMethodChange() {
  const method = document.getElementById("paymentMethodSelect").value;
  const total = Cart.totalHarga;
  const cashIn = document.getElementById("cashAmountInput");
  const nonCashIn = document.getElementById("nonCashAmountInput");
  const qrisBox = document.getElementById("qrisContainer");

  if (method === "Cash") {
    cashIn.value = total;
    nonCashIn.value = 0;
    qrisBox.style.display = "none";
  } else if (method === "QRIS" || method === "Debit" || method === "E-Wallet") {
    cashIn.value = 0;
    nonCashIn.value = total;
    if (method === "QRIS") {
      await requestDynamicQris(total);
    } else {
      qrisBox.style.display = "none";
    }
  } else if (method === "Split") {
    const halfCash = Math.min(50000, Math.floor(total / 2));
    cashIn.value = halfCash;
    nonCashIn.value = total - halfCash;
    await requestDynamicQris(total - halfCash);
  }
  recalculatePaymentSplit();
}

// panggil generator qris dinamis dari backend untuk nominal non-tunai
async function requestDynamicQris(amount) {
  const qrisBox = document.getElementById("qrisContainer");
  if (amount <= 0) {
    qrisBox.style.display = "none";
    return;
  }
  try {
    const res = await fetch("/api/pos/qris-generate", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ amount }),
    });
    const data = await res.json();
    if (data.success && data.qris) {
      document.getElementById("qrisRefInput").value = data.qris.reference_code;
      document.getElementById("qrisStringDisplay").textContent = data.qris.qris_string;
      document.getElementById("qrisAmountDisplay").textContent = `Rp ${formatRupiah(data.qris.amount)}`;
      qrisBox.style.display = "block";
    }
  } catch (err) {
    showToast("Gagal membuat kode QRIS dinamis", "error");
  }
}

// hitung total uang diterima dan kembalian secara otomatis
function recalculatePaymentSplit() {
  const cash = parseFloat(document.getElementById("cashAmountInput").value) || 0;
  const nonCash = parseFloat(document.getElementById("nonCashAmountInput").value) || 0;
  const totalPaid = cash + nonCash;
  const totalDue = Cart.totalHarga;
  const change = totalPaid - totalDue;

  const changeRow = document.getElementById("changeRow");
  const confirmBtn = document.getElementById("confirmPayBtn");

  if (totalPaid >= totalDue && totalDue > 0) {
    changeRow.style.display = "flex";
    document.getElementById("changeAmount").textContent = `Rp ${formatRupiah(change)}`;
    confirmBtn.disabled = false;
  } else {
    changeRow.style.display = "none";
    confirmBtn.disabled = true;
  }
}

// kirim transaksi pembayaran ke server dan tampilkan struk
async function confirmPayment() {
  const method = document.getElementById("paymentMethodSelect").value;
  const cash = parseFloat(document.getElementById("cashAmountInput").value) || 0;
  const nonCash = parseFloat(document.getElementById("nonCashAmountInput").value) || 0;
  const ref = document.getElementById("qrisRefInput").value.trim();

  const btn = document.getElementById("confirmPayBtn");
  btn.disabled = true;
  btn.textContent = "Memproses...";

  try {
    const res = await fetch("/api/pos/checkout", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        items: Cart.items,
        payment_method: method,
        cash_amount: cash,
        non_cash_amount: nonCash,
        payment_reference: ref,
      }),
    });
    const data = await res.json();
    if (data.success && data.transaction) {
      closePaymentModal();
      showReceipt(data.transaction);
      Cart.clear();
      loadPosCatalog();
      loadActiveShiftStatus();
      showToast("Transaksi berhasil disimpan!", "success");
    } else {
      showToast(data.error || "Gagal memproses transaksi", "error");
    }
  } catch (err) {
    showToast("Gangguan jaringan saat memproses transaksi", "error");
  } finally {
    btn.disabled = false;
    btn.textContent = "Konfirmasi Pembayaran";
  }
}

// render struk pembayaran ke dalam modal cetak thermal
function showReceipt(txn) {
  const content = document.getElementById("receiptContent");
  content.innerHTML = `
    <div class="receipt-header">
      <div style="font-size:1.05rem;font-weight:700;">🏪 RAMSTORE MINIMARKET</div>
      <div>Jl. Raya Denpasar No. 88, Bali</div>
      <div style="font-size:0.72rem;margin-top:0.2rem;">${escapeHtml(txn.tanggal_transaksi)}</div>
      <div style="font-size:0.75rem;font-weight:700;margin-top:0.2rem;">No: ${escapeHtml(txn.no_struk)}</div>
      <div style="font-size:0.72rem;">Kasir: ${escapeHtml(txn.nama_kasir || "Kasir")} (${escapeHtml(txn.shift_kasir || "-")})</div>
    </div>
    <div class="receipt-items">
      ${(txn.items || [])
        .map(
          (item) => `
        <div class="receipt-item">
          <span>${escapeHtml(item.nama_barang_snapshot)}</span>
        </div>
        <div class="receipt-item" style="color:#444;">
          <span>&nbsp;&nbsp;${item.jumlah} × Rp ${formatRupiah(item.harga_satuan)} ${
            parseFloat(item.diskon_item) > 0 ? `(-Rp ${formatRupiah(item.diskon_item)})` : ""
          }</span>
          <span>Rp ${formatRupiah(item.subtotal)}</span>
        </div>
      `
        )
        .join("")}
    </div>
    <div class="receipt-totals">
      <div class="receipt-item">
        <span>Subtotal Awal</span>
        <span>Rp ${formatRupiah(txn.subtotal_awal)}</span>
      </div>
      <div class="receipt-item">
        <span>Total Diskon Promo</span>
        <span>-Rp ${formatRupiah(txn.total_diskon)}</span>
      </div>
      <div class="receipt-item" style="font-weight:700;font-size:0.88rem;">
        <span>TOTAL AKHIR</span>
        <span>Rp ${formatRupiah(txn.total_harga)}</span>
      </div>
      <div class="receipt-item">
        <span>Metode (${escapeHtml(txn.metode_pembayaran)})</span>
        <span>Rp ${formatRupiah(txn.jumlah_bayar)}</span>
      </div>
      <div class="receipt-item">
        <span>&nbsp;&nbsp;Tunai / Non-Tunai</span>
        <span>Rp ${formatRupiah(txn.bayar_tunai)} / Rp ${formatRupiah(txn.bayar_non_tunai)}</span>
      </div>
      <div class="receipt-item" style="font-weight:700;">
        <span>KEMBALIAN</span>
        <span>Rp ${formatRupiah(txn.kembalian)}</span>
      </div>
    </div>
    <div class="receipt-footer">
      <div>Total Item: ${txn.total_item}</div>
      <div style="margin-top:0.35rem;">Terima kasih telah berbelanja di Ramstore!</div>
    </div>
  `;
  document.getElementById("receiptModal").classList.add("active");
}

function closeReceiptModal() {
  document.getElementById("receiptModal").classList.remove("active");
}

function printReceipt() {
  window.print();
}

function openParkCartModal() {
  if (Cart.items.length === 0) {
    openHeldListModal();
    return;
  }
  const label = prompt("Masukkan nama/catatan pelanggan untuk menunda keranjang ini:", "Pelanggan Lupa Dompet");
  if (!label) return;
  parkCurrentCart(label);
}

async function parkCurrentCart(label) {
  try {
    const res = await fetch("/api/pos/held/park", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ items: Cart.items, customer_label: label }),
    });
    const data = await res.json();
    if (data.success) {
      Cart.clear();
      loadHeldCartsCount();
      showToast(`Keranjang ditunda (${data.kode_tunda})`, "success");
    } else {
      showToast(data.error || "Gagal menunda keranjang", "error");
    }
  } catch (err) {
    showToast("Gagal menunda keranjang", "error");
  }
}

async function loadHeldCartsCount() {
  try {
    const res = await fetch("/api/pos/held");
    const data = await res.json();
    const list = data.held_carts || [];
    const badge = document.getElementById("heldCountBadge");
    if (badge) badge.textContent = list.length;
    return list;
  } catch (err) {
    return [];
  }
}

async function openHeldListModal() {
  const list = await loadHeldCartsCount();
  const tbody = document.getElementById("heldCartsTableBody");
  if (list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Tidak ada transaksi yang ditunda</td></tr>';
  } else {
    tbody.innerHTML = list
      .map(
        (h) => `
      <tr>
        <td><strong>${escapeHtml(h.kode_tunda)}</strong><br><small>${escapeHtml(h.waktu_tunda)}</small></td>
        <td>${escapeHtml(h.label_pelanggan)}</td>
        <td>Rp ${formatRupiah(h.subtotal_sementara)}</td>
        <td>
          <button class="btn btn-sm btn-dark" onclick="resumeHeldCart(${h.id_tunda})">Panggil</button>
          <button class="btn btn-sm btn-danger" onclick="discardHeldCart(${h.id_tunda})">Hapus</button>
        </td>
      </tr>
    `
      )
      .join("");
  }
  document.getElementById("heldModal").classList.add("active");
}

function closeHeldModal() {
  document.getElementById("heldModal").classList.remove("active");
}

async function resumeHeldCart(idTunda) {
  try {
    const res = await fetch(`/api/pos/held/resume/${idTunda}`, { method: "POST" });
    const data = await res.json();
    if (data.success) {
      Cart.items = data.items || [];
      await Cart.recalculateWithServer();
      closeHeldModal();
      loadHeldCartsCount();
      showToast(`Keranjang "${data.label}" dipulihkan`, "success");
    }
  } catch (err) {
    showToast("Gagal memulihkan keranjang", "error");
  }
}

async function discardHeldCart(idTunda) {
  await fetch(`/api/pos/held/discard/${idTunda}`, { method: "POST" });
  openHeldListModal();
}

function openSupervisorPinModal(reasonText) {
  document.getElementById("voidReasonDisplay").textContent = reasonText;
  document.getElementById("supervisorPinInput").value = "";
  document.getElementById("supervisorModal").classList.add("active");
}

function closeSupervisorModal() {
  document.getElementById("supervisorModal").classList.remove("active");
}

async function submitSupervisorPin() {
  const supervisorUser = document.getElementById("supervisorUsernameInput").value.trim() || "admin";
  const pin = document.getElementById("supervisorPinInput").value.trim();
  const reason = document.getElementById("voidReasonDisplay").textContent;
  const target = Cart.pendingVoidTarget || {};

  try {
    const res = await fetch("/api/auth/verify-supervisor", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        supervisor_username: supervisorUser,
        pin: pin,
        jenis_void: target.type || "Void_Cart_Item",
        id_barang: target.id_barang || null,
        nominal: target.nominal || 0,
        alasan: reason,
      }),
    });
    const data = await res.json();
    if (data.success && data.authorized) {
      closeSupervisorModal();
      await Cart.executeAuthorizedVoid();
      showToast(`Void disetujui oleh ${data.supervisor}`, "success");
    } else {
      showToast(data.error || "PIN Supervisor salah", "error");
    }
  } catch (err) {
    showToast("Gagal memverifikasi PIN Supervisor", "error");
  }
}

async function loadActiveShiftStatus() {
  try {
    const res = await fetch("/api/pos/shift/active");
    const data = await res.json();
    activeShiftData = data.shift || null;
    const badge = document.getElementById("shiftStatusBadge");
    if (badge) {
      if (activeShiftData) {
        badge.textContent = `Shift ${activeShiftData.shift}: Aktif`;
        badge.className = "badge badge-green";
      } else {
        badge.textContent = "Shift: Belum Dibuka";
        badge.className = "badge badge-yellow";
      }
    }
  } catch (err) {}
}

function openShiftModal() {
  const body = document.getElementById("shiftModalBody");
  if (!activeShiftData) {
    body.innerHTML = `
      <div class="form-group">
        <label class="form-label">Pilih Shift Kerja</label>
        <select id="openShiftSelect" class="form-input">
          <option value="Morning">Morning (Pagi)</option>
          <option value="Afternoon">Afternoon (Siang)</option>
          <option value="Night">Night (Malam)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Modal Kas Awal Laci (Rp)</label>
        <input type="number" id="openShiftCashInput" class="form-input" value="500000" min="0" />
      </div>
      <button class="btn btn-dark" style="width:100%;" onclick="submitOpenShift()">Buka Shift Sekarang</button>
    `;
  } else {
    const modalAwal = parseFloat(activeShiftData.modal_awal);
    const tunaiSistem = parseFloat(activeShiftData.penjualan_tunai_sistem);
    const nonTunaiSistem = parseFloat(activeShiftData.penjualan_non_tunai_sistem);
    const expectedCash = modalAwal + tunaiSistem;

    body.innerHTML = `
      <div style="background:var(--accent);padding:1rem;border-radius:10px;margin-bottom:1rem;font-size:0.85rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;">
          <span>Shift Aktif:</span><strong>${escapeHtml(activeShiftData.shift)} (Sejak ${escapeHtml(activeShiftData.waktu_buka)})</strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;">
          <span>Modal Awal Laci:</span><strong>Rp ${formatRupiah(modalAwal)}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;">
          <span>Penjualan Tunai Sistem:</span><strong>Rp ${formatRupiah(tunaiSistem)}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;">
          <span>Penjualan Non-Tunai (QRIS/Debit):</span><strong>Rp ${formatRupiah(nonTunaiSistem)}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:0.4rem;font-size:0.95rem;">
          <span>Total Kas Seharusnya di Laci:</span><strong>Rp ${formatRupiah(expectedCash)}</strong>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Hasil Hitung Fisik Uang Tunai di Laci (Rp)</label>
        <input type="number" id="closeShiftPhysicalInput" class="form-input" value="${expectedCash}" />
      </div>
      <div class="form-group">
        <label class="form-label">Catatan Rekonsiliasi Shift</label>
        <input type="text" id="closeShiftNotesInput" class="form-input" placeholder="Contoh: Sesuai / Selisih uang kembalian" />
      </div>
      <button class="btn btn-danger" style="width:100%;" onclick="submitCloseShift(${activeShiftData.id_rekon})">Tutup Shift & Simpan Rekonsiliasi</button>
    `;
  }
  document.getElementById("shiftModal").classList.add("active");
}

function closeShiftModal() {
  document.getElementById("shiftModal").classList.remove("active");
}

async function submitOpenShift() {
  const shift = document.getElementById("openShiftSelect").value;
  const startingCash = parseFloat(document.getElementById("openShiftCashInput").value) || 0;
  const res = await fetch("/api/pos/shift/open", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ shift, starting_cash: startingCash }),
  });
  const data = await res.json();
  if (data.success) {
    closeShiftModal();
    loadActiveShiftStatus();
    showToast("Shift berhasil dibuka", "success");
  } else {
    showToast(data.error || "Gagal membuka shift", "error");
  }
}

async function submitCloseShift(idRekon) {
  const physicalCash = parseFloat(document.getElementById("closeShiftPhysicalInput").value) || 0;
  const notes = document.getElementById("closeShiftNotesInput").value.trim();
  const res = await fetch("/api/pos/shift/close", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_rekon: idRekon, physical_cash: physicalCash, notes }),
  });
  const data = await res.json();
  if (data.success) {
    closeShiftModal();
    loadActiveShiftStatus();
    showToast(`Shift ditutup. Selisih kas: Rp ${formatRupiah(data.discrepancy)}`, "success");
  } else {
    showToast(data.error || "Gagal menutup shift", "error");
  }
}

async function openCashierHistoryModal() {
  const res = await fetch("/api/pos/history");
  const data = await res.json();
  const list = data.transactions || [];
  const tbody = document.getElementById("cashierHistoryBody");

  tbody.innerHTML =
    list.length === 0
      ? '<tr><td colspan="5" style="text-align:center;">Belum ada riwayat transaksi</td></tr>'
      : list
          .map(
            (t) => `
        <tr>
          <td><strong>${escapeHtml(t.no_struk)}</strong><br><small>${escapeHtml(t.tanggal_transaksi)}</small></td>
          <td>${escapeHtml(t.metode_pembayaran)}</td>
          <td>Rp ${formatRupiah(t.total_harga)}</td>
          <td><span class="badge ${t.status_transaksi === "Completed" ? "badge-green" : "badge-red"}">${escapeHtml(t.status_transaksi)}</span></td>
          <td><button class="btn btn-sm" onclick="reprintReceipt(${t.id_transaksi})">🖨️ Struk</button></td>
        </tr>
      `
          )
          .join("");
  document.getElementById("historyModal").classList.add("active");
}

function closeHistoryModal() {
  document.getElementById("historyModal").classList.remove("active");
}

async function reprintReceipt(idTransaksi) {
  const res = await fetch(`/api/pos/receipt/${idTransaksi}`);
  const data = await res.json();
  if (data.success && data.transaction) {
    closeHistoryModal();
    showReceipt(data.transaction);
  }
}

async function logoutUser() {
  await fetch("/api/auth/logout", { method: "POST" });
  window.location.href = "/login";
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

function showToast(message, type = "success") {
  const container = document.getElementById("toastContainer");
  if (!container) return;
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}
