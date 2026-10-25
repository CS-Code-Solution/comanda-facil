<?php

// Local pilot router intentionally does not log request URLs (which may contain QR grants).
$public = realpath(__DIR__.'/../public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = realpath($public.'/'.urldecode($path));
if ($path !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $public.'/index.php';
