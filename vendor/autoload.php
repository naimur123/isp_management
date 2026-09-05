<?php
/**
 * Minimal PSR-4 autoloader for the bundled third-party libraries
 * (PhpOffice\PhpSpreadsheet and its runtime dependencies).
 *
 * This project ships its dependencies pre-vendored so that it can be
 * deployed to ordinary cPanel shared hosting via File Manager alone -
 * no Composer, SSH, or CLI access is required on the server.
 */

spl_autoload_register(function ($class) {
    static $prefixes = null;

    if ($prefixes === null) {
        $base = __DIR__;
        $prefixes = [
            'PhpOffice\\PhpSpreadsheet\\' => $base . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/',
            'Composer\\Pcre\\'            => $base . '/composer-pcre-src/src/',
            'ZipStream\\'                 => $base . '/maennchen-zipstream-src/src/',
            'Complex\\'                   => $base . '/markbaker-complex-src/src/',
            'Matrix\\'                    => $base . '/markbaker-matrix-src/src/',
            'Psr\\Http\\Client\\'         => $base . '/psr-http-client-src/src/',
            'Psr\\Http\\Message\\'        => $base . '/psr-http-message-src/src/',
            'Psr\\SimpleCache\\'          => $base . '/psr-simple-cache-src/src/',
        ];
    }

    foreach ($prefixes as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relative = substr($class, $len);
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});
