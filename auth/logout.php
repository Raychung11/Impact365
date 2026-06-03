<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/auth.php';

logout();
start_session();
flash('success', 'You have been signed out.');
redirect('auth/login.php');
