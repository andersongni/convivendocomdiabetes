<?php
/**
 * Readiness probe para Railway (blue/green / zero-downtime).
 * So retorna 200 quando o container alcanca o MySQL — deploy novo nao
 * recebe trafego se o boot/DB falhar (enquanto o anterior ainda puder servir).
 *
 * Liveness estatico continua em /ccdhealth (Alias Apache).
 */
declare(strict_types=1);

header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=UTF-8');

$host_env = getenv('WORDPRESS_DB_HOST') ?: '';
$user     = getenv('WORDPRESS_DB_USER') ?: '';
$pass     = getenv('WORDPRESS_DB_PASSWORD') ?: '';
$name     = getenv('WORDPRESS_DB_NAME') ?: '';

if ($host_env === '' || $user === '' || $name === '') {
	http_response_code(503);
	echo "missing-db-env\n";
	exit;
}

$host = $host_env;
$port = 3306;
if (str_contains($host_env, ':')) {
	$parts = explode(':', $host_env, 2);
	$host  = $parts[0];
	$port  = (int) $parts[1];
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = mysqli_init();
if ($mysqli === false) {
	http_response_code(503);
	echo "mysqli-init\n";
	exit;
}
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
if (!$mysqli->real_connect($host, $user, $pass, $name, $port)) {
	http_response_code(503);
	echo "db-down\n";
	exit;
}
$mysqli->close();

if (!is_readable('/var/www/html/wp-config.php') && !is_readable('/var/www/html/wp-load.php')) {
	http_response_code(503);
	echo "wp-missing\n";
	exit;
}

http_response_code(200);
echo "ok\n";
