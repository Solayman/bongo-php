<?php

namespace System\Console;

/**
 * php bongo benchmark: real request timings, so docs and decisions use measured numbers.
 *
 *   php bongo benchmark                       // "/" and "/api/health", OpCache off and on
 *   php bongo benchmark --url=/products --requests=500 --opcache=on
 *
 * Each mode starts its own PHP server, warms it up, then sends the requests one by one
 * and reports median, p95 and requests per second. Memory is the peak of one in-process request.
 */
class Benchmark
{
    public static function run(array $urls, int $requests, string $opcache): int
    {
        $modes = match ($opcache) {
            'on' => [true],
            'off' => [false],
            default => [false, true],
        };
        if (in_array(true, $modes, true) && !self::opcacheAvailable()) {
            echo "\033[33mOpCache is not installed for this PHP, so only \"off\" is measured.\033[0m" . PHP_EOL;
            $modes = [false];
        }

        echo 'Bongo ' . \System\Framework::version() . ' · PHP ' . PHP_VERSION . ' · ' . PHP_OS_FAMILY
            . " · $requests requests per URL, one at a time" . PHP_EOL . PHP_EOL;
        printf("%-24s %-8s %10s %10s %8s %10s %8s\n", 'URL', 'OpCache', 'median', 'p95', 'req/s', 'memory', 'status');

        $memory = [];
        $limited = false;
        foreach ($modes as $withOpcache) {
            [$server, $base] = self::startServer($withOpcache);
            try {
                foreach ($urls as $url) {
                    $result = self::measure($base . $url, $requests);
                    if ($result === null) {
                        printf("%-24s %-8s  \033[31mfailed: no response (is the database running?)\033[0m\n", $url, $withOpcache ? 'on' : 'off');
                        continue;
                    }
                    $times = $result['times'];
                    sort($times);
                    $status = (int) array_key_first($result['statuses']);
                    $limited = $limited || isset($result['statuses'][429]);
                    printf(
                        "%-24s %-8s %8.1fms %8.1fms %8d %10s %8s\n",
                        $url,
                        $withOpcache ? 'on' : 'off',
                        $times[intdiv(count($times), 2)],
                        $times[max(0, (int) ceil(count($times) * 0.95) - 1)],
                        (int) round(1000 / max(array_sum($times) / count($times), 0.001)),
                        $memory[$url] ??= self::memory($url),
                        count($result['statuses']) > 1 ? "$status+" : $status
                    );
                }
            } finally {
                proc_terminate($server);
                proc_close($server);
            }
        }

        echo PHP_EOL . 'Times include the PHP built-in server. Production servers (nginx + PHP-FPM) are usually faster.' . PHP_EOL;
        if ($limited) {
            echo "\033[33mSome answers were 429 (rate limit), so those times measure the limiter, not the page."
                . " Benchmark a route without throttle, or raise its limit while measuring.\033[0m" . PHP_EOL;
        }
        if (!in_array(true, $modes, true) || PHP_OS_FAMILY === 'Windows') {
            echo 'Tip: on Linux production servers, enable OpCache and preloading (see DOCUMENTATION.md, "Performance").' . PHP_EOL;
        }
        return 0;
    }

    private static function opcacheAvailable(): bool
    {
        if (extension_loaded('Zend OPcache')) {
            return true;
        }
        exec(escapeshellarg(PHP_BINARY) . ' -d zend_extension=opcache -r "echo (int) extension_loaded(\'Zend OPcache\');" 2>&1', $out);
        return ($out[0] ?? '') === '1';
    }

    /** @return array{0: resource, 1: string} */
    private static function startServer(bool $opcache): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $cmd = [PHP_BINARY, '-d', 'opcache.enable=' . (int) $opcache, '-d', 'opcache.enable_cli=' . (int) $opcache];
        if ($opcache && !extension_loaded('Zend OPcache')) {
            array_splice($cmd, 1, 0, ['-d', 'zend_extension=opcache']);
        }
        // The server needs the same database extension as this process (php -d extension=pdo_pgsql bongo benchmark)
        foreach (['pdo_sqlite', 'pdo_pgsql'] as $ext) {
            if (extension_loaded($ext)) {
                array_push($cmd, '-d', "extension=$ext");
            }
        }
        array_push($cmd, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public');

        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $server = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, BASE_PATH);
        for ($i = 0; $i < 50; $i++) {
            if ($conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1)) {
                fclose($conn);
                return [$server, "http://127.0.0.1:$port"];
            }
            usleep(100000);
        }
        proc_terminate($server);
        throw new \RuntimeException('Could not start the benchmark server');
    }

    /** Milliseconds per request, or null when the URL does not answer */
    private static function measure(string $url, int $requests): ?array
    {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0]]);
        for ($i = 0; $i < 10; $i++) {   // warm-up: compiled views, OpCache, connections
            if (@file_get_contents($url, false, $context) === false) {
                return null;
            }
        }
        $times = [];
        $statuses = [];
        for ($i = 0; $i < $requests; $i++) {
            $start = hrtime(true);
            @file_get_contents($url, false, $context);
            $times[] = (hrtime(true) - $start) / 1e6;
            $status = (int) (explode(' ', $http_response_header[0] ?? '')[1] ?? 0);
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }
        arsort($statuses);
        return ['times' => $times, 'statuses' => $statuses];
    }

    /** Peak memory of one request, run inside a fresh PHP process */
    private static function memory(string $url): string
    {
        $code = '$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REQUEST_URI"]=' . var_export($url, true)
            . ';$_SERVER["SCRIPT_NAME"]="/index.php";$_SERVER["HTTP_HOST"]="127.0.0.1";'
            . 'register_shutdown_function(function(){ob_end_clean();fwrite(STDERR,"BONGO_MEM:".memory_get_peak_usage());});'
            . 'ob_start();chdir(' . var_export(BASE_PATH . '/public', true) . ');require "index.php";';
        $cmd = [PHP_BINARY];
        foreach (['pdo_sqlite', 'pdo_pgsql'] as $ext) {
            if (extension_loaded($ext)) {
                array_push($cmd, '-d', "extension=$ext");
            }
        }
        array_push($cmd, '-r', $code);
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
        stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($process);
        return preg_match('/BONGO_MEM:(\d+)/', $err, $m) ? round($m[1] / 1048576, 2) . ' MB' : '?';
    }
}
