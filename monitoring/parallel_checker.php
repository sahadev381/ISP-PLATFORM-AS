<?php
// monitoring/parallel_checker.php

/**
 * Perform parallel pings using proc_open.
 *
 * @param array $ips List of IP addresses to ping.
 * @param int $timeout Timeout in seconds for each ping.
 * @param int $concurrency Number of parallel processes.
 * @return array Associative array of [ip => status]
 */
function parallelPing($ips, $timeout = 2, $concurrency = 10) {
    $results = [];
    if (empty($ips)) return $results;

    $batches = array_chunk($ips, $concurrency);

    foreach ($batches as $batch) {
        $processes = [];
        $pipes_map = [];

        foreach ($batch as $ip) {
            $safe_ip = escapeshellarg($ip);
            // -c 1: one packet, -W $timeout: wait for response
            $command = "ping -c 1 -W $timeout $safe_ip";

            $descriptorspec = [
               0 => ["pipe", "r"], // stdin
               1 => ["pipe", "w"], // stdout
               2 => ["pipe", "w"]  // stderr
            ];

            $proc = proc_open($command, $descriptorspec, $pipes);
            if (is_resource($proc)) {
                $processes[$ip] = $proc;
                $pipes_map[$ip] = $pipes;
                // Close stdin immediately as we don't need it
                fclose($pipes[0]);
            } else {
                $results[$ip] = 'DOWN';
            }
        }

        while (count($processes) > 0) {
            foreach ($processes as $ip => $proc) {
                $status = proc_get_status($proc);
                if (!$status['running']) {
                    $results[$ip] = ($status['exitcode'] === 0) ? 'UP' : 'DOWN';

                    fclose($pipes_map[$ip][1]);
                    fclose($pipes_map[$ip][2]);
                    proc_close($proc);

                    unset($processes[$ip]);
                    unset($pipes_map[$ip]);
                }
            }
            if (count($processes) > 0) {
                usleep(10000); // 10ms wait to avoid CPU spiking
            }
        }
    }

    return $results;
}

/**
 * Perform parallel HTTP HEAD requests using curl_multi.
 *
 * @param array $urls List of URLs to check.
 * @param int $timeout Timeout in seconds for each request.
 * @param int $concurrency Number of parallel requests.
 * @return array Associative array of [url => status]
 */
function parallelHttpCheck($urls, $timeout = 10, $concurrency = 10) {
    $results = [];
    if (empty($urls)) return $results;

    $batches = array_chunk($urls, $concurrency);

    foreach ($batches as $batch) {
        $mh = curl_multi_init();
        $curl_handles = [];

        foreach ($batch as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

            curl_multi_add_handle($mh, $ch);
            $curl_handles[$url] = $ch;
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            } else {
                // If select fails, wait a bit
                usleep(100);
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach ($curl_handles as $url => $ch) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $results[$url] = ($http_code >= 200 && $http_code < 400) ? 'UP' : 'DOWN';

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    return $results;
}
