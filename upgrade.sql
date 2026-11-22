-- Only for a database you ALREADY imported before the delivery update. New installs use database.sql.
USE order_system;
ALTER TABLE restaurants ADD delivery_fee INT NOT NULL DEFAULT 3000;
CREATE TABLE customers(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE, phone VARCHAR(20) NOT NULL, address VARCHAR(255), password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
ALTER TABLE orders
  MODIFY order_type ENUM('dine_in','take_away','delivery') NOT NULL,
  MODIFY status ENUM('pending','preparing','ready','collected','out_for_delivery','delivered') NOT NULL DEFAULT 'pending',
  ADD customer_id INT NULL, ADD delivery_fee INT NOT NULL DEFAULT 0, ADD delivery_address VARCHAR(255), ADD phone VARCHAR(20),
  ADD payment_method ENUM('cash','mtn_momo','airtel_money','card') NOT NULL DEFAULT 'cash',
  ADD payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid', ADD payment_ref VARCHAR(20),
  ADD FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL;
