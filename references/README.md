
https://github.com/user-attachments/assets/c2013664-b308-4478-a248-32a81ba16327
# POS Cashier System — Architecture

# Installation
1. Git clone this whole repo
2. Ensure your XAMPP ready, and start Apache and MySQL
3. Go to `http://localhost/phpmyadmin/index.php?route=/server/sql`, if you fail to enter there, thats means you're not fully connected to MySQL.
4. Type this SQL code:
<details>
    <summary>script</summary>
    
    ```php
    CREATE DATABASE IF NOT EXISTS pos_cashier;
    USE pos_cashier;
    
    -- Users table
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    
    -- Categories table
    CREATE TABLE categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    
    -- Products table
    CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        category_id INT NOT NULL,
        price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        stock INT NOT NULL DEFAULT 0,
        image VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB;
    
    -- Transactions table
    CREATE TABLE transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_code VARCHAR(50) NOT NULL UNIQUE,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_items INT NOT NULL DEFAULT 0,
        payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        change_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    
    -- Transaction items table
    CREATE TABLE transaction_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        product_name VARCHAR(150) NOT NULL,
        product_price DECIMAL(12,2) NOT NULL,
        quantity INT NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB;
    
    --# SEED DATA
    -- Admin user (password: admin123)
    INSERT INTO users (username, password, full_name, role) VALUES
    ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');
    
    -- Categories
    INSERT INTO categories (name) VALUES
    ('Beverages'),
    ('Snacks'),
    ('Dairy'),
    ('Bakery'),
    ('Fruits'),
    ('Household'),
    ('Personal Care'),
    ('Canned Goods');
    
    -- Products
    INSERT INTO products (name, category_id, price, stock, image) VALUES
    ('Mineral Water 500ml', 1, 1.00, 150, NULL),
    ('Cola 330ml', 1, 1.50, 100, NULL),
    ('Orange Juice 1L', 1, 3.50, 60, NULL),
    ('Green Tea Bottle', 1, 2.00, 80, NULL),
    ('Coffee Latte Can', 1, 2.50, 45, NULL),
    ('Potato Chips Classic', 2, 2.00, 75, NULL),
    ('Chocolate Bar', 2, 1.75, 120, NULL),
    ('Corn Chips Spicy', 2, 2.25, 55, NULL),
    ('Mixed Nuts Pack', 2, 4.50, 40, NULL),
    ('Crackers Salted', 2, 1.50, 90, NULL),
    ('Fresh Milk 1L', 3, 3.00, 50, NULL),
    ('Yogurt Strawberry', 3, 2.00, 65, NULL),
    ('Cheese Slice Pack', 3, 4.00, 30, NULL),
    ('Butter 200g', 3, 3.50, 25, NULL),
    ('White Bread Loaf', 4, 2.50, 40, NULL),
    ('Croissant Pack (4)', 4, 5.00, 20, NULL),
    ('Muffin Blueberry', 4, 3.00, 35, NULL),
    ('Apple (per kg)', 5, 4.00, 60, NULL),
    ('Banana (per kg)', 5, 2.50, 80, NULL),
    ('Orange (per kg)', 5, 3.50, 50, NULL),
    ('Dish Soap 500ml', 6, 3.00, 40, NULL),
    ('Laundry Detergent 1kg', 6, 6.50, 30, NULL),
    ('Trash Bags (20pcs)', 6, 2.50, 55, NULL),
    ('Shampoo 200ml', 7, 4.50, 35, NULL),
    ('Toothpaste 100g', 7, 2.50, 60, NULL),
    ('Hand Soap 250ml', 7, 3.00, 45, NULL),
    ('Canned Tuna 185g', 8, 3.00, 70, NULL),
    ('Canned Corn 340g', 8, 2.50, 50, NULL),
    ('Canned Beans 400g', 8, 2.00, 3, NULL),
    ('Tomato Sauce 500ml', 8, 2.50, 0, NULL);
    
    -- Sample past transactions for dashboard demo
    INSERT INTO transactions (transaction_code, total_amount, total_items, payment_amount, change_amount, created_at) VALUES
    ('TXN-20250101-001', 15.50, 5, 20.00, 4.50, '2025-01-15 10:30:00'),
    ('TXN-20250101-002', 28.75, 8, 30.00, 1.25, '2025-01-15 14:20:00'),
    ('TXN-20250102-001', 42.00, 12, 50.00, 8.00, '2025-01-16 09:15:00'),
    ('TXN-20250102-002', 8.50, 3, 10.00, 1.50, CURRENT_TIMESTAMP),
    ('TXN-20250102-003', 22.25, 7, 25.00, 2.75, CURRENT_TIMESTAMP);
    
    INSERT INTO transaction_items (transaction_id, product_id, product_name, product_price, quantity, subtotal) VALUES
    (1, 1, 'Mineral Water 500ml', 1.00, 2, 2.00),
    (1, 6, 'Potato Chips Classic', 2.00, 1, 2.00),
    (1, 7, 'Chocolate Bar', 1.75, 2, 3.50),
    (1, 11, 'Fresh Milk 1L', 3.00, 1, 3.00),
    (1, 15, 'White Bread Loaf', 2.50, 2, 5.00),
    (2, 2, 'Cola 330ml', 1.50, 3, 4.50),
    (2, 9, 'Mixed Nuts Pack', 4.50, 2, 9.00),
    (2, 18, 'Apple (per kg)', 4.00, 1, 4.00),
    (2, 19, 'Banana (per kg)', 2.50, 2, 5.00),
    (2, 25, 'Toothpaste 100g', 2.50, 1, 2.50),
    (2, 10, 'Crackers Salted', 1.50, 1, 1.50),
    (2, 3, 'Orange Juice 1L', 3.50, 1, 3.50),
    (3, 22, 'Laundry Detergent 1kg', 6.50, 2, 13.00),
    (3, 21, 'Dish Soap 500ml', 3.00, 3, 9.00),
    (3, 24, 'Shampoo 200ml', 4.50, 2, 9.00),
    (3, 26, 'Hand Soap 250ml', 3.00, 1, 3.00),
    (3, 23, 'Trash Bags (20pcs)', 2.50, 2, 5.00),
    (3, 1, 'Mineral Water 500ml', 1.00, 3, 3.00),
    (4, 1, 'Mineral Water 500ml', 1.00, 2, 2.00),
    (4, 7, 'Chocolate Bar', 1.75, 1, 1.75),
    (4, 19, 'Banana (per kg)', 2.50, 1, 2.50),
    (4, 25, 'Toothpaste 100g', 2.50, 1, 2.50),
    (5, 11, 'Fresh Milk 1L', 3.00, 2, 6.00),
    (5, 15, 'White Bread Loaf', 2.50, 1, 2.50),
    (5, 16, 'Croissant Pack (4)', 5.00, 1, 5.00),
    (5, 12, 'Yogurt Strawberry', 2.00, 2, 4.00),
    (5, 6, 'Potato Chips Classic', 2.00, 1, 2.00),
    (5, 10, 'Crackers Salted', 1.50, 1, 1.50),
    (5, 4, 'Green Tea Bottle', 2.00, 1, 2.00);
    ```
