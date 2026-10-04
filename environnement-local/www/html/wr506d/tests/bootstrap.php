<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// `.env` n'est pas versionné (il porte des valeurs locales). Sur un clone neuf
// ou en CI, les variables viennent de l'environnement et de phpunit.dist.xml.
if (method_exists(Dotenv::class, 'bootEnv') && is_file(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
} else {
    $_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ?? 'test';
    $_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ?? '1';
    $_SERVER['APP_SECRET'] ??= $_ENV['APP_SECRET'] ?? bin2hex(random_bytes(16));
}

// Clés JWT jetables, propres à la suite de tests : jamais les clés locales ou
// de production. Générées une fois dans var/ (non versionné).
$jwtDir = dirname(__DIR__).'/var/test-jwt';
$passphrase = 'test-only-passphrase';
if (!is_file($jwtDir.'/private.pem') || !is_file($jwtDir.'/public.pem')) {
    if (!is_dir($jwtDir)) {
        mkdir($jwtDir, 0700, true);
    }
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privatePem, $passphrase);
    file_put_contents($jwtDir.'/private.pem', $privatePem);
    file_put_contents($jwtDir.'/public.pem', openssl_pkey_get_details($key)['key']);
}
foreach ([
    'JWT_SECRET_KEY' => $jwtDir.'/private.pem',
    'JWT_PUBLIC_KEY' => $jwtDir.'/public.pem',
    'JWT_PASSPHRASE' => $passphrase,
] as $name => $value) {
    $_SERVER[$name] = $_ENV[$name] = $value;
    putenv($name.'='.$value);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
