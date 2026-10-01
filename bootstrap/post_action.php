<?php
/**
 * file   : post_action.php
 * path   : bootstrap/post_action.php
 * fungsi : Eksekusi file action jika route mengarah ke /actions/*
 */
declare(strict_types=1);

require_once __DIR__ . '/internal_guard.php';

if (!empty($_SERVER['ACTION_FILE'])) {
    if (file_exists($_SERVER['ACTION_FILE'])) {
        require_once $_SERVER['ACTION_FILE'];
        exit;
    } else {
        http_response_code(404);
        echo "<h3>Action Error:</h3> File fisik tidak ditemukan di path:<br><code>" . htmlspecialchars($_SERVER['ACTION_FILE']) . "</code>";
        exit;
    }
}