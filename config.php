<?php
require_once __DIR__ . '/includes/env.php';

$tz = app_env('APP_TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set($tz);
error_reporting(E_ALL);
ini_set('display_errors', app_env_bool('APP_DEBUG', false) ? '1' : '0');
ini_set('log_errors', '1');

define('APP_NAME', app_env('APP_NAME', 'Sucesso no Rádio'));
