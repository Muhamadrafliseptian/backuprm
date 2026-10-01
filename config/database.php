<?php
/**
 * file   : database.php
 * path   : C:\xampp\htdocs\backuprm\config\database.php
 * fungsi : Koneksi PDO singleton ke database db_backuprm
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host    = env('DB_HOST', 'localhost');
    $port    = env('DB_PORT', '3306');
    $name    = env('DB_NAME', 'db_backuprm');
    $user    = env('DB_USER', 'root');
    $pass    = env('DB_PASS', '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
        ]);
    } catch (PDOException $e) {
        @file_put_contents(LOG_ERROR, '[' . date('Y-m-d H:i:s') . '] DB: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        if (env('APP_DEBUG') === 'true') {
            die('DB Error: ' . $e->getMessage());
        }
        die('Koneksi database gagal.');
    }

    return $pdo;
}