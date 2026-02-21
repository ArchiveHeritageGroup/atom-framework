# HTTP -> HTTPS redirect
server {
    listen 80;
    server_name {{SERVER_NAME}};
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {{SERVER_NAME}};
    root {{ATOM_PATH}};
    index index.php;

    # SSL certificate paths (self-signed or Let's Encrypt)
    ssl_certificate /etc/ssl/atom-heratio/server.crt;
    ssl_certificate_key /etc/ssl/atom-heratio/server.key;

    # SSL settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    client_max_body_size 72M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/(index|qubit_dev)\.php(/|$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:{{FPM_SOCKET}};
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 120;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # IIIF/Media extensions (include if present)
    include /etc/nginx/snippets/atom-extensions.conf*;

    # Static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny hidden files
    location ~ /\. {
        deny all;
    }

    # Deny sensitive files
    location ~* (composer\.(json|lock)|package\.json|\.env|\.git) {
        deny all;
    }
}
