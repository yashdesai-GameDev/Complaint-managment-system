-- Complaint Management System Database Schema
-- Run this in phpMyAdmin to create the database and tables

CREATE DATABASE IF NOT EXISTS complaint_management;
USE complaint_management;

-- Users table for all roles (student, admin, super_admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin', 'super_admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Complaints table
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('Hostel', 'Faculty', 'Facility', 'Sports', 'Other') NOT NULL,
    status ENUM('Pending', 'Work in Progress', 'Resolved') DEFAULT 'Pending',
    importance ENUM('Not Important', 'Normal', 'Important') DEFAULT 'Normal',
    urgency ENUM('Not Urgent', 'Normal', 'Urgent') DEFAULT 'Normal',
    student_id INT NOT NULL,
    assigned_admin_id INT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolution_note TEXT NULL,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (assigned_admin_id) REFERENCES users(id)
);

-- Votes table for upvote/downvote functionality
CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('upvote', 'downvote') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_complaint_vote (user_id, complaint_id)
);

-- Reviews table for student feedback on resolved complaints
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    student_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    UNIQUE KEY unique_student_complaint_review (student_id, complaint_id)
);

-- Insert seed data for testing
INSERT INTO users (username, email, password_hash, role, full_name) VALUES
('yash', 'yash@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Yash'),
('admin', 'admin@college.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin'),
('admin2', 'admin2@college.com', '$2y$12$OAlG635/z7viBaJcsJGb.u9cxjBjZsx/DMufRd2GS.03cRy6Fydte', 'admin', 'Admin2'),
('admin3', 'admin3@college.com', '$2y$12$OAlG635/z7viBaJcsJGb.u9cxjBjZsx/DMufRd2GS.03cRy6Fydte', 'admin', 'Admin3'),
('superadmin', 'superadmin@college.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Superadmin');

-- Note: The password for all seed accounts is 'password' (hashed with bcrypt)