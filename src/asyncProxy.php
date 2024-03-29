<?php
$loop = new EvLoop();
$w = new EvIo(
    $sock,
    Ev::READ,
    function ($w) use ($loop) {
        $conn = stream_socket_accept($w->fd, 0);
        new EvIo(
            $conn,
            Ev::READ,
            function ($w) use ($loop) {
                $data = fread($w->fd, 8192);
                if ($data === '') {
                    $w->stop();
                    fclose($w->fd);
                } else {
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        if ($_SERVER['REQUEST_URI'] === '/') {
                            $data = file_get_contents(dirname(__FILE__) . '/bare-server.json');
                            http_response_code(200);
                            header('Content-Type: application/json');
                            echo $data;
                        } else {
                            if (str_starts_with($_SERVER['REQUEST_URI'], "/v3")) {
                                if (isset($_SERVER['HTTP_X_BARE_URL'])) {
                                    $url = $_SERVER['HTTP_X_BARE_URL'];
                                    $headers = isset($_SERVER['HTTP_X_BARE_HEADERS']) ? json_decode($_SERVER['HTTP_X_BARE_HEADERS'], true) : [];
                                    if (isset($_SERVER['HTTP_X_BARE_FORWARD_HEADERS'])) {
                                        $forward_headers = json_decode($_SERVER['HTTP_X_BARE_FORWARD_HEADERS'], true);
                                        foreach ($forward_headers as $header) {
                                            if (isset($_SERVER['HTTP_' . $header])) {
                                                $headers[$header] = $_SERVER['HTTP_' . $header];
                                            }
                                        }
                                    }
                                    $opts = [
                                        'http' => [
                                            'method' => "GET",
                                            'header' => $headers,
                                        ],
                                    ];
                                    $context = stream_context_create($opts);
                                    $resp = file_get_contents($url, false, $context);
                                    $resp_headers = $http_response_header;
                                    if (isset($_SERVER['HTTP_X_BARE_PASS_STATUS'])) {
                                        $pass_status = json_decode($_SERVER['HTTP_X_BARE_PASS_STATUS'], true);
                                        if (in_array(intval(substr($http_response_header[0], 9, 3)), $pass_status)) {
                                            header($http_response_header[0]);
                                        } else {
                                            http_response_code(200);
                                        }
                                    } else {
                                        http_response_code(200);
                                    }
                                    header('Cache-Control: no-cache');
                                    header('ETag: ' . (isset($resp_headers['ETag']) ? $resp_headers['ETag'] : ''));
                                    header('Content-Encoding: ' . (isset($resp_headers['Content-Encoding']) ? $resp_headers['Content-Encoding'] : ''));
                                    header('Content-Length: ' . (isset($resp_headers['Content-Length']) ? $resp_headers['Content-Length'] : ''));
                                    header('X-Bare-Status: ' . intval(substr($http_response_header[0], 9, 3)));
                                    header('X-Bare-Status-Text: ' . substr($http_response_header[0], 13));
                                    header('X-Bare-Headers: ' . json_encode($resp_headers));
                                    if (isset($_SERVER['HTTP_X_BARE_PASS_HEADERS'])) {
                                        $pass_headers = json_decode($_SERVER['HTTP_X_BARE_PASS_HEADERS'], true);
                                        foreach ($pass_headers as $header) {
                                            foreach ($resp_headers as $resp_header) {
                                                if (strpos($resp_header, $header) === 0) {
                                                    header($resp_header);
                                                }
                                            }
                                        }
                                    }
                                    echo $resp;
                                } else {
                                    http_response_code(400);
                                    echo "Missing x-bare-url header";
                                }
                            } else {
                                http_response_code(404);
                                echo "404";
                            }
                        }
                    }
                }
            },
            $loop
        );
    },
    $loop
);

$loop->run();
?>
