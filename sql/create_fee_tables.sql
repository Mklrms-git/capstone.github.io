-- Fee Categories Table
CREATE TABLE IF NOT EXISTS fee_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Fees Table
CREATE TABLE IF NOT EXISTS fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES fee_categories(id)
) ENGINE=InnoDB;

-- Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    fee_id INT NOT NULL,
    appointment_id INT,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('Cash', 'Card', 'Online', 'Insurance') NOT NULL DEFAULT 'Cash',
    payment_status ENUM('Pending', 'Completed', 'Refunded') NOT NULL DEFAULT 'Pending',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (fee_id) REFERENCES fees(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Insert some default fee categories
INSERT INTO fee_categories (name, description) VALUES
('Consultation', 'Regular doctor consultation fees'),
('Laboratory', 'Various laboratory tests and procedures'),
('Imaging', 'X-ray, ultrasound, and other imaging services'),
('Procedures', 'Minor surgical and medical procedures'),
('Medicine', 'Prescribed medications and supplies');

-- Insert some sample fees
INSERT INTO fees (category_id, name, amount, description) VALUES
((SELECT id FROM fee_categories WHERE name = 'Consultation'), 'General Consultation', 500.00, 'Regular consultation with general physician'),
((SELECT id FROM fee_categories WHERE name = 'Consultation'), 'Specialist Consultation', 1000.00, 'Consultation with specialist doctor'),
((SELECT id FROM fee_categories WHERE name = 'Laboratory'), 'Complete Blood Count', 350.00, 'CBC test'),
((SELECT id FROM fee_categories WHERE name = 'Laboratory'), 'Urinalysis', 200.00, 'Urine analysis test'),
((SELECT id FROM fee_categories WHERE name = 'Laboratory'), 'Blood Chemistry', 800.00, 'Basic blood chemistry panel'),
((SELECT id FROM fee_categories WHERE name = 'Imaging'), 'Chest X-ray', 800.00, 'Standard chest x-ray'),
((SELECT id FROM fee_categories WHERE name = 'Imaging'), 'Ultrasound', 1500.00, 'General ultrasound scan'),
((SELECT id FROM fee_categories WHERE name = 'Procedures'), 'Wound Dressing', 300.00, 'Basic wound cleaning and dressing'),
((SELECT id FROM fee_categories WHERE name = 'Procedures'), 'ECG', 600.00, 'Electrocardiogram'),
((SELECT id FROM fee_categories WHERE name = 'Medicine'), 'Antibiotics', 500.00, 'Basic antibiotic course'),
((SELECT id FROM fee_categories WHERE name = 'Medicine'), 'Pain Relievers', 200.00, 'Standard pain medication'); 