<?php
/**
 * Guest Guard - Prevents logged-in users from viewing login/register pages
 */
require_once __DIR__ . '/helpers.php';

if (is_logged_in()) {
    redirect('../dashboard.php');
}
