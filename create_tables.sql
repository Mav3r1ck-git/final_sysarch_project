-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    year_level ENUM('1st Year', '2nd Year', '3rd Year', '4th Year') NOT NULL,
    course VARCHAR(100) NOT NULL,
    profile_picture VARCHAR(255) DEFAULT 'default_profile_picture.png',
    remaining_sessions INT DEFAULT 30,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create current_sitins table
CREATE TABLE IF NOT EXISTS current_sitins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    lab VARCHAR(50) NOT NULL,
    pc_number INT NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    other_purpose VARCHAR(255),
    sitin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create logged_out_sitins table
CREATE TABLE IF NOT EXISTS logged_out_sitins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    lab VARCHAR(50) NOT NULL,
    pc_number INT NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    other_purpose VARCHAR(255),
    sitin_time TIMESTAMP NOT NULL,
    logout_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    was_rewarded BOOLEAN DEFAULT FALSE,
    feedback TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create lab_pcs table to track PC availability
CREATE TABLE IF NOT EXISTS lab_pcs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab VARCHAR(50) NOT NULL,
    pc_number INT NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    UNIQUE KEY unique_lab_pc (lab, pc_number)
);

-- Insert default PC numbers for each lab
INSERT INTO lab_pcs (lab, pc_number) VALUES
('Lab 524', 1), ('Lab 524', 2), ('Lab 524', 3), ('Lab 524', 4), ('Lab 524', 5),
('Lab 524', 6), ('Lab 524', 7), ('Lab 524', 8), ('Lab 524', 9), ('Lab 524', 10),
('Lab 524', 11), ('Lab 524', 12), ('Lab 524', 13), ('Lab 524', 14), ('Lab 524', 15),
('Lab 524', 16), ('Lab 524', 17), ('Lab 524', 18), ('Lab 524', 19), ('Lab 524', 20),
('Lab 524', 21), ('Lab 524', 22), ('Lab 524', 23), ('Lab 524', 24), ('Lab 524', 25),
('Lab 524', 26), ('Lab 524', 27), ('Lab 524', 28), ('Lab 524', 29), ('Lab 524', 30),

('Lab 526', 1), ('Lab 526', 2), ('Lab 526', 3), ('Lab 526', 4), ('Lab 526', 5),
('Lab 526', 6), ('Lab 526', 7), ('Lab 526', 8), ('Lab 526', 9), ('Lab 526', 10),
('Lab 526', 11), ('Lab 526', 12), ('Lab 526', 13), ('Lab 526', 14), ('Lab 526', 15),
('Lab 526', 16), ('Lab 526', 17), ('Lab 526', 18), ('Lab 526', 19), ('Lab 526', 20),
('Lab 526', 21), ('Lab 526', 22), ('Lab 526', 23), ('Lab 526', 24), ('Lab 526', 25),
('Lab 526', 26), ('Lab 526', 27), ('Lab 526', 28), ('Lab 526', 29), ('Lab 526', 30),

('Lab 528', 1), ('Lab 528', 2), ('Lab 528', 3), ('Lab 528', 4), ('Lab 528', 5),
('Lab 528', 6), ('Lab 528', 7), ('Lab 528', 8), ('Lab 528', 9), ('Lab 528', 10),
('Lab 528', 11), ('Lab 528', 12), ('Lab 528', 13), ('Lab 528', 14), ('Lab 528', 15),
('Lab 528', 16), ('Lab 528', 17), ('Lab 528', 18), ('Lab 528', 19), ('Lab 528', 20),
('Lab 528', 21), ('Lab 528', 22), ('Lab 528', 23), ('Lab 528', 24), ('Lab 528', 25),
('Lab 528', 26), ('Lab 528', 27), ('Lab 528', 28), ('Lab 528', 29), ('Lab 528', 30),

('Lab 530', 1), ('Lab 530', 2), ('Lab 530', 3), ('Lab 530', 4), ('Lab 530', 5),
('Lab 530', 6), ('Lab 530', 7), ('Lab 530', 8), ('Lab 530', 9), ('Lab 530', 10),
('Lab 530', 11), ('Lab 530', 12), ('Lab 530', 13), ('Lab 530', 14), ('Lab 530', 15),
('Lab 530', 16), ('Lab 530', 17), ('Lab 530', 18), ('Lab 530', 19), ('Lab 530', 20),
('Lab 530', 21), ('Lab 530', 22), ('Lab 530', 23), ('Lab 530', 24), ('Lab 530', 25),
('Lab 530', 26), ('Lab 530', 27), ('Lab 530', 28), ('Lab 530', 29), ('Lab 530', 30),

('Lab 542', 1), ('Lab 542', 2), ('Lab 542', 3), ('Lab 542', 4), ('Lab 542', 5),
('Lab 542', 6), ('Lab 542', 7), ('Lab 542', 8), ('Lab 542', 9), ('Lab 542', 10),
('Lab 542', 11), ('Lab 542', 12), ('Lab 542', 13), ('Lab 542', 14), ('Lab 542', 15),
('Lab 542', 16), ('Lab 542', 17), ('Lab 542', 18), ('Lab 542', 19), ('Lab 542', 20),
('Lab 542', 21), ('Lab 542', 22), ('Lab 542', 23), ('Lab 542', 24), ('Lab 542', 25),
('Lab 542', 26), ('Lab 542', 27), ('Lab 542', 28), ('Lab 542', 29), ('Lab 542', 30),

('Lab 544', 1), ('Lab 544', 2), ('Lab 544', 3), ('Lab 544', 4), ('Lab 544', 5),
('Lab 544', 6), ('Lab 544', 7), ('Lab 544', 8), ('Lab 544', 9), ('Lab 544', 10),
('Lab 544', 11), ('Lab 544', 12), ('Lab 544', 13), ('Lab 544', 14), ('Lab 544', 15),
('Lab 544', 16), ('Lab 544', 17), ('Lab 544', 18), ('Lab 544', 19), ('Lab 544', 20),
('Lab 544', 21), ('Lab 544', 22), ('Lab 544', 23), ('Lab 544', 24), ('Lab 544', 25),
('Lab 544', 26), ('Lab 544', 27), ('Lab 544', 28), ('Lab 544', 29), ('Lab 544', 30),

('Lab 517', 1), ('Lab 517', 2), ('Lab 517', 3), ('Lab 517', 4), ('Lab 517', 5),
('Lab 517', 6), ('Lab 517', 7), ('Lab 517', 8), ('Lab 517', 9), ('Lab 517', 10),
('Lab 517', 11), ('Lab 517', 12), ('Lab 517', 13), ('Lab 517', 14), ('Lab 517', 15),
('Lab 517', 16), ('Lab 517', 17), ('Lab 517', 18), ('Lab 517', 19), ('Lab 517', 20),
('Lab 517', 21), ('Lab 517', 22), ('Lab 517', 23), ('Lab 517', 24), ('Lab 517', 25),
('Lab 517', 26), ('Lab 517', 27), ('Lab 517', 28), ('Lab 517', 29), ('Lab 517', 30);

-- Table for uploaded materials
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for uploaded lab schedules
CREATE TABLE IF NOT EXISTS lab_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for announcements
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
); 