<?php
/**
 * Generator stub .php untuk routing tanpa .htaccess / tanpa rewrite.
 * Jalankan:  php tools/generate-stubs.php
 *
 * Sumber kebenaran route ada di:
 *   - bootstrap/route_map.php    (route halaman)
 *   - bootstrap/action_map.php   (route action)
 *
 * Stub yang dibuat hanya satu baris:
 *     halaman: <?php require_once __DIR__ . '/route.php';
 *     action : <?php require_once __DIR__ . '/../route.php';
 *
 * Stub yang sudah ada TIDAK ditimpa kalau isinya bukan stub.
 */
declare(strict_types=1);

$root = dirname(__DIR__);

$pages   = require $root . '/bootstrap/route_map.php';
$actions = require $root . '/bootstrap/action_map.php';

$stubPage   = '<?php require_once __DIR__ . ' . "'/route.php';";
$stubAction = '<?php require_once __DIR__ . ' . "'/../route.php';";

echo 'Route halaman : ' . count($pages) . ' -> ' . implode(', ', array_keys($pages)) . "\n";
echo 'Route action  : ' . count($actions) . ' -> ' . implode(', ', array_keys($actions)) . "\n\n";

$make = function (string $file, string $stub): bool {
    // Selalu tulis ulang stub (tidak pernah skip). Stub dibuat ulang setiap
    // kali generator dijalankan, jadi tidak akan ikut dapat baris guard
    // internal_guard.php yang disisipkan ke file actions/ dan pages/.
    file_put_contents($file, $stub . "\n");
    return true;
};

$made = 0;
foreach (array_keys($pages) as $route) {
    if ($make($root . '/' . $route . '.php', $stubPage)) $made++;
}
echo "Stub halaman dibuat/ diperbarui: $made\n";

$madeA = 0;
foreach (array_keys($actions) as $route) {
    if ($make($root . '/actions/' . $route . '.php', $stubAction)) $madeA++;
}
echo "Stub action dibuat/ diperbarui : $madeA\n";

echo "\nAkses aplikasi (tanpa .htaccess, tanpa rewrite):\n";
echo "  https://domain/rekam-medis/login.php\n";
echo "  https://domain/rekam-medis/dashboard.php\n";
echo "  https://domain/rekam-medis/actions/login.php\n";