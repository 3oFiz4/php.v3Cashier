/**
 * Admin Dashboard
 * Handles dashboard, products CRUD, transactions, categories
 */

const API_BASE = "/v2cashier/backend/php";

let adminCategories = [];

// Auth Check & Init

document.addEventListener("DOMContentLoaded", async () => {
  try {
    const res = await fetch(`${API_BASE}/auth.php?action=check`);
    const data = await res.json();

    if (!data.authenticated) {
      window.location.href = "login.html";
      return;
    }

    document.getElementById("adminName").textContent =
      `Welcome, ${data.user.full_name}`;
  } catch (err) {
    window.location.href = "login.html";
    return;
  }

  setupAdminEvents();
  loadDashboard();
  loadAdminCategories();
});

function setupAdminEvents() {
  // Tab navigation
  document.querySelectorAll(".sidebar-link").forEach((link) => {
    link.addEventListener("click", () => {
      const tab = link.dataset.tab;

      document
        .querySelectorAll(".sidebar-link")
        .forEach((l) => l.classList.remove("active"));
      link.classList.add("active");

      document
        .querySelectorAll(".admin-tab")
        .forEach((t) => t.classList.remove("active"));
      document.getElementById(`tab-${tab}`).classList.add("active");

      // Load tab data
      if (tab === "dashboard") loadDashboard();
      if (tab === "products") loadAdminProducts();
      if (tab === "transactions") loadAdminTransactions();
      if (tab === "categories") loadAdminCategoriesTable();
    });
  });

  // Logout
  document.getElementById("logoutBtn").addEventListener("click", async () => {
    await fetch(`${API_BASE}/auth.php?action=logout`);
    window.location.href = "login.html";
  });

  // Add Product
  document
    .getElementById("addProductBtn")
    .addEventListener("click", () => openProductModal());

  // Save Product
  document
    .getElementById("saveProductBtn")
    .addEventListener("click", saveProduct);

  // Add Category
  document.getElementById("addCategoryBtn").addEventListener("click", () => {
    document.getElementById("categoryName").value = "";
    document.getElementById("categoryModal").classList.add("active");
  });

  // Save Category
  document
    .getElementById("saveCategoryBtn")
    .addEventListener("click", saveCategory);
}

// Dashboard

async function loadDashboard() {
  try {
    // Load summary
    const summaryRes = await fetch(`${API_BASE}/dashboard.php?action=summary`);
    const summaryData = await summaryRes.json();
    renderStats(summaryData.summary);

    // Load recent transactions
    const recentRes = await fetch(`${API_BASE}/dashboard.php?action=recent`);
    const recentData = await recentRes.json();
    renderRecentTransactions(recentData.transactions);

    // Load bestsellers
    const bestRes = await fetch(`${API_BASE}/dashboard.php?action=bestsellers`);
    const bestData = await bestRes.json();
    renderBestsellers(bestData.bestsellers);

    // Load inventory
    const invRes = await fetch(`${API_BASE}/dashboard.php?action=inventory`);
    const invData = await invRes.json();
    renderInventory(invData.inventory);
  } catch (err) {
    console.error("Dashboard load error:", err);
    showAdminToast("Failed to load dashboard", "error");
  }
}

function renderStats(s) {
  document.getElementById("statsGrid").innerHTML = `
        <div class="stat-card">
            <div class="stat-value">$${s.today_revenue.toFixed(2)}</div>
            <div class="stat-label">Today's Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$${s.total_revenue.toFixed(2)}</div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${s.total_products}</div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${s.total_transactions}</div>
            <div class="stat-label">Total Transactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${s.total_items_sold}</div>
            <div class="stat-label">Items Sold</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${s.low_stock}</div>
            <div class="stat-label">Low Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${s.out_of_stock}</div>
            <div class="stat-label">Out of Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">${s.today_transactions}</div>
            <div class="stat-label">Today's Transactions</div>
        </div>
    `;
}

