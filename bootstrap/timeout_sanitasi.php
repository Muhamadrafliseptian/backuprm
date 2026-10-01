<?php
/**
 * file   : timeout_sanitasi.php
 * path   : C:\xampp\htdocs\backuprm\bootstrap\timeout_sanitasi.php
 * fungsi : Trim semua input GET & POST dari spasi berlebih
 */
declare(strict_types=1);

$_GET  = array_map('trim', $_GET  ?? []);
$_POST = array_map('trim', $_POST ?? []);