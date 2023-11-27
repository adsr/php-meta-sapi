#!/usr/bin/env php
<?php
declare(strict_types=1);

(new class {
    private ?string $libphp_path = null;
    private ?FFI $ffi = null;
    private ?FFI\CData $module = null;
    private array $free_list = [];

    public function run(): void {
        $this->initFfi();
        $this->initPhp();
        $this->loop();
        $this->deinitPhp();
    }

    private function initFfi(): void {
        $libphp_so = $this->getLibPhp();
        $cdefs = $this->getLibPhpCDefs();
        $this->ffi = FFI::cdef($cdefs, $libphp_so);
    }

    private function initPhp(): void {
        $this->initPhpModule();
        $this->ffi->zend_signal_startup();
        $this->ffi->sapi_startup(FFI::addr($this->module));
        call_user_func($this->module->startup, FFI::addr($this->module));
    }

    private function loop(): void {
        $history_file = sprintf('.%s-history', basename(__FILE__, '.php'));
        readline_read_history($history_file);

        while (!in_array(($line = readline('php_meta_sapi > ')), [false, 'quit', 'exit'], true)) {
            $this->ffi->php_request_startup();

            if (file_exists($line)) {
                $fh = $this->ffi->new('struct zend_file_handle');
                $this->ffi->zend_stream_init_filename(FFI::addr($fh), $line);
                $this->ffi->php_execute_script(FFI::addr($fh));

                $this->ffi->zend_destroy_file_handle(FFI::addr($fh));
            } else {
                $code = sprintf("(function() {\n%s;\n})()", $line);
                $zv = $this->ffi->new('struct zval');
                $zvp = FFI::addr($zv);
                $this->ffi->zend_eval_stringl_ex($code, strlen($code), $zvp, 'php_meta_sapi', true);
                if ($this->ffi->zend_zval_type_name($zvp) !== 'null') {
                    $this->ffi->php_var_dump($zvp, 0);
                }
            }

            if (strlen(trim($line)) > 0) {
                readline_add_history($line);
            }

            $this->ffi->php_request_shutdown(NULL);
        }
        // TODO handle SIGINT

        readline_write_history($history_file);
    }

    private function initPhpModule(): void {
        $this->module = $this->ffi->new('struct sapi_module_struct');
        FFI::memset(FFI::addr($this->module), 0, FFI::sizeof($this->module));
        $this->module->name = $this->cString('meta');
        $this->module->pretty_name = $this->cString('PHP meta-SAPI');
        $this->module->startup = function($module) {
            $this->module->ini_entries = $this->cString(<<<EOD
                html_errors=0
                implicit_flush=1
                output_buffering=0
                max_execution_time=0
                max_input_time=-1
            EOD
            );
            $this->ffi->php_module_startup($module, NULL);
        };
        $this->module->shutdown = $this->ffi->php_module_shutdown_wrapper;
        $this->module->ub_write = function(string $str, int $len) {
            // printf("ub_write: %s\n", $str);
            echo $str;
        };
        $this->module->sapi_error = function($type, $fmt) { // TODO variadic
            fwrite(STDERR, "sapi_error($type): $fmt\n");
        };
        $this->module->send_header = function($hdr, $ctx) {
        };
        $this->module->read_cookies = function() {
            return NULL;
        };
        $this->module->register_server_variables = function($arr) {
        };
        $this->module->log_message = function($msg, $type) {
            printf("log_message(%d): %s\n", $type, $msg);
        };
    }

    private function cString(string $s): FFI\CData {
        $slen = strlen($s);
        $owned = false;
        $cdata = $this->ffi->new(sprintf('char[%d]', $slen + 1), $owned);
        FFI::memset(FFI::addr($cdata), 0, $slen + 1);
        FFI::memcpy($cdata, $s, $slen);
        $this->free_list[] = $cdata;
        return $cdata;
    }

    private function deinitPhp(): void {
        foreach ($this->free_list as $cdata) {
            FFI::free($cdata);
        }
        $this->ffi->php_module_shutdown();
        $this->ffi->sapi_shutdown();
    }

    private function getLibPhp(): string {
        if ($this->libphp_path !== null) {
            return $this->libphp_path;
        }
        $output = [];
        $exit_code = 0;
        exec('php-config --prefix', $output, $exit_code);
        if ($exit_code !== 0) {
            throw new RuntimeException('php-config --prefix failed');
        }
        return sprintf('%s/lib/libphp.so', $output[0]);
    }

    private function getLibPhpCDefs(): string {
        return <<<EOD
            struct zend_file_handle {
                uint8_t opaque[80];
            };
            struct zval {
                uint8_t opaque[16];
            };
            struct sapi_module_struct {
                char *name;
                char *pretty_name;
                int (*startup)(void *);
                int (*shutdown)(void *);
                int (*activate)(void);
                int (*deactivate)(void);
                size_t (*ub_write)(const char *, size_t);
                void (*flush)(void *);
                void *(*get_stat)(void);
                char *(*getenv)(const char *, size_t);
                void (*sapi_error)(int, const char *); // TODO variadic
                int (*header_handler)(void *, int, void *);
                int (*send_headers)(void *);
                void (*send_header)(void *, void *);
                size_t (*read_post)(char *, size_t);
                char *(*read_cookies)(void);
                void (*register_server_variables)(void *);
                void (*log_message)(const char *, int);
                void (*get_request_time)(double *);
                void (*terminate_process)(void);
                char *php_ini_path_override;
                void (*default_post_reader)(void);
                void (*treat_data)(int, char *, void *);
                char *executable_location;
                int php_ini_ignore;
                int php_ini_ignore_cwd;
                int (*get_fd)(int *);
                int (*force_http_10)(void);
                int (*get_target_uid)(void *);
                int (*get_target_gid)(void *);
                unsigned int (*input_filter)(int, const char *, char **, size_t, size_t *);
                void (*ini_defaults)(void *);
                int phpinfo_as_text;
                const char *ini_entries;
                const void *additional_functions;
                unsigned int (*input_filter_init)(void);
            };
            void zend_signal_startup(void);
            void sapi_startup(void *);
            void sapi_shutdown(void);
            int php_request_startup(void);
            void php_request_shutdown(void *);
            int php_module_startup(void *, void *);
            int php_module_shutdown(void);
            int php_module_shutdown_wrapper(void *);
            int zend_eval_stringl_ex(const char *code, size_t code_len, struct zval *retval, const char *name, bool handle_exceptions);
            void zend_stream_init_filename(struct zend_file_handle *, const char *);
            uint8_t php_execute_script(struct zend_file_handle *);
            void zend_destroy_file_handle(struct zend_file_handle *);
            void php_var_dump(struct zval *zv, int level);
            const char *zend_zval_type_name(struct zval *zv);
        EOD;
    }
})->run();
