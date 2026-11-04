<?php
require_once __DIR__ . '/includes/auth.php';

$wasAdmin = isset($_GET['admin']);
logout_all();

// Start a fresh session only to carry the confirmation message.
session_start();
set_flash('success', 'You have been logged out successfully.');
redirect($wasAdmin ? 'admin/login.php' : 'login.php');
