### access the db via terminal
psql -U jervi -h localhost -p 5433 -d postgres

### pm2
pm2 start --name mirhadati-server "php artisan serve --host 0.0.0.0 --port 6959"

### Domain name
- sudo nano /etc/nginx/sites-available/mirhadati.octaprize.com
```
server {
    listen 80;
    server_name mirhadati.octaprize.com;

        # Block common WordPress attack paths
    location ~* ^/(wp-admin|wp-login.php|wp-content|wp-includes|wp-json|wp-cron.php|wp-config.php|cgi-bin|xmrlpc.php) {
        return 403;
    }

    # Allow Let's Encrypt SSL renewals but block other access
    location ^~ /.well-known/acme-challenge/ {
        allow all;
    }
    location ~* ^/.well-known/ {
        return 403;
    }

    # Block direct access to PHP files except index.php
    location ~* \.php$ {
        if ($uri !~ "^/index.php$") {
            return 403;
        }
    }

    # Block empty User-Agent requests (common bot behavior)
    if ($http_user_agent = "") {
        return 403;
    }

    # Block bad bots (list can be expanded)
    if ($http_user_agent ~* (crawler|scrapy|spider|nmap|java|masscan|curl|wget|hydra|nikto|flood|sqlmap|acunetix|wpscan|wordpress|wordpressscan) ) {
        return 403;
    }

    # Block common attack patterns
    location ~* ^/(admin|adminer|phpmyadmin|config.php|setup.php|shell.php) {
        return 403;
    }

    location / {
        proxy_pass http://localhost:6959;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        proxy_buffer_size 128k;
        proxy_buffers 4 256k;
        proxy_busy_buffers_size 256k;

    }
}
```
- sudo ln -s /etc/nginx/sites-available/mirhadati.octaprize.com /etc/nginx/sites-enabled/
- sudo nginx -t
- sudo systemctl restart nginx
- sudo certbot --nginx -d mirhadati.octaprize.com
- sudo nano /etc/nginx/sites-available/mirhadati.octaprize.com