</details>
5. Open it in your `localhost/$thisRepoName/frontend/htm/index.html`

# Explanation (even better _if you're coming from a MERN stack background_)

have a nice read :)... sorry i have to explain this as if its a mern stack, im a mern stack backgroun lol.

---

## MERN Model vs PHP Model

In MERN, the layers mostly look like this:

```
MongoDB  ←→  Express API  ←→  React Frontend
(Data)        (Server)         (UI + State)
```

In this PHP stack (cmiiw), it look like this:

```
MySQL  ←→  PHP Backend  ←→  HTML/CSS/JS Frontend
(Data)      (Server)         (UI + State)
```

The concept is pretty much identic 1 to 1. The tools are different. In this case, PHP replaces Express + Node. MySQL replaces MongoDB. Vanilla JS with Fetch API replaces React with Axios. HTML files replace the JSX components.

Also one more GREAT differences. In MERN, thje Express server runs **continuously** as a long-lived process. In PHP on Apache (XAMPP), there is **no persistent server process**. Every time the browser hits a PHP file, Apache spins up PHP, runs the script top to bottom, returns the response, and PHP dies. So its stateless by nature. Sessions are the mechanism that fake persistence between those requests.

---

## File structure

```
pos-cashier/
│
├── assets/
│   └── images/                  → Static files (like Express static middleware)
│
├── backend/
│   └── php/
│       ├── db.php               →  database.js / mongoose connection
│       ├── auth.php             →  authRoutes.js + authController.js
│       ├── products.php         →  productRoutes.js + productController.js
│       ├── categories.php       →  categoryRoutes.js + categoryController.js
│       ├── transactions.php     →  orderRoutes.js + orderController.js
│       └── dashboard.php        →  analyticsController.js
│
├── frontend/
│   ├── htm/
│   │   ├── index.html           →  App.jsx / CashierPage.jsx (entry point)
│   │   ├── login.html           →  LoginPage.jsx
│   │   └── admin.html           →  AdminDashboard.jsx
│   ├── css/
│   │   └── style.css            →  global CSS / Tailwind base styles
│   └── js/
│       ├── app.js               →  CashierPage logic (useEffect, fetch, handlers)
│       ├── cart.js              →  CartContext.js / useCart.js custom hook
│       ├── admin.js             →  AdminPage logic
│       └── login.js             →  LoginPage logic
│
└── pos_cashier.sql              →  Mongoose schema seeds / migration file
```

