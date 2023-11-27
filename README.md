# php-meta-sapi

This is a PHP SAPI written in PHP via FFI, or in other words, PHP embedded in
itself.

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

### Details

Technically, `php-meta-sapi.php` loads `libphp.so` from the `embed` SAPI via FFI
to define and run a custom PHP-scriptable `meta` SAPI. The `loop` method could
be anything, but for this example, I implemented a simple REPL. Each line
entered in the REPL represents one request in the `meta` SAPI. In effect this is
similar to invoking `eval` but with more control over runtime behavior via SAPI
callbacks.

I've only tested `php-meta-sapi.php` via the `cli` SAPI, but if you subtract the
`readline` stuff it should work in other SAPIs as well.

As for when this might be useful, I don't know. It probably isn't useful beyond
educational content. I wrote it mainly to satisfy a personal curiosity.

Related TODOs:

* Add support for [variadic closures in FFI](https://github.com/php/php-src/blob/dfaf7986de0e3f8ae3a310b96213d99a14f34236/ext/ffi/ffi.c#L1001-L1004).
  This would allow us to properly implement the `sapi_error` callback.

* Experiment with loading FFI targets via `dlmopen` instead of `dlopen`
  [here](https://github.com/php/php-src/blob/dfaf7986de0e3f8ae3a310b96213d99a14f34236/ext/ffi/ffi.c#L2981).
  I think that would enable driving multiple SAPIs in the same process.
