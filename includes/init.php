<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login/index.php');
        exit;
    }
}

// Function to check user role
function hasRole($role) {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Function to require specific role
function requireRole($role) {
    if (!hasRole($role)) {
        // If not logged in, redirect to login
        if (!isLoggedIn()) {
            header('Location: ' . BASE_URL . '/login/index.php');
        } else {
            // If logged in but wrong role, redirect to home or show error
            header('Location: ' . BASE_URL . '/'); // We'll create a home page later, for now redirect to login
        }
        exit;
    }
}

// Get current user data from session
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['full_name']
        ];
    }
    return null;
}
?>