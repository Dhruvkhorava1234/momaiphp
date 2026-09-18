<?php
/**
 * Authentication Guard - Restricts pages to logged-in users only
 */
require_once __DIR__ . '/helpers.php';

if (!is_logged_in()) {
    flash_set('error', 'Please log in to access this page.');
    redirect('auth/login.php');
}
