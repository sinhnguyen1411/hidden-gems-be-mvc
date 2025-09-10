Development TLS Certificates

This folder is for local development TLS certs used by Nginx (`/etc/nginx/certs`).

Quick generate self-signed (OpenSSL):

Linux/macOS (shell):

  openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout server.key -out server.crt \
    -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

Windows (PowerShell, using OpenSSL if available):

  openssl req -x509 -nodes -days 365 -newkey rsa:2048 `
    -keyout server.key -out server.crt `
    -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

Alternatively, use mkcert for trusted local certs.

