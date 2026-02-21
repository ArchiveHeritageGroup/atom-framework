server {
    listen 80;
    server_name {{SERVER_NAME}};
    root {{ATOM_PATH}};
    index index.php;

    client_max_body_size 72M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

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

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ /\. {
        deny all;
    }
}
