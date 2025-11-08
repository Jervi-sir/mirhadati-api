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

    # 🔓 allow larger requests (pick a size you’re comfy with)
    client_max_body_size 25M;
    client_body_buffer_size 256k;
    client_body_timeout 300s;

    # ... your existing blocks ...

    location / {
        proxy_pass http://localhost:6959;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # 🧠 streaming uploads to upstream (don’t buffer at Nginx)
        proxy_request_buffering off;

        # (optional) if buffering is on, don’t spill to temp files
        proxy_max_temp_file_size 0;

        # timeouts for slow mobile networks
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;

        proxy_buffer_size 128k;
        proxy_buffers 4 256k;
        proxy_busy_buffers_size 256k;

        proxy_http_version 1.1;
        proxy_set_header Connection "";
    }
}

```
- sudo ln -s /etc/nginx/sites-available/mirhadati.octaprize.com /etc/nginx/sites-enabled/
- sudo nginx -t
- sudo systemctl restart nginx
- sudo certbot --nginx -d mirhadati.octaprize.com
- sudo nano /etc/nginx/sites-available/mirhadati.octaprize.com

