<?php
/**
 * file   : constants.php
 * path   : C:\xampp\htdocs\backuprm\config\constants.php
 * fungsi : Definisi konstanta global (APP, path, URL, session, role, log)
 */
declare(strict_types=1);

define('APP_NAME',    env('APP_NAME', 'Backup RM'));
define('APP_VERSION', '1.0.0');

define('URL_ASSETS',  BASE_URL . '/assets');
define('URL_BACKUPS', BASE_URL . '/backups');

define('PATH_BACKUPS', BASE_PATH . '/backups');
define('PATH_LOGS',    BASE_PATH . '/logs');
define('PATH_UPLOADS', BASE_PATH . '/uploads');

define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 7200));
define('SESSION_NAME',     env('SESSION_NAME', 'BACKUPRM_SESS'));

define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', 52428800));

define('ROLE_ADMIN', 'admin');
define('ROLE_USER',  'user');

define('LOG_ACTIVITY', PATH_LOGS . '/activity.log');
define('LOG_ERROR',    PATH_LOGS . '/error.log');