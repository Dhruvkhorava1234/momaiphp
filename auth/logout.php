<?php
/**
 * Logout Action
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/../includes/helpers.php';

// Unset user session data
unset($_SESSION['user']);
session_destroy();

// Restart clean session for flash message
session_start();
flash_set('success', 'You have been successfully logged out.');
redirect('login.php');
