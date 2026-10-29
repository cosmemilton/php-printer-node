<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $prefix='Acbr\\Printer\\';
    if (str_starts_with($class,$prefix)) {
        $name=substr($class,strlen($prefix));
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D',$name)) require __DIR__.'/../src/'.$name.'.php';
    }
});
require_once __DIR__.'/../src/functions.php';
