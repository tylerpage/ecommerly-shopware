<?php declare(strict_types=1);

/**
 * Finds a Shopware autoloader wherever this plugin happens to live.
 *
 * Installed under custom/plugins the shop's own vendor directory is
 * four levels up, which is the layout CI and a developer's local shop
 * both use. Checked out on its own — reviewing a pull request, say —
 * there is no shop above it, so SHOPWARE_ROOT names one. Either way
 * the plugin's own classes are autoloaded from src/ below, so the
 * tests that touch no Shopware class run in a bare checkout.
 */
$autoloaders = array_filter([
    __DIR__.'/../../../../vendor/autoload.php',
    getenv('SHOPWARE_ROOT') ? getenv('SHOPWARE_ROOT').'/vendor/autoload.php' : null,
    __DIR__.'/../vendor/autoload.php',
]);

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require_once $autoloader;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    foreach (['Ecommerly\\Connector\\Tests\\' => __DIR__, 'Ecommerly\\Connector\\' => __DIR__.'/../src'] as $prefix => $root) {
        if (str_starts_with($class, $prefix)) {
            $path = $root.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            if (is_file($path)) {
                require_once $path;
            }

            return;
        }
    }
});
