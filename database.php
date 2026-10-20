-- Create database
CREATE DATABASE IF NOT EXISTS fsc_inventory;
USE fsc_inventory;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Supplier', 'Retailer', 'Customer') NOT NULL,
    full_name VARCHAR(100),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    region VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Inventory table
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fertilizer_type VARCHAR(50) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    warehouse VARCHAR(100) NOT NULL,
    minimum_threshold DECIMAL(10,2) DEFAULT 100,
    expiry_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Farmers table
CREATE TABLE IF NOT EXISTS farmers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id VARCHAR(50) UNIQUE NOT NULL,
    region VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    woreda VARCHAR(100) NOT NULL,
    kebele VARCHAR(100) NOT NULL,
    land_size DECIMAL(10,2) NOT NULL,
    requirement DECIMAL(10,2) NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    registration_date DATE DEFAULT (CURRENT_DATE),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Distributions table
CREATE TABLE IF NOT EXISTS distributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(100) NOT NULL,
    fertilizer_type VARCHAR(50) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    distribution_date DATE NOT NULL,
    status ENUM('Pending', 'Delivered', 'In-Transit', 'Leakage Alert') DEFAULT 'Pending',
    farmer_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE SET NULL
);

-- Shipments table
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    truck_plate VARCHAR(20) NOT NULL,
    driver_name VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL,
    woreda VARCHAR(100) NOT NULL,
    hub VARCHAR(100) NOT NULL,
    eta VARCHAR(50),
    status ENUM('In-Transit', 'Delivered', 'Delayed') DEFAULT 'In-Transit',
    supplier_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample data
INSERT INTO users (username, password, role, full_name) VALUES
('admin', 'admin123', 'Admin', 'System Administrator'),
('supplier', 'supplier123', 'Supplier', 'Main Supplier Co.'),
('retailer', 'retailer123', 'Retailer', 'Local Retailer'),
('customer', 'customer123', 'Customer', 'Demo Farmer');

INSERT INTO inventory (fertilizer_type, quantity, warehouse) VALUES
('DAP', 5000, 'Debark Central'),
('Urea', 3500, 'Debark Central'),
('DAP', 2000, 'Gondar Hub'),
('Urea', 1500, 'Gondar Hub'),
('NPS', 1000, 'Axum Depot');

INSERT INTO farmers (farmer_id, region, full_name, woreda, kebele, land_size, requirement) VALUES
('FARM-2025-1001', 'AMHARA', 'ABEBE BALCHA', 'DEBARK', 'Kebele 01', 2.5, 7.5),
('FARM-2025-1002', 'AMHARA', 'CHALA GULUMA', 'DEBARK', 'Kebele 02', 3.0, 9.0),
('FARM-2025-1003', 'TIGRAY', 'HAILE MARIAM', 'AXUM', 'Kebele 05', 4.0, 12.0),
('FARM-2025-1004', 'OROMIA', 'BEKELE TADESSE', 'JIMA', 'Kebele 10', 5.5, 16.5);

INSERT INTO distributions (region, fertilizer_type, quantity, distribution_date, status, farmer_id) VALUES
('AMHARA', 'DAP', 1500, CURDATE(), 'Delivered', 1),
('AMHARA', 'Urea', 1000, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Delivered', 2),
('TIGRAY', 'DAP', 800, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'In-Transit', 3);

INSERT INTO shipments (truck_plate, driver_name, region, woreda, hub, eta, status) VALUES
('ET-3-A1234', 'ABEBE BELAY', 'AMHARA', 'DEBARK', 'Debark Hub', '2 hours', 'In-Transit'),
('ET-5-B5678', 'MULUGETA KASSA', 'TIGRAY', 'AXUM', 'Axum Depot', 'Tomorrow', 'In-Transit'),
('ET-7-C9012', 'ALEMU BEKELE', 'OROMIA', 'JIMA', 'Jima Hub', 'Delivered', 'Delivered');