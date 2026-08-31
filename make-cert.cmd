@echo off
REM Genera un certificado X.509 autofirmado para el servidor HTTPS local.
REM Salida: certs\server.crt y certs\server.key
REM Uso:  .\make-cert.cmd

where openssl >nul 2>nul
if errorlevel 1 (
    set "OPENSSL=C:\xampp\apache\bin\openssl.exe"
) else (
    set "OPENSSL=openssl"
)

if not exist certs mkdir certs

REM Si existe un openssl.cnf de XAMPP se usa para evitar errores de config.
set "CONF="
if exist "C:\xampp\apache\conf\openssl.cnf" set "CONF=-config C:\xampp\apache\conf\openssl.cnf"

"%OPENSSL%" req -x509 -nodes -newkey rsa:2048 -keyout certs\server.key ^
    -out certs\server.crt -days 3650 -sha256 %CONF% ^
    -subj "/C=AR/ST=Buenos Aires/L=CABA/O=Programacion 4/CN=localhost" ^
    -addext "subjectAltName=DNS:localhost,IP:127.0.0.1,IP:::1"

echo.
echo [OK] Certificados generados:
echo   certs\server.crt
echo   certs\server.key
