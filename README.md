# php-meta-sapi

This is a PHP SAPI written in PHP via FFI, or in other words, PHP embedded in itself.

### Synopsis

    $ cat test.php
    <?php

    echo "Hello from inside PHP meta-SAPI\n";
    $ ./php-meta-sapi.php
    php_meta_sapi > return 42
    int(42)
    php_meta_sapi > return 'a'
    string(1) "a"
    php_meta_sapi > function f() { echo "43\n"; } f();
    43
    php_meta_sapi > test.php
    Hello from inside PHP meta-SAPI
    php_meta_sapi > ^C
    $

### Requirements

* PHP 8.3.x compiled with `--with-readline` and `--enable-embed`
