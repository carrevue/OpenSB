# OpenSB

<img src="https://raw.githubusercontent.com/carrevue/OpenSB/refs/heads/opensb-2.1/private/skins/trinium/default.png"></img>

OpenSB (Open SquareBracket) is a free and open-source PHP-based video/image sharing website script. Compared to other "video sharing site scripts", OpenSB aims to be lightweight and customizable. It is primarily used by the FulpTube/squareBracket platform.

## MAKE SURE YOU'RE GETTING OPENSB FROM THE CARREVUE DEVELOPMENT GITHUB!
We've gotten reports about an unofficial malicious backdoored version of OpenSB originating from Eastern Europe. The only official source for OpenSB is and will always be the Carrevue Development GitHub organization. We are not responsible for anything that happens using that unofficial backdoored version.

## How to setup an OpenSB instance.

1. Get a webserver (Apache or NGINX) with PHP and MariaDB or MySQL up and running, including Composer, the PHP GD library extension, and the PHP intl extension.
1. Configure your webserver. Look below the steps for an example.
1. Run `git submodule init` and `git submodule update` from the terminal.
1. Run `composer update` from the terminal.
1. Copy `config.sample.php` in `private/config`, rename it to `config.php` and fill in your database credentials.
1. Import the database template found in `sql/` into the database you want to use.
1. Run the `compile-scss` shell script available in the tools directory to generate the required stylesheets. You may find Dart-Sass here at https://github.com/sass/dart-sass/releases/. Ruby Sass is deprecated, do not use it.

### Production specific

1. Use Linux for anything related to production.
1. Instead of installing dependencies using `composer update` you do `composer update --no-dev`
1. Make the `dynamic/` and `private/skins/cache/` directories writable by your web server.
1. Modify branding settings to replace the default OpenSB branding with your custom branding. Check the `public/assets/placeholder` directory for reference.

### Development specific

1. Enable debugging features by setting `mode` to `DEV`.
1. If you want to be able to upload during development, make the `dynamic/` directory and the directories inside it writable by your web server.

### SELinux
If you are trying to setup an instance of OpenSB on a Linux installation with SELinux enabled (eg: Fedora installs by default). There may be some issues.

Run these commands as root or through an utility like sudo (replace ``/var/www/opensb`` with the location of your instance):

```
setsebool -P httpd_can_network_connect on
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/opensb/dynamic(/.*)?"
restorecon -Rv /var/www/opensb/dynamic
```

### Apache Virtual host example
You will have to modify the directories to match your instance's location.
```
<VirtualHost *> 
    ServerName localhost
    DocumentRoot "/var/www/opensb/public"

    Alias /dynamic "/var/www/opensb/dynamic"

    <Directory "/var/www/opensb/">
        Options Indexes FollowSymLinks
        Require all granted
        AllowOverride All
    </Directory>
</VirtualHost>
```

### NGINX config example
Please note that this example uses `php-fpm`.
You will have to modify this to match your instance's location and/or distro.
```
server {
    listen       80;
    server_name  localhost;
    root   /var/www/opensb/public/;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location /dynamic/ {
        alias /var/www/opensb/dynamic;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_index index.php;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```
