# Iniciar no macOS

[Voltar ao README](../../README.md) · [Linux](linux.md) · [Windows](windows.md)

Este guia usa **Homebrew, Apache e PHP-FPM**, com porta **80** e um worker Laravel em outro terminal. Os caminhos do Homebrew variam entre Apple Silicon e Intel; descubra o prefixo com `brew --prefix`.

## 1. Pré-requisitos

Instale o [Homebrew](https://brew.sh/) e, em seguida:

```sh
brew install php@8.3 composer node@24 ffmpeg httpd git
export PATH="$(brew --prefix php@8.3)/bin:$(brew --prefix php@8.3)/sbin:$(brew --prefix node@24)/bin:$PATH"
```

Adicione a linha `export PATH=...` ao seu `~/.zshrc` para reutilizar as versões em novos terminais. As fórmulas [php@8.3](https://formulae.brew.sh/formula/php@8.3) e [node@24](https://formulae.brew.sh/formula/node@24) são versionadas; a configuração do PATH seleciona essas versões.

Confira:

```sh
php -v
composer --version
node --version
npm --version
ffmpeg -version
ffprobe -version
```

## 2. Baixar e preparar

O exemplo usa a pasta web do Homebrew:

```sh
mkdir -p "$(brew --prefix)/var/www"
git clone https://github.com/ponborj/cinema-local.git "$(brew --prefix)/var/www/cinema-local"
cd "$(brew --prefix)/var/www/cinema-local"
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

Em atualizações, preserve `.env`, chave e banco; execute `php artisan migrate` para aplicar apenas as migrações pendentes.

## 3. Configurar a biblioteca

Edite o `.env` com os caminhos reais, por exemplo:

```dotenv
APP_URL=http://localhost
MEDIA_PORT=80
MEDIA_DIRECTORY="/Volumes/Videos/Filmes e Series"
FFMPEG_BINARY=/opt/homebrew/bin/ffmpeg
FFPROBE_BINARY=/opt/homebrew/bin/ffprobe
```

Use `command -v ffmpeg` e `command -v ffprobe` para obter os caminhos; em Macs Intel, o prefixo costuma ser `/usr/local`. Não use `~` no `.env`; informe o caminho absoluto. `MEDIA_DIRECTORY` vazia usa `storage/app/private/library`.

```sh
php artisan config:clear
```

## 4. Iniciar PHP-FPM e configurar Apache

Inicie o PHP-FPM pelo seu usuário:

```sh
brew services start php@8.3
```

Confira a diretiva `listen` em `$(brew --prefix)/etc/php/8.3/php-fpm.d/www.conf`. O VirtualHost abaixo pressupõe `127.0.0.1:9000`; se o pool usar outro endereço, ajuste o `SetHandler` para corresponder a ele.

Edite `$(brew --prefix)/etc/httpd/httpd.conf`:

1. Altere a diretiva `Listen` existente para `Listen 80`.
2. Habilite os módulos `proxy_module`, `proxy_fcgi_module` e `rewrite_module` nas respectivas linhas `LoadModule` já presentes.
3. Defina `ServerName localhost:80` e inclua o arquivo de VirtualHost abaixo com `Include /CAMINHO_DO_BREW/etc/httpd/extra/cinema-local.conf`, substituindo o prefixo pelo resultado de `brew --prefix`.

Crie esse arquivo `cinema-local.conf`. Substitua `/CAMINHO_DO_PROJETO` pelo resultado de `pwd` na pasta clonada:

```apache
<VirtualHost *:80>
    ServerName cinema-local.test
    DocumentRoot "/CAMINHO_DO_PROJETO/public"

    <Directory "/CAMINHO_DO_PROJETO/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require ip 127.0.0.0/8 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16 ::1
    </Directory>

    <FilesMatch "\.php$">
        SetHandler "proxy:fcgi://127.0.0.1:9000"
    </FilesMatch>
    DirectoryIndex index.php
</VirtualHost>
```

O [mod_proxy_fcgi](https://httpd.apache.org/docs/2.4/mod/mod_proxy_fcgi.html) encaminha a execução de PHP ao PHP-FPM. O Apache precisa ler a pasta `public`; o PHP-FPM e o worker precisam ler os vídeos e escrever em `storage`, `bootstrap/cache` e `database`. Neste guia, o PHP-FPM e o worker rodam pelo usuário que clonou o projeto.

Valide e inicie a instância do Apache do Homebrew:

```sh
"$(brew --prefix httpd)/bin/httpd" -t
sudo "$(brew --prefix httpd)/bin/httpd" -k start
"$(brew --prefix httpd)/bin/httpd" -S
```

O `sudo` aplica-se ao início do Apache na porta 80. Instale dependências e execute os comandos do projeto pelo seu usuário normal. Confira no comando `-S` se esse VirtualHost é o padrão para acessos por IP na porta 80. Ao editar a configuração, use `sudo "$(brew --prefix httpd)/bin/httpd" -k graceful` para recarregar.

## 5. Iniciar a fila, acessar e encerrar

Em outro terminal, na raiz do projeto:

```sh
php artisan queue:work --sleep=1 --tries=1 --timeout=21630 --memory=256
```

Mantenha o terminal aberto. Abra [http://localhost](http://localhost) no Mac; na TV, utilize o IP mostrado em **Acessar na TV**. Os dois dispositivos precisam estar na mesma rede.

Para encerrar:

```sh
php artisan cinema:stop-worker
sudo "$(brew --prefix httpd)/bin/httpd" -k stop
```

O PHP-FPM do Homebrew pode ser encerrado com `brew services stop php@8.3` quando não estiver sendo utilizado por outros projetos.

## Modo rápido para desenvolvimento

Para conferir a interface sem configurar o Apache:

```sh
PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=0.0.0.0 --port=8080 --no-reload
```

Abra `http://localhost:8080`, execute o worker em outro terminal e encerre o servidor com Ctrl+C. A porta 8080 vale apenas para esse comando; o padrão do projeto continua sendo 80.

Os [workers do servidor embutido do PHP](https://www.php.net/manual/en/features.commandline.webserver.php) atendem requisições simultâneas em desenvolvimento. Para uso prolongado na TV, prefira o Apache.

## Diagnóstico

- `php artisan test` executa os testes do projeto.
- `storage/logs/laravel.log` contém os erros da aplicação.
- Os logs do Apache ficam no diretório de logs configurado pelo Homebrew.
- Se a TV não abrir, confira a permissão de entrada do Apache no firewall do macOS e a conexão dos dois dispositivos à mesma rede.
- Se o MP4 permanecer na fila, confira o terminal do worker e os caminhos do FFmpeg/FFprobe.
