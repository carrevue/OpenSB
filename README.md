# OpenSB

## Our official instance

[SquareBracket.pw](https://squarebracket.pw/)

Alternative domains: [squarebracket.bluffingo.net](https://squarebracket.bluffingo.net/), [FulpTube.rocks](https://fulptube.rocks)

## How to setup an OpenSB instance.

I wouldn't recommend using this code unless if you ***really*** know what you're doing.

1. Get a webserver (Apache or NGINX) with PHP and MariaDB or MySQL up and running, including Composer and the PHP GD library extension. We recommend Apache, as you will experience issues with NGINX.
1. Configure your webserver. Look below the steps for an example.
1. Run `composer update` from the terminal.
1. Copy `config.sample.php` in `private/config`, rename it to `config.php` and fill in your database credentials.
1. Import the database template found in `sql/` into the database you want to use.
1. Run the `compile-scss` script available in the tools directory to generate the required stylesheets. You may find Dart-Sass here at https://sass-lang.com/install. Ruby Sass is deprecated, do not use it. You MUST use a specific Dart-Sass, or it won't work. You can find it [here](https://github.com/sass/dart-sass/releases/).

### Production specific

1. Use Linux for anything related to production.
1. Instead of installing dependencies using `composer update` you do `composer update --no-dev`
1. Make the `dynamic/` and `templates/cache/` directories writable by your web server.
1. Modify branding settings to replace the default OpenSB branding with your custom branding. Check the `public/assets/placeholder` directory for reference.

### Development specific

1. Enable debugging features by setting `$isDebug` to true.
1. If you want to be able to upload during development, make the `dynamic/` directory and the directories inside it writable by your web server.

### Apache's Virtual host example
You will have to modify the directories to match your instance's location.
```
<VirtualHost *> 
    ServerName localhost
    DocumentRoot "C:/xampp/openSB/public"

    Alias /dynamic "C:/xampp/openSB/dynamic"

    <Directory "C:/xampp/openSB">
        Options Indexes FollowSymLinks
        Require all granted
        AllowOverride All
    </Directory>
</VirtualHost>
```

### NGINX's config example
Please note that this example uses `php-fpm`.
You will have to modify the directories to match your instance's location.
```
server {
    listen       80;
    server_name  www.squarebracket.pw
    server_name  squarebracket.pw;

    root   /var/www/squarebracket/public/;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location /dynamic/ {
        root /var/www/squarebracket/dynamic/;
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_index index.php;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```
