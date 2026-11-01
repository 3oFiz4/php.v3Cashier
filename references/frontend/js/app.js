/**
 * Main Cashier Application
 * Handles product display, search, filter, and checkout
 */
const API_BASE = "/v2cashier/backend/php";

let allProducts = [];
let allCategories = [];

// Initialization

document.addEventListener("DOMContentLoaded", () => {
  loadCategories();
  loadProducts();
  startClock();
  setupEventListeners();
});

function setupEventListeners() {
  // Search input with debounce
  let searchTimeout;
  document.getElementById("searchInput").addEventListener("input", (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => filterProducts(), 300);
  });

  // Category filter
  document.getElementById("categoryFilter").addEventListener("change", () => {
    filterProducts();
  });

  // Checkout button
  document
    .getElementById("checkoutBtn")
    .addEventListener("click", openPaymentModal);

  // Payment input
  document
    .getElementById("paymentInput")
    .addEventListener("input", updateChange);
}

// Clock

function startClock() {
  const clockEl = document.getElementById("clock");
  const update = () => {
    const now = new Date();
    clockEl.textContent = now.toLocaleString("en-US", {
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

// Data Loading

async function loadCategories() {
  try {
    const res = await fetch(`${API_BASE}/categories.php?action=list`);
    const data = await res.json();
    allCategories = data.categories || [];

    const select = document.getElementById("categoryFilter");
    select.innerHTML = '<option value="all">All Categories</option>';
    allCategories.forEach((cat) => {
      select.innerHTML += `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`;
    });
  } catch (err) {
    console.error("Error loading categories:", err);
  }
}

async function loadProducts() {
  try {
    const res = await fetch(`${API_BASE}/products.php?action=list`);
    const data = await res.json();
    allProducts = data.products || [];
    renderProducts(allProducts);
  } catch (err) {
    console.error("Error loading products:", err);
    showToast("Failed to load products", "error");
  }
}

// Filter & Render

function filterProducts() {
  const search = document
    .getElementById("searchInput")
    .value.toLowerCase()
    .trim();
  const category = document.getElementById("categoryFilter").value;

  let filtered = allProducts;

  if (search) {
    filtered = filtered.filter((p) => p.name.toLowerCase().includes(search));
  }

  if (category !== "all") {
    filtered = filtered.filter((p) => p.category_id == category);
  }

  renderProducts(filtered);
}

function renderProducts(products) {
  const grid = document.getElementById("productsGrid");

  if (products.length === 0) {
    grid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #9CA3AF;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">🔍</div>
                <div>No products found</div>
            </div>
        `;
    return;
  }

  // Map categories to emojis for visual appeal
  const categoryEmoji = {
    Beverages: "🥤",
    Snacks: "🍿",
    Dairy: "🧀",
    Bakery: "🍞",
    Fruits: "🍎",
    Household: "🧹",
    "Personal Care": "🧴",
    "Canned Goods": "🥫",
  };

  grid.innerHTML = products
    .map((product) => {
      const isOut = product.stock <= 0;
      const isLow = product.stock > 0 && product.stock <= 5;
      const emoji = categoryEmoji[product.category_name] || "📦";

      let stockClass = "";
      let stockText = `${product.stock} in stock`;
      if (isOut) {
        stockClass = "out";
        stockText = "Out of stock";
      } else if (isLow) {
        stockClass = "low";
        stockText = `${product.stock} left`;
      }

      return `
            <div class="product-card ${isOut ? "out-of-stock" : ""}" 
                 onclick='Cart.add(${JSON.stringify({
                   id: product.id,
                   name: product.name,
                   price: product.price,
                   stock: product.stock,
                 })})'>
                <div class="product-name">${escapeHtml(product.name)}</div>
                <div class="product-category">${escapeHtml(product.category_name)}</div>
                <div class="product-meta">
                    <span class="product-price">$${parseFloat(product.price).toFixed(2)}</span>
                    <span class="product-stock ${stockClass}">${stockText}</span>
                </div>
            </div>
        `;
    })
    .join("");
}

// Payment Modal

function openPaymentModal() {
  if (Cart.items.length === 0) return;

  const modal = document.getElementById("paymentModal");
  const summary = document.getElementById("paymentSummary");
  const total = Cart.getTotal();

  summary.innerHTML =
    Cart.items
      .map(
        (item) => `
        <div class="payment-row">
            <span>${escapeHtml(item.name)} × ${item.quantity}</span>
            <span>$${(item.price * item.quantity).toFixed(2)}</span>
        </div>
    `,
      )
      .join("") +
    `
        <div class="payment-row total">
            <span>Total</span>
            <span>$${total.toFixed(2)}</span>
        </div>
    `;

  document.getElementById("paymentInput").value = "";
  document.getElementById("changeRow").style.display = "none";
  document.getElementById("confirmPayBtn").disabled = true;

  modal.classList.add("active");
  setTimeout(() => document.getElementById("paymentInput").focus(), 300);
}

function closePaymentModal() {
  document.getElementById("paymentModal").classList.remove("active");
}

function updateChange() {
  const payment =
    parseFloat(document.getElementById("paymentInput").value) || 0;
  const total = Cart.getTotal();
  const change = payment - total;
  const changeRow = document.getElementById("changeRow");
  const confirmBtn = document.getElementById("confirmPayBtn");

  if (payment >= total && payment > 0) {
    changeRow.style.display = "flex";
    document.getElementById("changeAmount").textContent =
      `$${change.toFixed(2)}`;
    confirmBtn.disabled = false;
  } else {
    changeRow.style.display = "none";
    confirmBtn.disabled = true;
  }
}

// Checkout

async function confirmPayment() {
  const payment =
    parseFloat(document.getElementById("paymentInput").value) || 0;
  const total = Cart.getTotal();

  if (payment < total) {
    showToast("Insufficient payment amount", "error");
    return;
  }

  const payload = {
    items: Cart.items.map((item) => ({
      product_id: item.product_id,
      quantity: item.quantity,
    })),
    payment_amount: payment,
  };

  try {
    document.getElementById("confirmPayBtn").disabled = true;
    document.getElementById("confirmPayBtn").textContent = "Processing...";

    const res = await fetch(`${API_BASE}/transactions.php?action=checkout`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const data = await res.json();

    if (data.success) {
      closePaymentModal();
      showReceipt(data.transaction);
      Cart.clear();
      loadProducts(); // Refresh stock
      showToast("Payment successful!", "success");
    } else {
      showToast(data.error || "Checkout failed", "error");
    }
  } catch (err) {
    console.error("Checkout error:", err);
    showToast("Checkout failed. Please try again.", "error");
  } finally {
    document.getElementById("confirmPayBtn").disabled = false;
    document.getElementById("confirmPayBtn").textContent = "Confirm Payment";
  }
}

// Receipt

function showReceipt(transaction) {
  const content = document.getElementById("receiptContent");
  const date = new Date(transaction.created_at);

  content.innerHTML = `
        <div class="receipt-header">
            <div class="receipt-store">🏪 POS CASHIER</div>
            <div>Mini Marketplace</div>
            <div style="font-size: 0.7rem; margin-top: 0.25rem;">
                ${date.toLocaleString()}
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; margin-top: 0.25rem;">
                ${transaction.transaction_code}
            </div>
        </div>

        <div class="receipt-items">
            ${transaction.items
              .map(
                (item) => `
                <div class="receipt-item">
                    <span>${escapeHtml(item.product_name)}</span>
                    <span></span>
                </div>
                <div class="receipt-item" style="color: #666;">
                    <span>&nbsp;&nbsp;${item.quantity} × $${parseFloat(item.product_price).toFixed(2)}</span>
                    <span>$${parseFloat(item.subtotal).toFixed(2)}</span>
                </div>
            `,
              )
              .join("")}
        </div>

        <div class="receipt-totals">
            <div class="receipt-item" style="font-weight: 700;">
                <span>TOTAL</span>
                <span>$${parseFloat(transaction.total_amount).toFixed(2)}</span>
            </div>
            <div class="receipt-item">
                <span>Payment</span>
                <span>$${parseFloat(transaction.payment_amount).toFixed(2)}</span>
            </div>
            <div class="receipt-item" style="font-weight: 700;">
                <span>Change</span>
                <span>$${parseFloat(transaction.change_amount).toFixed(2)}</span>
            </div>
        </div>

        <div class="receipt-footer">
            <div>Items: ${transaction.total_items}</div>
            <div style="margin-top: 0.5rem;">Thank you for shopping!</div>
            <div style="font-size: 0.7rem;">Have a nice day 😊</div>
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

// Utilities

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function showToast(message, type = "success") {
  const container = document.getElementById("toastContainer");
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateX(100px)";
    toast.style.transition = "all 0.3s ease";
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
