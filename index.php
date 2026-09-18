<?php
/**
 * Root Entrypoint
 * MOMAI PLYWOOD - Core PHP
 */
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}