---

## Each file... do what?

---

### `backend/php/db.php` — The Db

**MERN equivalent:** `config/db.js` or is Mongoose `connect()` call

In MERN we mostly write something like this:

```javascript
// MERN way
import mongoose from "mongoose";
mongoose.connect(process.env.MONGO_URI); // fetch from .env, lets just say this exist.
```

In PHP with PDO, it looks like this:

```php
// PHP way
$pdo = new PDO("mysql:host=localhost;dbname=pos_cashier", "root", "");
```

PDO stands for PHP Data Objects. its like MongoDB driver or the `mysql2` npm package. and btw this PDO supports **prepared statements**, which is PHP's defense against SQL injection. The equivalent in MERN is parameterized queries or Mongoose's built-in sanitization.

Every other PHP file starts with:

```php
require_once __DIR__ . '/db.php';
```

This is exactly like writing `import { connectDB } from './config/db.js'` at the top of every route file. The `require_once` keyword ensures the file is only loaded once per request cycle, even if multiple files try to include it.

There is also a `jsonResponse()` helper defined here:

```php
function jsonResponse(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit();
}
```

In Express we mostly write `res.status(200).json(data)`. Now this.. This is exactly that. The `exit()` call is important because it stops PHP from executing any further code after the response is sent, the same way returning from an Express handler stops further middleware from running.

---

### `backend/php/auth.php` — Authentication

**MERN equivalent of:** `routes/auth.js` + `middleware/authMiddleware.js`

In MERN it use JWT. The flow looks like this:

```
POST /api/auth/login
→ Validate credentials
→ Sign a JWT
→ Return token to client
→ Client stores token in localStorage
→ Client sends token in every future request header
→ Middleware verifies token on protected routes
```

This PHP app uses **PHP Sessions** instead of JWT. The flow is:

```
POST /backend/php/auth.php?action=login
→ Validate credentials
→ Store user data in $_SESSION (server-side)
→ PHP sends a session cookie to the browser automatically
→ Browser sends that cookie on every future request automatically
→ PHP reads $_SESSION on protected endpoints
```

The key difference: **JWT is stateless** (the token contains all the info), **Sessions are stateful** (the server remembers who User are via a session ID stored in a cookie). Sessions are simpler for demo apps. JWT is better for production distributed systems. Still tho use JWT at all cases!

