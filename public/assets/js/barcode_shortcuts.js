// deteksi input cepat pemindai barcode usb/wireless dan pintasan tombol fungsi kasir
const BarcodeAndShortcuts = {
  buffer: "",
  lastKeyTime: 0,
  scannerThresholdMs: 45,

  // pasang pendengar event keydown global pada jendela terminal kasir
  init() {
    window.addEventListener("keydown", (e) => this.handleKeyDown(e));
  },

  // proses pintasan tombol f2, f4, f6, f8, f9, enter serta tangkap burst karakter barcode
  handleKeyDown(e) {
    if (e.key === "F2") {
      e.preventDefault();
      Cart.promptDirectQty();
      return;
    }
    if (e.key === "F4") {
      e.preventDefault();
      openParkCartModal();
      return;
    }
    if (e.key === "F6") {
      e.preventDefault();
      openShiftModal();
      return;
    }
    if (e.key === "F8") {
      e.preventDefault();
      openPaymentModal();
      return;
    }
    if (e.key === "F9") {
      e.preventDefault();
      Cart.requestClearCart();
      return;
    }

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
