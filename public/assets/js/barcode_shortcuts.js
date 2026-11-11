// deteksi input cepat pemindai barcode usb/wireless dan pintasan tombol fungsi kasir
function openCashierShortcutsModal() {
  const m = document.getElementById("cashierShortcutsModal");
  if (m) m.classList.add("active");
}

function closeCashierShortcutsModal() {
  const m = document.getElementById("cashierShortcutsModal");
  if (m) m.classList.remove("active");
}

const BarcodeAndShortcuts = {
  buffer: "",
  lastKeyTime: 0,
  scannerThresholdMs: 45,

  // pasang pendengar event keydown global pada jendela terminal kasir
  init() {
    window.addEventListener("keydown", (e) => this.handleKeyDown(e));
  },

  // proses pintasan tombol f1-f10, esc, alt, enter serta tangkap burst karakter barcode
  handleKeyDown(e) {
    // F1: Tampilkan / sembunyikan bantuan pintasan keyboard kasir
    if (e.key === "F1") {
      e.preventDefault();
      const m = document.getElementById("cashierShortcutsModal");
      if (m && m.classList.contains("active")) {
        closeCashierShortcutsModal();
      } else {
        openCashierShortcutsModal();
      }
      return;
    }

    // F2: Ubah kuantitas item terakhir / terpilih langsung
    if (e.key === "F2") {
      e.preventDefault();
      Cart.promptDirectQty();
      return;
    }

    // F3: Fokus cepat ke kotak pencarian nama/barcode barang
    if (e.key === "F3") {
      e.preventDefault();
      const sInput = document.getElementById("searchInput");
      if (sInput) {
        sInput.focus();
        sInput.select();
      }
      return;
    }

    // F4: Parkir / tunda transaksi keranjang belanja
    if (e.key === "F4") {
      e.preventDefault();
      openParkCartModal();
      return;
    }

    // F6: Rekonsiliasi modal kas awal dan hitung fisik shift
    if (e.key === "F6") {
      e.preventDefault();
      openShiftModal();
      return;
    }

    // F7: Buka riwayat transaksi kasir yang selesai
    if (e.key === "F7") {
      e.preventDefault();
      openCashierHistoryModal();
      return;
    }

    // F8: Buka dialog pembayaran multi-metode & QRIS
    if (e.key === "F8") {
      e.preventDefault();
      openPaymentModal();
      return;
    }

    // F9: Minta otorisasi supervisor untuk void seluruh item
    if (e.key === "F9") {
      e.preventDefault();
      Cart.requestClearCart();
      return;
    }

    // F10: Panggil daftar transaksi tertunda (held carts)
    if (e.key === "F10") {
      e.preventDefault();
      openHeldListModal();
      return;
    }

    // Escape: Tutup modal apapun yang sedang aktif
    if (e.key === "Escape") {
      if (typeof closePaymentModal === "function") closePaymentModal();
      if (typeof closeSupervisorModal === "function") closeSupervisorModal();
      if (typeof closeHeldModal === "function") closeHeldModal();
      if (typeof closeShiftModal === "function") closeShiftModal();
      if (typeof closeHistoryModal === "function") closeHistoryModal();
      if (typeof closeReceiptModal === "function") closeReceiptModal();
      closeCashierShortcutsModal();
      return;
    }

    // Alt + D: Toggle mode gelap / terang
    if (e.altKey && (e.key === "d" || e.key === "D")) {
      e.preventDefault();
      if (typeof ThemeManager !== "undefined") ThemeManager.toggle();
      return;
    }

    // Alt + A: Buka Panel Admin jika pengguna memiliki izin
    if (e.altKey && (e.key === "a" || e.key === "A")) {
      const adminLink = document.querySelector('a[href="/admin"]');
      if (adminLink) {
        e.preventDefault();
        window.location.href = "/admin";
        return;
      }
    }

    // Alt + B: Fokus ke input emulasi scanner barcode
    if (e.altKey && (e.key === "b" || e.key === "B")) {
      e.preventDefault();
      const bInput = document.getElementById("barcodeScannerInput");
      if (bInput) {
        bInput.focus();
        bInput.select();
      }
      return;
    }

    // Pintasan khusus saat modal pembayaran sedang aktif
    const paymentModal = document.getElementById("paymentModal");
    if (paymentModal && paymentModal.classList.contains("active")) {
      const activeElem = document.activeElement;
      const isTypingAmount = activeElem && (activeElem.id === "cashAmountInput" || activeElem.id === "nonCashAmountInput");
      const methodSelect = document.getElementById("paymentMethodSelect");

      if (e.key === "Enter" && !isTypingAmount) {
        const confirmBtn = document.getElementById("confirmPayBtn");
        if (confirmBtn && !confirmBtn.disabled) {
          e.preventDefault();
          confirmPayment();
          return;
        }
      }

      const methodShortcuts = {
        "1": "Cash",
        "2": "QRIS",
        "3": "Split",
        "4": "Debit",
        "5": "E-Wallet",
      };

      if ((e.altKey && methodShortcuts[e.key]) || (!isTypingAmount && !e.altKey && !e.ctrlKey && methodShortcuts[e.key])) {
        e.preventDefault();
        if (methodSelect) {
          methodSelect.value = methodShortcuts[e.key];
          methodSelect.dispatchEvent(new Event("change"));
        }
        return;
      }
    }

    // Enter saat struk terbuka: cetak struk
    const receiptModal = document.getElementById("receiptModal");
    if (e.key === "Enter" && receiptModal && receiptModal.classList.contains("active")) {
      e.preventDefault();
      printReceipt();
      return;
    }

    const activeTag = document.activeElement ? document.activeElement.tagName : "";
    if (activeTag === "INPUT" || activeTag === "TEXTAREA" || activeTag === "SELECT") {
      return;
    }

    const now = Date.now();
    if (now - this.lastKeyTime > this.scannerThresholdMs) {
      this.buffer = "";
    }
    this.lastKeyTime = now;

    if (e.key === "Enter" && this.buffer.length >= 6) {
      e.preventDefault();
      const scannedCode = this.buffer;
      this.buffer = "";
      triggerBarcodeLookup(scannedCode);
    } else if (e.key.length === 1 && /[0-9A-Za-z-]/.test(e.key)) {
      this.buffer += e.key;
    }
  },
};

document.addEventListener("DOMContentLoaded", () => BarcodeAndShortcuts.init());
