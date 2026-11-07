// kelola state keranjang belanja multi-item, diskon bundling otomatis, dan otorisasi void
const Cart = {
  items: [],
  subtotalAwal: 0,
  totalDiskon: 0,
  totalHarga: 0,
  pendingVoidTarget: null,

  // tambahkan produk ke keranjang aktif dengan validasi ketersediaan stok
  async add(product, qtyToAdd = 1) {
    const id = parseInt(product.id_barang || product.id);
    const maxStock = parseInt(product.stok ?? product.stock ?? 0);
    const price = parseFloat(product.harga_jual ?? product.price ?? 0);
    const cost = parseFloat(product.harga_beli ?? 0);
    const name = product.nama_barang || product.name;

    const existing = this.items.find((i) => i.id_barang === id);

    if (existing) {
      if (existing.jumlah + qtyToAdd > maxStock) {
        showToast(`Stok maksimum "${name}" tercapai (${maxStock} unit)`, "error");
        return false;
      }
      existing.jumlah += qtyToAdd;
    } else {
      if (maxStock < qtyToAdd || maxStock <= 0) {
        showToast(`Stok "${name}" habis atau tidak mencukupi`, "error");
        return false;
      }
      this.items.push({
        id_barang: id,
        barcode: product.barcode || "",
        nama_barang: name,
        harga_beli: cost,
        harga_jual: price,
        jumlah: qtyToAdd,
        max_stock: maxStock,
        diskon_item: 0,
        label_promo: null,
        subtotal: price * qtyToAdd,
      });
    }

    await this.recalculateWithServer();
    showToast(`+${qtyToAdd} "${name}" masuk keranjang`);
    return true;
  },

  // minta otorisasi pin supervisor sebelum menghapus baris item dari keranjang aktif
  requestVoidItem(idBarang) {
    const item = this.items.find((i) => i.id_barang === idBarang);
    if (!item) return;

    this.pendingVoidTarget = {
      type: "Void_Cart_Item",
      id_barang: idBarang,
      nama_barang: item.nama_barang,
      nominal: item.subtotal,
    };
    openSupervisorPinModal(
      `Hapus item "${item.nama_barang}" (Rp ${formatRupiah(item.subtotal)}) dari keranjang`
    );
  },

  // minta otorisasi pin supervisor sebelum mengosongkan seluruh keranjang
  requestClearCart() {
    if (this.items.length === 0) return;
    this.pendingVoidTarget = {
      type: "Clear_Active_Cart",
      id_barang: null,
      nama_barang: "Seluruh Keranjang Aktif",
      nominal: this.totalHarga,
    };
    openSupervisorPinModal(
      `Kosongkan seluruh keranjang senilai Rp ${formatRupiah(this.totalHarga)}`
    );
  },

  // eksekusi penghapusan item setelah pin supervisor terverifikasi
  async executeAuthorizedVoid() {
    if (!this.pendingVoidTarget) return;
    if (this.pendingVoidTarget.type === "Clear_Active_Cart") {
      this.clear();
    } else if (this.pendingVoidTarget.id_barang) {
      this.items = this.items.filter(
        (i) => i.id_barang !== this.pendingVoidTarget.id_barang
      );
      await this.recalculateWithServer();
    }
    this.pendingVoidTarget = null;
  },

  // ubah kuantitas beli item di dalam keranjang
  async updateQty(idBarang, delta) {
    const item = this.items.find((i) => i.id_barang === idBarang);
    if (!item) return;

    const newQty = item.jumlah + delta;
    if (newQty <= 0) {
      this.requestVoidItem(idBarang);
      return;
    }
    if (newQty > item.max_stock) {
      showToast(`Melebihi sisa stok tersedia (${item.max_stock})`, "error");
      return;
    }

    item.jumlah = newQty;
    await this.recalculateWithServer();
  },

  // atur kuantitas item secara langsung melalui pintasan keyboard f2
  async promptDirectQty() {
    if (this.items.length === 0) {
      showToast("Keranjang masih kosong", "error");
      return;
    }
    const lastItem = this.items[this.items.length - 1];
    const input = prompt(
      `[F2] Masukkan jumlah baru untuk "${lastItem.nama_barang}" (Stok: ${lastItem.max_stock}):`,
      String(lastItem.jumlah)
    );
    if (input === null) return;
    const qty = parseInt(input);
    if (isNaN(qty) || qty <= 0) {
      this.requestVoidItem(lastItem.id_barang);
      return;
    }
    if (qty > lastItem.max_stock) {
      showToast(`Stok tidak mencukupi (Maks: ${lastItem.max_stock})`, "error");
      return;
    }
    lastItem.jumlah = qty;
    await this.recalculateWithServer();
  },

  // hitung ulang diskon promosi bundling dan total keranjang melalui mesin promo server
  async recalculateWithServer() {
    if (this.items.length === 0) {
      this.subtotalAwal = 0;
      this.totalDiskon = 0;
      this.totalHarga = 0;
      this.render();
      return;
    }

    try {
      const res = await fetch("/api/pos/calculate-cart", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ items: this.items }),
      });
      const data = await res.json();
      if (data.success && data.calculation) {
        const calc = data.calculation;
        this.subtotalAwal = calc.subtotal_awal;
        this.totalDiskon = calc.total_diskon;
        this.totalHarga = calc.total_harga;

        calc.items.forEach((evaluated) => {
          const match = this.items.find((i) => i.id_barang === evaluated.id_barang);
          if (match) {
            match.diskon_item = evaluated.diskon_item;
            match.label_promo = evaluated.label_promo;
            match.subtotal = evaluated.subtotal;
          }
        });
      }
    } catch (err) {
      this.subtotalAwal = this.items.reduce((s, i) => s + i.harga_jual * i.jumlah, 0);
      this.totalDiskon = 0;
      this.totalHarga = this.subtotalAwal;
    }

    this.render();
  },

  // ambil total kuantitas seluruh barang di keranjang
  getCount() {
    return this.items.reduce((sum, item) => sum + item.jumlah, 0);
  },

  // kosongkan state keranjang setelah pembayaran selesai atau ditunda
  clear() {
    this.items = [];
    this.subtotalAwal = 0;
    this.totalDiskon = 0;
    this.totalHarga = 0;
    this.render();
  },

  // perbarui tampilan daftar item keranjang dan total tagihan pada layar kasir
  render() {
    const cartItemsEl = document.getElementById("cartItems");
    const cartCountEl = document.getElementById("cartCount");
    const cartSubtotalEl = document.getElementById("cartSubtotal");
    const cartDiscountEl = document.getElementById("cartDiscount");
    const cartTotalEl = document.getElementById("cartTotal");
    const checkoutBtn = document.getElementById("checkoutBtn");

    cartCountEl.textContent = this.getCount();
    if (cartSubtotalEl) cartSubtotalEl.textContent = `Rp ${formatRupiah(this.subtotalAwal)}`;
    if (cartDiscountEl) cartDiscountEl.textContent = `-Rp ${formatRupiah(this.totalDiskon)}`;
    cartTotalEl.textContent = `Rp ${formatRupiah(this.totalHarga)}`;
    checkoutBtn.disabled = this.items.length === 0;

    if (this.items.length === 0) {
      cartItemsEl.innerHTML = `
        <div class="cart-empty">
          <span style="font-size: 2.5rem;">🛒</span>
          <span>Keranjang belanja kosong</span>
          <span style="font-size: 0.78rem;">Klik produk atau scan barcode untuk menambahkan</span>
        </div>
      `;
      return;
    }

    cartItemsEl.innerHTML = this.items
      .map(
        (item) => `
        <div class="cart-item">
          <div class="cart-item-info">
            <div class="cart-item-name">${escapeHtml(item.nama_barang)}</div>
            <div class="cart-item-price">Rp ${formatRupiah(item.harga_jual)} × ${item.jumlah}</div>
            ${
              item.diskon_item > 0
                ? `<div class="cart-item-promo">🏷️ ${escapeHtml(item.label_promo || "Promo")} (-Rp ${formatRupiah(item.diskon_item)})</div>`
                : ""
            }
          </div>
          <div class="cart-item-qty">
            <button onclick="Cart.updateQty(${item.id_barang}, -1)">−</button>
            <span>${item.jumlah}</span>
            <button onclick="Cart.updateQty(${item.id_barang}, 1)">+</button>
          </div>
          <div class="cart-item-subtotal">Rp ${formatRupiah(item.subtotal)}</div>
          <button class="btn btn-sm btn-danger" title="Void Item (Supervisor PIN)" onclick="Cart.requestVoidItem(${item.id_barang})">✕</button>
        </div>
      `
      )
      .join("");
  },
};