The `$_SESSION` superglobal in PHP is like a server-side `localStorage` that is tied to a specific user's browser session. btw this do not manage the cookie manually. PHP handles it.

The `action` query parameter on the URL acts like Express sub-routes:

```
/auth.php?action=login    →  like  POST /api/auth/login
/auth.php?action=logout   →  like  POST /api/auth/logout
/auth.php?action=check    →  like  GET  /api/auth/me
```

Also, the thing that In Express, we tend to separate these with `router.post('/login', ...)`. In PHP, it uses a `match()` expression to dispatch to different functions based on the URL parameter. Same concept, different syntax.

---

### `backend/php/products.php` — CRUD API

**MERN equivalent:** `routes/products.js` + `controllers/productController.js`

```javascript
// MERN way
router.get("/", getAllProducts);
router.post("/", protect, createProduct);
router.put("/:id", protect, updateProduct);
router.delete("/:id", protect, deleteProduct);
```

In PHP, the HTTP method is read from `$_SERVER['REQUEST_METHOD']` and the action from `$_GET['action']`. The entire routing logic sits in one file and dispatches using `match()`:

```php
// PHP way
match($method) {
    'GET' => match($action) {
        'list' => listProducts(),
        'get'  => getProduct(),
    },
    'POST' => match($action) {
        'create' => createProduct(),
        'update' => updateProduct(),
        'delete' => deleteProduct(),
    }
};

// honestly, php router somewhat similar to Rust Tokio
```

**Prepared Statements vs Mongoose Queries**

In Mongoose we write:

```javascript
// MERN way
const products = await Product.find({ category: req.query.category });
```

In PDO we write:

```php
// PHP way
$stmt = $db->prepare("SELECT * FROM products WHERE category_id = ?");
$stmt->execute([$categoryId]);
$products = $stmt->fetchAll();

// took 3 lines vro
```

The `?` placeholder is the prepared statement parameter. PDO binds the value safely, preventing SQL injection. Mongoose does the equivalent sanitization internally.

**Why `DELETE` uses `POST` here**

Normally REST says use the `DELETE` HTTP method for deletions. This project sends deletions as `POST` requests with `action=delete` in the URL. This is a pragmatic simplification for the demo. Many older PHP apps work this way. In production, most of the time we all would use proper REST verbs. Again, this is example.

---

### `backend/php/transactions.php` — The Checkout Engine

**MERN equivalent:** `controllers/orderController.js` with a Mongoose transaction session

This is the most important backend file. The checkout flow uses a **database transaction** to ensure data integrity. In MERN with Mongoose:

```javascript
// MERN way
const session = await mongoose.startSession();
session.startTransaction();
try {
    await Order.create([orderData], { session });
    await Product.updateMany(..., { session });
    await session.commitTransaction();
} catch (err) {
    await session.abortTransaction();
}
```

In PDO you write:

```php
// PHP way
$db->beginTransaction();
try {
    // All database operations here
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => $e->getMessage()], 400);
}
```

The concept is.. ehem.. again. identical. If any step fails — insufficient stock, invalid product, payment too low — everything rolls back and the database stays clean. No partial checkouts. No phantom inventory deductions.

The `FOR UPDATE` clause in the stock check query is critical:

```php
"SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE"
```

---

### `backend/php/dashboard.php` — Analytics

**MERN equivalent:** `controllers/analyticsController.js`

This file is only accessible to authenticated admins. It checks the session at the top:

```php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}
```

In Express with JWT middleware you write:

```javascript
router.get("/summary", protect, getSummary);
```

In PHP there is no middleware pipeline. Protection is done manually at the top of each function or file.

The queries here are pure SQL aggregations. The MERN equivalent would be MongoDB aggregation pipelines. For example, today's revenue:

```php
// PHP / SQL way
"SELECT COALESCE(SUM(total_amount), 0) as today_revenue
 FROM transactions WHERE DATE(created_at) = ?"
```

