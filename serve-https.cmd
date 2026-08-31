@echo off
REM Levanta la API en HTTPS usando un proxy TLS en PHP puro.
REM
REM El servidor built-in de PHP en Windows NO soporta TLS, asi que se arrancan
REM dos procesos:
REM   1) Worker del built-in server en HTTP:        127.0.0.1:8080  (sin TLS)
REM   2) Proxy TLS -> https://localhost:8443        127.0.0.1:8443  (TLS)
REM
REM Uso:
REM   serve-https.cmd                 -> https://localhost:8443  (driver sqlite)
REM   serve-https.cmd memory          -> driver en memoria
REM   serve-https.cmd sqlite 9000     -> HTTPS en https://localhost:9000
REM
REM Variables opcionales:  set JWT_SECRET=...   set JWT_TTL_SECONDS=...

set HTTPS_PORT=8443
set HTTP_PORT=8080
set DRIVER=sqlite
if not "%1"=="" set DRIVER=%1
if not "%2"=="" set HTTPS_PORT=%2
set REPOSITORY_DRIVER=%DRIVER%

if not exist certs\server.crt (
    echo [ERROR] No existen certs\server.crt ^| certs\server.key
    echo         Generalos primero con: make-cert.cmd
    exit /b 1
)

echo [ok] Worker  (HTTP)      : http://127.0.0.1:%HTTP_PORT%
echo [ok] Proxy   (TLS/HTTPS) : https://localhost:%HTTPS_PORT%
echo       driver: %DRIVER%   ^|   Apache Nginx no requerido
echo.
echo Detiene todo con Ctrl+C o cerrando esta ventana.

REM Arranca el worker HTTP (built-in server normal) en segundo plano.
set WORKER_PID=
start "API-worker-HTTP" /min cmd /c "php -S 127.0.0.1:%HTTP_PORT% -t public >%TEMP%\api-worker.log 2>&1"
timeout /t 1 /nobreak >nul

REM Arranca el proxy TLS en primer plano (es el que queda visible).
php https-proxy.php %HTTPS_PORT% %HTTP_PORT% certs\server.crt certs\server.key

REM Al cerrar el proxy, detener el worker.
taskkill /F /FI "WINDOWTITLE eq API-worker-HTTP*" >nul 2>&1
