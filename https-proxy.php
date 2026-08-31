<?php

declare(strict_types=1);

/*
 * PROXY REVERSO TLS en PHP puro (sin extensiones externas ni software a instalar).
 *
 * Contexto: el servidor built-in de PHP en WINDOWS no soporta TLS (lo confirma
 * el error "Unsupported SSL request" al usar --cert). Este script es un servidor
 * TLS mínimo: escucha en https://localhost:8443, descifra TLS con el certificado
 * autofirmado y reenvía cada petición HTTP a un worker del servidor built-in
 * que corre en http://127.0.0.1:<BACKEND_PORT> (sin TLS).
 *
 * Flujo:
 *   cliente HTTPS  ->  este proxy (TLS, 8443)  ->  PHP built-in server (HTTP 8080)
 *
 * OJO con un matiz: utilizamos HTTP/1.1 no reutilizando conexiones (Connection: close)
 * aguas arriba para simplificar el reenvío sin framear multiples requests por socket.
 *
 * Solo escucha en 127.0.0.1 (interfaz local) por seguridad.
 *
 * Ejecución (la hace automaticamente serve-https.cmd):
 *   php https-proxy.php 8443 8080 certs/server.crt certs/server.key
 *
 * Este codigo es DIDACTICO (una clase de Programacion 4). En produccion se usa
 * Nginx/Caddy/Apache con TLS o un tunnel gestionado por una CA publica.
 */

$listenPort = (int) ($argv[1] ?? 8443);
$backendPort = (int) ($argv[2] ?? 8080);
$certFile = (string) ($argv[3] ?? 'certs/server.crt');
$keyFile = (string) ($argv[4] ?? 'certs/server.key');

foreach ([$certFile, $keyFile] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "[ERROR] No existe el archivo: $f\n");
        exit(1);
    }
}

$context = stream_context_create([
    'ssl' => [
        'local_cert'        => $certFile,
        'local_pk'          => $keyFile,
        'allow_self_signed' => true,
        'verify_peer'       => false,
        'verify_peer_name'  => false,
    ],
]);

// Solo interfaz local: nunca exponer este proxy a la red.
$address = "tls://127.0.0.1:{$listenPort}";
$server = stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "[ERROR] No se pudo abrir TLS en $address: $errstr ($errno)\n");
    exit(1);
}

fwrite(STDOUT, "[OK] Proxy TLS escuchando en https://localhost:{$listenPort} -> http://127.0.0.1:{$backendPort}\n");
fwrite(STDOUT, "     Presione Ctrl+C para detener.\n\n");

$backendAddr = "tcp://127.0.0.1:{$backendPort}";

while (true) {
    // 1) Aceptar y completar el handshake TLS con el cliente.
    $client = @stream_socket_accept($server, -1);
    if ($client === false) {
        continue;
    }
    stream_set_timeout($client, 10);

    // 2) Leer la peticion HTTP completa del cliente (hasta \r\n\r\n).
    $request = '';
    while (!str_contains($request, "\r\n\r\n") && !feof($client)) {
        $chunk = fread($client, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $request .= $chunk;
    }

    if ($request === '' || !preg_match('#^(\S+)\s+(\S+)\s+HTTP/1\.[01]#i', $request, $m)) {
        // Peticion mal formada (p. ej. el error "Unsupported SSL request" de curl
        // cuando le hablan en claro). Responder 400 y cerrar.
        fwrite($client, "HTTP/1.1 400 Bad Request\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
        fclose($client);
        continue;
    }

    $method = $m[1];
    $target = $m[2];

    // 3) Separar cuerpo: en POST/PUT llega despues de la cabecera.
    [$head, $body] = explode("\r\n\r\n", $request, 2) + [1 => ''];

    // 4) Reescribir: target absoluto para el worker HTTP.
    $outbound = "$method $target HTTP/1.1\r\n";
    foreach (explode("\r\n", $head) as $line) {
        if ($line === '' || preg_match('#^HTTP/#i', $line)) {
            continue; // saltar request line original
        }
        // Mover Host al backend; demas cabeceras pasan tal cual.
        if (stripos($line, 'Host:') === 0) {
            continue;
        }
        if (stripos($line, 'Connection:') === 0) {
            continue;
        }
        // Ignorar X-Forwarded-* que pudiera inventar el cliente (evita spoofing).
        if (stripos($line, 'X-Forwarded-') === 0) {
            continue;
        }
        $outbound .= $line . "\r\n";
    }
    $outbound .= "Host: 127.0.0.1:{$backendPort}\r\n";
    $outbound .= "X-Forwarded-Proto: https\r\n"; // marca este reenvio como HTTPS seguro
    $outbound .= "Connection: close\r\n";
    $outbound .= "Content-Length: " . strlen($body) . "\r\n";
    $outbound .= "\r\n" . $body;

    // 5) Conectar al worker y reenviar.
    $upstream = @stream_socket_client($backendAddr, $berrno, $berrstr, 10);
    if ($upstream === false) {
        fwrite(STDERR, "[ERROR] No se pudo conectar al worker ($backendAddr): $berrstr ($berrno)\n");
        fwrite($client, "HTTP/1.1 502 Bad Gateway\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
        fclose($client);
        continue;
    }
    fwrite($upstream, $outbound);
    stream_set_timeout($upstream, 10);

    // 6) Recibir respuesta completa del worker.
    $response = '';
    while (!feof($upstream)) {
        $chunk = fread($upstream, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $response .= $chunk;
        // Dada Connection: close, se corta al fin del flujo; el worker no reusa.
    }
    fclose($upstream);

    // 7) Devolverla al cliente. (No nesesitamos reescribir Location ni nada:
    //    esta API solo responde JSON y el cliente usa https://localhost.)
    fwrite($client, $response);
    fclose($client);
}