```javascript
// MERN / MongoDB way
Transaction.aggregate([
  { $match: { createdAt: { $gte: startOfDay, $lte: endOfDay } } },
  { $group: { _id: null, total: { $sum: "$totalAmount" } } },
]);
```

Both produce the same result. SQL aggregations tend to be more concise for relational data. MongoDB pipelines are more flexible for nested documents.

---

### `frontend/js/cart.js` — The Cart State Manager

**MERN equivalent:** `CartContext.js` + `useCart.js` custom hook, or a Redux cart slice

In React you manage cart state with `useState`, `useContext`, or Redux:

```javascript
// MERN way
const [cartItems, setCartItems] = useState([]);
const addToCart = (product) => {
  setCartItems((prev) => [...prev, product]);
};
```

In this app, the cart is a plain JavaScript **module-level object** called `Cart`:

```javascript
// HOnestly, i liked this more, feels much closer to real js/php.. than having to define some random junko like useState, like wth is that?
// Vanilla JS way
const Cart = {
  items: [], // This is your state
  add(product) {}, // This is your action/dispatch
  remove(id) {},
  render() {}, // This is your component re-render
};
```

The `Cart.render()` method is called after every state change. It directly writes HTML strings into the DOM using `innerHTML`. ine.

Btw theres no reactivity system (sadly), no state subscription, no diffing. When state changes, you manually call `render()`.

### `frontend/js/app.js` — The Cashier Page Logic

**MERN equivalent:** `pages/CashierPage.jsx` with `useEffect`, `useState`, and event handlers

In React you write:

```javascript
// MERN way
useEffect(() => {
  fetchProducts();
}, []);

const fetchProducts = async () => {
  const res = await axios.get("/api/products");
  setProducts(res.data.products);
};
```

In Vanilla JS you write:

```javascript
// Vanilla JS way
document.addEventListener("DOMContentLoaded", () => {
  loadProducts();
});

async function loadProducts() {
  const res = await fetch(`${API_BASE}/products.php?action=list`);
  const data = await res.json();
  allProducts = data.products;
  renderProducts(allProducts);
}
```

`DOMContentLoaded` is `useEffect(() => {}, [])`. The empty dependency array in React means "run once on mount." `DOMContentLoaded` fires once when the page HTML is fully parsed. They are the same concept. I think you can agree on this explanation of useEffect

`fetch()` is the browser-native equivalent of `axios.get()`. Both return promises. Both parse JSON. The main difference is that `fetch()` does not automatically throw on non-2xx status codes, so you need to check `res.ok` manually in production code.

The `allProducts` variable is a module-level array, like a `useRef` or a state variable that does not trigger re-renders when changed. When you filter, you filter from `allProducts` and pass results to `renderProducts()`, which writes new HTML into the grid. That is your filtered product list re-render.

---

### `frontend/js/login.js` and `frontend/js/admin.js`

**MERN equivalent:** `pages/LoginPage.jsx` and `pages/AdminDashboard.jsx`

These files follow the exact same pattern as `app.js`. On `DOMContentLoaded`, they check authentication status by calling the PHP session check endpoint. If not authenticated, they redirect. This is the equivalent of a React `PrivateRoute` wrapper or an `AuthGuard`.

```javascript
// The auth guard pattern in Vanilla JS
const res = await fetch(`${API_BASE}/auth.php?action=check`);
const data = await res.json();
if (!data.authenticated) {
  window.location.href = "login.html"; // Redirect, like React Router's navigate()
}
```

Tab switching in the admin panel is done by toggling CSS classes. In React if im not wrong, i think it would conditionally render components. Here it show and hide `<div>` elements by adding or removing the `active` class. Same visual result, no Virtual DOM.

---

## How Files Communicate With Each Other

Here is the complete communication map of the system:

