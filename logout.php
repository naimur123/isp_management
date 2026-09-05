<?php
require_once __DIR__ . '/config/config.php';
do_logout();
session_start();
flash_set('success', 'You have been logged out successfully.');
redirect(base_url('login.php'));
