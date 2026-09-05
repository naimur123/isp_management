<?php
require_once __DIR__ . '/config/config.php';
redirect(is_logged_in() && !empty(current_user()) ? base_url('dashboard.php') : base_url('login.php'));
