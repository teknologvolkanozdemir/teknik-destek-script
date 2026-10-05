<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