```
┌─────────────────────────────────────────────────────────────────┐
│                        BROWSER                                   │
│                                                                  │
│   index.html ──loads──► style.css                               │
│       │                                                          │
│       └──loads──► cart.js   (Cart object, no API calls)         │
│       └──loads──► app.js    (orchestrates everything)           │
│                       │                                          │
│                       │  fetch() calls over HTTP                 │
│                       │                                          │
└───────────────────────┼──────────────────────────────────────────┘
                        │
                        ▼  HTTP Request (GET/POST + JSON body)
┌─────────────────────────────────────────────────────────────────┐
│                      APACHE / PHP                                │
│                                                                  │
│   products.php  ◄── GET ?action=list                            │
│       │                                                          │
│       └──► db.php  (gets PDO connection)                        │
│       └──► MySQL query                                          │
│       └──► echo json_encode($data)  ──► HTTP Response (JSON)   │
│                                                                  │
│   transactions.php ◄── POST ?action=checkout (JSON body)        │
│       └──► db.php                                               │
│       └──► BEGIN TRANSACTION                                    │
│       └──► Validate stock                                       │
│       └──► INSERT transaction + items                           │
│       └──► UPDATE stock                                         │
│       └──► COMMIT                                               │
│       └──► Return receipt data as JSON                          │
│                                                                  │
│   auth.php ◄── POST ?action=login (JSON body)                   │
│       └──► db.php                                               │
│       └──► Verify password                                      │
│       └──► $_SESSION['logged_in'] = true                        │
│       └──► PHP sets session cookie in response header           │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                        │
                        ▼  PDO Connection
┌─────────────────────────────────────────────────────────────────┐
│                       MYSQL DATABASE                             │
│                                                                  │
│   users ──────────────────────────────┐                         │
│   categories ──────────────┐          │                         │
│   products (category_id FK)┘          │  (no FK, historical)    │
│   transactions ───────────────────────┘                         │
│   transaction_items (transaction_id FK, product_id FK nullable) │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

Every arrow in this diagram is either a `fetch()` call from the browser, a `require_once` between PHP files, or a PDO query from PHP to MySQL. There is no magic happening anywhere.

---

## The Request Lifecycle, Step by Step

Let's trace exactly what happens when a cashier adds a product to cart and checks out. This is the full round trip.

### Step 1: Page Load

```
Browser requests index.html
→ Apache serves the static HTML file
→ Browser parses HTML, finds <script> tags
→ Browser loads cart.js then app.js
→ app.js DOMContentLoaded fires
→ fetch('/backend/php/categories.php?action=list')
→ fetch('/backend/php/products.php?action=list')
→ Apache receives GET request, runs categories.php
→ categories.php calls db.php for PDO connection
→ PDO queries MySQL: SELECT * FROM categories
→ MySQL returns rows
→ PHP encodes as JSON, echoes it
→ Apache sends HTTP response back to browser
→ app.js receives JSON, calls renderProducts()
→ renderProducts() writes HTML into the DOM
→ Products appear on screen
```

This entire cycle happens in milliseconds. No page reload. This is AJAX, the same pattern React uses.

### Step 2: Add to Cart

```
User clicks a product card
→ onclick fires Cart.add(product)
→ Cart.add() checks: is product already in cart?
→ If yes: increment quantity (check against max_stock)
→ If no: push new item into Cart.items[]
→ Cart.render() is called
→ Cart.render() writes new cart HTML using innerHTML
→ DOM updates instantly
→ No server call happens here
```

The cart lives entirely in browser memory. This is the same as React's `useState` for cart items. No API call happens until checkout. The stock check against `max_stock` is a client-side guard. The real guard is on the server during checkout.

### Step 3: Checkout

```
User clicks "Proceed to Checkout"
→ Payment modal opens
→ User enters payment amount
→ JavaScript calculates change in real time (no server call)
→ User clicks "Confirm Payment"
→ fetch POST to /backend/php/transactions.php?action=checkout
→ Body: { items: [...], payment_amount: 150.00 }
→ Apache runs transactions.php
→ PHP reads JSON body via file_get_contents('php://input')
→ PDO begins a database transaction
→ For each item:
    → SELECT product with FOR UPDATE lock
    → Verify stock is sufficient
    → If not: throw Exception → rollback → return error