function renderRecentTransactions(txns) {
  document.getElementById("recentTxnBody").innerHTML =
    txns.length === 0
      ? '<tr><td colspan="4" style="text-align:center;color:#9CA3AF;">No transactions</td></tr>'
      : txns
          .map(
            (t) => `
            <tr>
                <td style="font-weight:500; font-size:0.8rem;">${escapeHtml(t.transaction_code)}</td>
                <td>$${parseFloat(t.total_amount).toFixed(2)}</td>
                <td>${t.total_items}</td>
                <td style="font-size:0.8rem;color:#6B7280;">${formatDate(t.created_at)}</td>
            </tr>
        `,
          )
          .join("");
}

function renderBestsellers(items) {
  document.getElementById("bestsellersBody").innerHTML =
    items.length === 0
      ? '<tr><td colspan="3" style="text-align:center;color:#9CA3AF;">No data</td></tr>'
      : items
          .map(
            (item) => `
            <tr>
                <td style="font-weight:500;">${escapeHtml(item.product_name)}</td>
                <td>${item.total_sold}</td>
                <td>$${parseFloat(item.total_revenue).toFixed(2)}</td>
            </tr>
        `,
          )
          .join("");
}

function renderInventory(products) {
  document.getElementById("inventoryBody").innerHTML = products
    .map((p) => {
      let badge = '<span class="badge badge-green">In Stock</span>';
      if (p.stock <= 0)
        badge = '<span class="badge badge-red">Out of Stock</span>';
      else if (p.stock <= 5)
        badge = '<span class="badge badge-yellow">Low Stock</span>';

      return `
            <tr>
                <td style="font-weight:500;">${escapeHtml(p.name)}</td>
                <td>${escapeHtml(p.category_name)}</td>
                <td>$${parseFloat(p.price).toFixed(2)}</td>
                <td>${p.stock}</td>
                <td>${badge}</td>
            </tr>
        `;
    })
    .join("");
}

// Products Management

async function loadAdminProducts() {
  try {
    const res = await fetch(`${API_BASE}/products.php?action=list`);
    const data = await res.json();
    renderProductsTable(data.products || []);
  } catch (err) {
    showAdminToast("Failed to load products", "error");
  }
}

function renderProductsTable(products) {
  document.getElementById("productsTableBody").innerHTML =
    products.length === 0
      ? '<tr><td colspan="7" style="text-align:center;color:#9CA3AF;">No products</td></tr>'
      : products
          .map(
            (p) => `
            <tr>
                <td>${p.id}</td>
                <td style="font-weight:500;">${escapeHtml(p.name)}</td>
                <td>${escapeHtml(p.category_name)}</td>
                <td>$${parseFloat(p.price).toFixed(2)}</td>
                <td>${p.stock}</td>
                <td style="font-size:0.8rem;color:#6B7280;">${formatDate(p.created_at)}</td>
                <td>
                    <div style="display:flex;gap:0.5rem;">
                        <button class="btn btn-sm" onclick='openProductModal(${JSON.stringify(p)})'>Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteProduct(${p.id}, '${escapeHtml(p.name)}')">Delete</button>
                    </div>
                </td>
            </tr>
        `,
          )
          .join("");
}

async function loadAdminCategories() {
  try {
    const res = await fetch(`${API_BASE}/categories.php?action=list`);
    const data = await res.json();
    adminCategories = data.categories || [];

    const select = document.getElementById("productCategory");
    select.innerHTML = '<option value="">Select category</option>';
    adminCategories.forEach((cat) => {
      select.innerHTML += `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`;
    });
  } catch (err) {
    console.error("Error loading categories:", err);
  }
}

