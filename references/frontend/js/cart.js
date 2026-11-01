/**
 * Cart Module
 * Manages shopping cart state and operations
 */

const Cart = {
  items: [],

  // Add item to cart
  add(product) {
    const existing = this.items.find((item) => item.product_id === product.id);

    if (existing) {
      if (existing.quantity >= product.stock) {
        showToast(`Maximum stock reached for "${product.name}"`, "error");
        return false;
      }
      existing.quantity++;
    } else {
      if (product.stock <= 0) {
        showToast(`"${product.name}" is out of stock`, "error");
        return false;
      }
      this.items.push({
        product_id: product.id,
        name: product.name,
        price: parseFloat(product.price),
        quantity: 1,
        max_stock: product.stock,
      });
    }

    this.render();
    showToast(`Added "${product.name}" to cart`);
    return true;
  },

  // Remove item from cart
  remove(productId) {
    this.items = this.items.filter((item) => item.product_id !== productId);
    this.render();
  },

  // Update quantity
  updateQty(productId, delta) {
    const item = this.items.find((i) => i.product_id === productId);
    if (!item) return;

    const newQty = item.quantity + delta;

    if (newQty <= 0) {
      this.remove(productId);
      return;
    }

    if (newQty > item.max_stock) {
      showToast("Cannot exceed available stock", "error");
      return;
    }

    item.quantity = newQty;
    this.render();
  },

  // Get total
  getTotal() {
    return this.items.reduce(
      (sum, item) => sum + item.price * item.quantity,
      0,
    );
  },

  // Get total items count
  getCount() {
    return this.items.reduce((sum, item) => sum + item.quantity, 0);
  },

  // Clear cart
  clear() {
    this.items = [];
    this.render();
  },

  // Render cart UI
  render() {
    const cartItemsEl = document.getElementById("cartItems");
    const cartCountEl = document.getElementById("cartCount");
    const cartTotalEl = document.getElementById("cartTotal");
    const checkoutBtn = document.getElementById("checkoutBtn");

    const count = this.getCount();
    const total = this.getTotal();

    cartCountEl.textContent = count;
    cartTotalEl.textContent = `$${total.toFixed(2)}`;
    checkoutBtn.disabled = this.items.length === 0;

    if (this.items.length === 0) {
      cartItemsEl.innerHTML = `
                <div class="cart-empty">
                    <span class="cart-empty-icon">🛒</span>
                    <span>Cart is empty</span>
                    <span style="font-size: 0.8rem;">Click a product to add it</span>
                </div>
            `;
      return;
    }

    cartItemsEl.innerHTML = this.items
      .map(
        (item) => `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${escapeHtml(item.name)}</div>
                    <div class="cart-item-price">$${item.price.toFixed(2)} each</div>
                </div>
                <div class="cart-item-qty">
                    <button onclick="Cart.updateQty(${item.product_id}, -1)">−</button>
                    <span>${item.quantity}</span>
                    <button onclick="Cart.updateQty(${item.product_id}, 1)">+</button>
                </div>
                <div class="cart-item-subtotal">$${(item.price * item.quantity).toFixed(2)}</div>
                <button class="cart-item-remove" onclick="Cart.remove(${item.product_id})">✕</button>
            </div>
        `,
      )
      .join("");
  },
};
