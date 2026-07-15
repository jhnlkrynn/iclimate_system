<?php
try {
    $p = new PDO('mysql:host=127.0.0.1;port=3306;dbname=iclimate_laravel', 'root', '');
    $stmt = $p->query('SELECT COUNT(*) FROM migrations');
    echo "migrations count: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo 'FAIL: ' . $e->getMessage() . "\n";
}
try {
    $p = new PDO('mysql:host=127.0.0.1;port=3306;dbname=iclimate_laravel', 'root', '');
    $stmt = $p->query('SELECT COUNT(*) FROM users');
    echo "users count: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo 'FAIL users: ' . $e->getMessage() . "\n";
}
