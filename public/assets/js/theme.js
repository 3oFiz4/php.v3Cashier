// kelola pergantian tema tampilan gelap (dark) dan terang (light)
const ThemeManager = {
  // muat preferensi tema tersimpan dari localstorage
  init() {
    const saved = localStorage.getItem("ramstore_theme") || "light";
    this.apply(saved);
  },

  // terapkan atribut data-theme pada elemen dokumen utama
  apply(mode) {
    document.documentElement.setAttribute("data-theme", mode);
    localStorage.setItem("ramstore_theme", mode);
    const btn = document.getElementById("themeToggleBtn");
    if (btn) {
      btn.textContent = mode === "dark" ? "☀️ Light" : "🌙 Dark";
    }
  },

  // balikkan status tema aktif antara gelap dan terang
  toggle() {
    const current = document.documentElement.getAttribute("data-theme") || "light";
    this.apply(current === "dark" ? "light" : "dark");
  },
};

document.addEventListener("DOMContentLoaded", () => ThemeManager.init());
