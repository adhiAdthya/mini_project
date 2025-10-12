-- Minimal starter schema. Extend with more tables as you build modules.

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
);

-- Customer feedback on completed work orders
CREATE TABLE IF NOT EXISTS feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  customer_id INT NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comments TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_feedback_order_customer (work_order_id, customer_id),
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  phone VARCHAR(20),
  address VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  vin VARCHAR(64),
  license_plate VARCHAR(32),
  make VARCHAR(50),
  model VARCHAR(50),
  year INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS service_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  default_rate DECIMAL(10,2) DEFAULT 0,
  est_duration INT DEFAULT 60
);

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  vehicle_id INT NOT NULL,
  service_type_id INT NOT NULL,
  preferred_date DATETIME NOT NULL,
  status ENUM('new','approved','assigned','in_progress','completed','cancelled') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (service_type_id) REFERENCES service_types(id)
);

CREATE TABLE IF NOT EXISTS work_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  supervisor_id INT NOT NULL,
  mechanic_id INT NULL,
  status ENUM('new','in_progress','on_hold','completed','billed') DEFAULT 'new',
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  notes TEXT,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id),
  FOREIGN KEY (supervisor_id) REFERENCES users(id),
  FOREIGN KEY (mechanic_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS work_order_status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  status VARCHAR(32) NOT NULL,
  changed_by INT NOT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id),
  FOREIGN KEY (changed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS parts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(64) UNIQUE,
  name VARCHAR(120) NOT NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 0,
  cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(10,2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS spare_part_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  mechanic_id INT NOT NULL,
  part_id INT NOT NULL,
  qty INT NOT NULL,
  status ENUM('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  manager_id INT NULL,
  approved_qty INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id),
  FOREIGN KEY (mechanic_id) REFERENCES users(id),
  FOREIGN KEY (part_id) REFERENCES parts(id),
  FOREIGN KEY (manager_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  part_id INT NOT NULL,
  delta_qty INT NOT NULL,
  reason VARCHAR(64) NOT NULL,
  reference_id INT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (part_id) REFERENCES parts(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  number VARCHAR(50) UNIQUE,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('draft','issued','paid','void') DEFAULT 'draft',
  issued_at DATETIME NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id)
);

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  type ENUM('labor','part') NOT NULL,
  reference_id INT NULL,
  description VARCHAR(255) NOT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  method VARCHAR(32) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  paid_at DATETIME NOT NULL,
  txn_ref VARCHAR(120) NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  payload JSON NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(50) NOT NULL,
  entity_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  actor_id INT NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_id) REFERENCES users(id)
);

-- Seed basic roles
INSERT INTO roles (name) VALUES ('customer'), ('supervisor'), ('mechanic'), ('manager')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Seed demo users (password is 'password')
INSERT INTO users (name, email, password, role_id, is_active)
SELECT 'Demo Customer', 'customer@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name='customer'), 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='customer@gmail.com');

INSERT INTO users (name, email, password, role_id, is_active)
SELECT 'Supervisor', 'supervisor@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name='supervisor'), 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='supervisor@gmail.com');

INSERT INTO users (name, email, password, role_id, is_active)
SELECT 'Mechanic', 'mechanic@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name='mechanic'), 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='mechanic@gmail.com');

INSERT INTO users (name, email, password, role_id, is_active)
SELECT 'Manager', 'manager@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name='manager'), 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='manager@gmail.com');
