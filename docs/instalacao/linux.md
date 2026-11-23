# Iniciar no Linux

[Voltar ao README](../../README.md) · [Windows](windows.md) · [macOS](macos.md)

O caminho recomendado para assistir pela TV é **Apache + PHP**, na porta **80**, com um worker Laravel em outro terminal. Os comandos de serviço abaixo são exemplos para Ubuntu/Debian; adapte os nomes dos pacotes à sua distribuição.

## 1. Pré-requisitos

- PHP 8.3+ e Composer 2.
- Node.js 24 LTS com npm.
- FFmpeg e FFprobe.
- Apache 2.4 com PHP e `mod_rewrite`.
- Extensões PHP requeridas pelo Laravel, incluindo `mbstring`, `xml`, `curl`, `fileinfo`, `intl`, `zip` e `pdo_sqlite`.

Em Ubuntu/Debian cujo PHP padrão seja 8.3 ou superior:

```sh
sudo apt update
sudo apt install apache2 libapache2-mod-php php-cli php-mbstring php-xml php-curl php-zip php-sqlite3 php-intl ffmpeg git unzip
```

Instale o [Composer](https://getcomposer.org/download/) e o [Node.js 24](https://nodejs.org/en/download) pelos respectivos canais oficiais ou pelo gerenciador de versões que você já utiliza. Verifique antes de continuar:

```sh
php -v
composer --version
node --version
npm --version
ffmpeg -version
ffprobe -version
```

## 2. Baixar e preparar

Este exemplo coloca o projeto em `/var/www/cinema-local`, com seu usuário responsável pelos arquivos.

```sh
sudo mkdir -p /var/www/cinema-local
sudo chown "$USER":www-data /var/www/cinema-local
git clone https://github.com/ponborj/cinema-local.git /var/www/cinema-local
cd /var/www/cinema-local
composer install
test -f .env || cp .env.example .env
test -f database/database.sqlite || touch database/database.sqlite
mkdir -p storage/app/private/library
npm ci
npm run build
```

Na **primeira instalação**, quando `APP_KEY` estiver vazia:

```sh
php artisan key:generate
php artisan migrate
```

Em atualizações, preserve `.env`, chave e banco. Aplique as migrações pendentes com `php artisan migrate`.

## 3. Configurar caminhos e permissões

Edite o `.env`:

```dotenv
APP_URL=http://localhost
MEDIA_PORT=80
MEDIA_DIRECTORY="/mnt/videos/Filmes e Series"
FFMPEG_BINARY=/usr/bin/ffmpeg
FFPROBE_BINARY=/usr/bin/ffprobe
```

Confira os caminhos reais com `command -v ffmpeg` e `command -v ffprobe`. A biblioteca deve ser legível pelo usuário do Apache (`www-data` neste exemplo) e pelo usuário que executa o worker. Com `MEDIA_DIRECTORY` vazia, a aplicação usa `storage/app/private/library`.

O Apache e o worker precisam escrever no SQLite, em `storage` e em `bootstrap/cache`. Compartilhe esses diretórios pelo grupo `www-data`:

```sh
sudo chgrp -R www-data storage bootstrap/cache database
sudo find storage bootstrap/cache database -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache database -type f -exec chmod 0664 {} \;
php artisan config:clear
```

O worker iniciado no passo 5 usará `umask 0002` para que os arquivos que criar continuem graváveis pelo Apache.

## 4. Servir pela porta 80

Crie `/etc/apache2/sites-available/000-cinema-local.conf`:

```apache
<VirtualHost *:80>
    ServerName cinema-local.test
    DocumentRoot /var/www/cinema-local/public

    <Directory /var/www/cinema-local/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require ip 127.0.0.0/8 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16 ::1
    </Directory>

    php_admin_value max_execution_time 0
    php_admin_value memory_limit 256M
    ErrorLog ${APACHE_LOG_DIR}/cinema-local-error.log
    CustomLog ${APACHE_LOG_DIR}/cinema-local-access.log combined
</VirtualHost>
```

Ative e valide:

```sh
sudo a2enmod rewrite
sudo a2ensite 000-cinema-local.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo apache2ctl -S
```

O último comando mostra qual VirtualHost atende acessos por IP. O Cinema Local deve ser o site padrão dessa porta para que a TV abra a biblioteca pelo IP da máquina. Em um servidor com vários sites, ajuste a seleção do VirtualHost à sua configuração existente.

O Apache deve escutar em `80` em `/etc/apache2/ports.conf`. A raiz pública é sempre `public/`, conforme a [documentação de deploy do Laravel](https://laravel.com/docs/13.x/deployment).

## 5. Iniciar a fila e acessar

Em um terminal com seu usuário:

```sh
cd /var/www/cinema-local
umask 0002
php artisan queue:work --sleep=1 --tries=1 --timeout=21630 --memory=256
```

Mantenha esse terminal aberto. Para uso contínuo, um gerenciador de processos pode manter esse mesmo comando ativo, com o diretório de trabalho do projeto.

- Computador: [http://localhost](http://localhost).
- TV: use o IP exibido em **Acessar na TV**, na mesma rede local.
- Diagnóstico: `php artisan test` e os logs em `storage/logs` e `/var/log/apache2`.

Para encerrar o worker, rode `php artisan cinema:stop-worker` em outro terminal. Em uma instalação dedicada, `sudo systemctl stop apache2` encerra o servidor web.

## Modo rápido para desenvolvimento

Para conferir a interface sem configurar o Apache, use uma porta sem privilégio e múltiplos workers do PHP:

```sh
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8080 --no-reload
```

Acesse `http://localhost:8080` e inicie o worker da fila em outro terminal. Encerre com Ctrl+C. Esse comando usa a porta 8080 apenas nessa sessão; a configuração padrão do projeto continua na porta 80.

O [servidor embutido do PHP](https://www.php.net/manual/en/features.commandline.webserver.php) é destinado a desenvolvimento. Para assistir pela TV por períodos prolongados, utilize o Apache.