→ INSERT into transactions table
→ INSERT into transaction_items table (with price snapshot)
→ UPDATE products SET stock = stock - qty for each item
→ PDO commits transaction
→ PHP returns complete transaction data as JSON
→ Browser receives success response
→ showReceipt() renders the receipt modal
→ Cart.clear() empties the cart
→ loadProducts() re-fetches all products with updated stock
→ UI reflects new stock levels
```

The price snapshot in `transaction_items` is critical. The product's current price might change tomorrow. The receipt must show what was charged today. By storing `product_price` directly in `transaction_items`, historical accuracy is preserved forever. This is the equivalent of embedding a price in a MongoDB subdocument instead of referencing a product by ID.

---

## The Database Design, Explained

Coming from MongoDB, we tend to think in documents. Here the same data is expressed in related tables.

**MongoDB mental model:**

```javascript
// One document, nested
{
    _id: ObjectId,
    transactionCode: "TXN-001",
    totalAmount: 25.50,
    items: [
        { productId: ref, name: "Cola", price: 1.50, qty: 2 }
    ]
}
```

**MySQL relational model:**

```sql
-- Two tables, linked by foreign key
transactions: id=1, code="TXN-001", total=25.50
transaction_items: id=1, transaction_id=1, name="Cola", price=1.50, qty=2
```

The foreign key `transaction_id` in `transaction_items` is the relational equivalent of a nested array. Now lets say you `JOIN` the tables, this makes you reconstruct the same nested structure that MongoDB gives you natively.

The `product_id` in `transaction_items` is nullable (`DEFAULT NULL`) and uses `ON DELETE SET NULL`. This means if a product is deleted from the products table, the transaction history is not deleted. The `product_id` becomes NULL, but `product_name` and `product_price` remain intact. This is intentional. Historical accuracy is more important than referential integrity in this case.

---

## Why No Routing Library?!?!?! :shocked:

In MERN you use React Router:

```jsx
// lets say we use react-routerr-dom
<Route path="/admin" element={<AdminPage />} />
<Route path="/login" element={<LoginPage />} />
```

In this project, each HTML file **is** a route. The browser navigates between them using `window.location.href = 'admin.html'`. Apache serves whichever HTML file the browser requests. There is no client-side router. There is no single-page application shell. Each page is a complete, self-contained HTML document that loads its own CSS and JS.

This is simpler but has a tradeoff: every page navigation is a full page load. React Router's advantage is that navigation happens instantly without server round trips. For a POS demo running on a local network, full page loads are imperceptible.

---

## The Key Differences Summarized

| Concept          | MERN Stack                    | This PHP Stack                         |
| ---------------- | ----------------------------- | -------------------------------------- |
| Server runtime   | Node.js (always running)      | PHP (runs per request, dies after)     |
| API framework    | Express.js                    | Raw PHP with manual routing            |
| Database driver  | Mongoose / mongoose ODM       | PHP PDO                                |
| Query language   | MongoDB aggregation pipeline  | SQL with JOIN and GROUP BY             |
| Authentication   | JWT in Authorization header   | PHP Sessions via cookie                |
| UI rendering     | React Virtual DOM             | Direct DOM manipulation with innerHTML |
| State management | useState / useContext / Redux | Module-level JS variables              |
| Routing          | React Router (client-side)    | Separate HTML files (server-side)      |
| Component system | JSX components                | HTML files + JS functions              |
| Data shape       | JSON documents (nested)       | Relational tables (joined)             |
| Environment vars | `.env` file via dotenv        | PHP constants in `db.php`              |
| Package manager  | npm                           | None (no build step)                   |
| Build step       | Vite / Webpack                | None (files served directly)           |

benefit: no `npm install [100 dependencies here...]` and `node_modules` with the density of a blackhole.
cons: cant even re-render lmao, bad routing ahhh
