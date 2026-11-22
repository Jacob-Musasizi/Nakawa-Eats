CREATE DATABASE IF NOT EXISTS order_system CHARACTER SET utf8mb4;
USE order_system;
CREATE TABLE restaurants(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, location VARCHAR(160), delivery_fee INT NOT NULL DEFAULT 3000, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY, restaurant_id INT NOT NULL, name VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('owner','waiter') NOT NULL, FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE);
CREATE TABLE customers(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE, phone VARCHAR(20) NOT NULL, address VARCHAR(255), password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE menu_items(id INT AUTO_INCREMENT PRIMARY KEY, restaurant_id INT NOT NULL, name VARCHAR(120) NOT NULL, category ENUM('Food','Drinks','Desserts') NOT NULL DEFAULT 'Food', price INT NOT NULL, image VARCHAR(255), available TINYINT DEFAULT 1, FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE);
CREATE TABLE orders(id INT AUTO_INCREMENT PRIMARY KEY, restaurant_id INT NOT NULL, customer_id INT NULL, token INT NOT NULL,
  order_type ENUM('dine_in','take_away','delivery') NOT NULL,
  status ENUM('pending','preparing','ready','collected','out_for_delivery','delivered') NOT NULL DEFAULT 'pending',
  total INT NOT NULL, delivery_fee INT NOT NULL DEFAULT 0, delivery_address VARCHAR(255), phone VARCHAR(20),
  payment_method ENUM('cash','mtn_momo','airtel_money','card') NOT NULL DEFAULT 'cash',
  payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid', payment_ref VARCHAR(20),
  called_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(restaurant_id, created_at),
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE, FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL);
CREATE TABLE order_items(id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, item_id INT, name VARCHAR(120) NOT NULL, price INT NOT NULL, qty INT NOT NULL, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE);
