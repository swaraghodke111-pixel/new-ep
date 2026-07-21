<?php
// index.php — Entry point: redirect based on login state
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(get_dashboard_url());
} else {
    redirect(BASE_URL . '/auth/login.php');
}