function openProductModal(product = null) {
  const modal = document.getElementById("productModal");
  const title = document.getElementById("productModalTitle");

  if (product) {
    title.textContent = "Edit Product";
    document.getElementById("productId").value = product.id;
    document.getElementById("productName").value = product.name;
    document.getElementById("productCategory").value = product.category_id;
    document.getElementById("productPrice").value = product.price;
    document.getElementById("productStock").value = product.stock;
    document.getElementById("productImage").value = product.image || "";
  } else {
    title.textContent = "Add Product";
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
    name: document.getElementById("productName").value.trim(),
    category_id: document.getElementById("productCategory").value,
    price: parseFloat(document.getElementById("productPrice").value) || 0,
    stock: parseInt(document.getElementById("productStock").value) || 0,
    image: document.getElementById("productImage").value.trim(),
  };

  if (!payload.name || !payload.category_id) {
    showAdminToast("Please fill in required fields", "error");
    return;
  }

  const action = id ? "update" : "create";
  if (id) payload.id = parseInt(id);

  try {
    const res = await fetch(`${API_BASE}/products.php?action=${action}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const data = await res.json();

    if (data.success) {
      closeProductModal();
      loadAdminProducts();
      showAdminToast(data.message || "Product saved!", "success");
    } else {
      showAdminToast(data.error || "Failed to save", "error");
    }
  } catch (err) {
    showAdminToast("Error saving product", "error");
  }
}

async function deleteProduct(id, name) {
  if (!confirm(`Delete "${name}"? This action cannot be undone.`)) return;

  try {
    const res = await fetch(`${API_BASE}/products.php?action=delete`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });

    const data = await res.json();

    if (data.success) {
      loadAdminProducts();
      showAdminToast("Product deleted", "success");
    } else {
      showAdminToast(data.error || "Failed to delete", "error");
    }
  } catch (err) {
    showAdminToast("Error deleting product", "error");
  }
}

// Transactions

async function loadAdminTransactions() {
  try {
    const res = await fetch(`${API_BASE}/transactions.php?action=list`);
    const data = await res.json();
    const txns = data.transactions || [];

    document.getElementById("transactionsTableBody").innerHTML =
      txns.length === 0
        ? '<tr><td colspan="6" style="text-align:center;color:#9CA3AF;">No transactions</td></tr>'
        : txns
            .map(
              (t) => `
                <tr>
                    <td style="font-weight:500; font-size:0.85rem;">${escapeHtml(t.transaction_code)}</td>
                    <td style="font-weight:600;">$${parseFloat(t.total_amount).toFixed(2)}</td>
                    <td>${t.total_items}</td>
                    <td>$${parseFloat(t.payment_amount).toFixed(2)}</td>
                    <td>$${parseFloat(t.change_amount).toFixed(2)}</td>
                    <td style="font-size:0.8rem;color:#6B7280;">${formatDate(t.created_at)}</td>
                </tr>
            `,
            )
            .join("");
  } catch (err) {
    showAdminToast("Failed to load transactions", "error");
  }
}

// Categories

async function loadAdminCategoriesTable() {
  try {
    const res = await fetch(`${API_BASE}/categories.php?action=list`);
    const data = await res.json();
    const cats = data.categories || [];

    document.getElementById("categoriesTableBody").innerHTML =
      cats.length === 0
        ? '<tr><td colspan="4" style="text-align:center;color:#9CA3AF;">No categories</td></tr>'
        : cats
            .map(
              (c) => `
                <tr>
                    <td>${c.id}</td>
                    <td style="font-weight:500;">${escapeHtml(c.name)}</td>
                    <td>${c.product_count}</td>
                    <td style="font-size:0.8rem;color:#6B7280;">${formatDate(c.created_at)}</td>
                </tr>
            `,
            )
            .join("");
  } catch (err) {
    showAdminToast("Failed to load categories", "error");
  }
}

function closeCategoryModal() {
  document.getElementById("categoryModal").classList.remove("active");
}

async function saveCategory() {
  const name = document.getElementById("categoryName").value.trim();
  if (!name) {
    showAdminToast("Category name is required", "error");
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/categories.php?action=create`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name }),
    });

    const data = await res.json();

    if (data.success) {
      closeCategoryModal();
      loadAdminCategoriesTable();
      loadAdminCategories(); // Refresh select dropdown
      showAdminToast("Category created!", "success");
    } else {
      showAdminToast(data.error || "Failed to create", "error");
    }
  } catch (err) {
    showAdminToast("Error creating category", "error");
  }
}

// Utilities

function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function showAdminToast(message, type = "success") {
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
