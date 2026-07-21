<?php
// auth/logout.php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
logout_user();
redirect(BASE_URL . '/auth/login.php');
exit;
