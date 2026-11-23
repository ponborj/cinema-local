# Iniciar no Windows

[Voltar ao README](../../README.md) · [Linux](linux.md) · [macOS](macos.md)

Este guia usa o Apache e o PHP Thread Safe do **Laragon**, mais Composer, Node.js e FFmpeg. O servidor abre na porta **80**; os atalhos iniciam o Apache e a fila em segundo plano.

## 1. Pré-requisitos

- Windows com [Laragon](https://laragon.org/download/) em `C:\laragon`.
- PHP 8.3+ **Thread Safe**, com `php8apache2_4.dll`, compatível com o Apache do Laragon.
- [Composer 2](https://getcomposer.org/download/), [Node.js 24 LTS](https://nodejs.org/en/download) e [Git](https://git-scm.com/downloads/win) no PATH.
- [FFmpeg e FFprobe](https://ffmpeg.org/download.html#build-windows), no PATH ou configurados por caminho absoluto no `.env`.
- Extensões PHP: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_sqlite`, `session`, `tokenizer`, `xml` e `zip`.

Confira em um PowerShell:

```powershell
php -v
composer --version
node --version
npm --version
ffmpeg -version
ffprobe -version
```

O helper procura o Apache em `C:\laragon\bin\apache` e usa o `php.exe` do PATH. Se o Laragon estiver em outro local, ajuste `$apacheDirectory` em [`scripts/windows/servidor.ps1`](../../scripts/windows/servidor.ps1).

## 2. Baixar e preparar

Clone o repositório ou extraia o ZIP e abra um terminal na pasta do projeto.

```powershell
git clone https://github.com/ponborj/cinema-local.git cinema-local
cd cinema-local
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
if (!(Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite | Out-Null }
New-Item -ItemType Directory -Force storage/app/private/library | Out-Null
npm ci
npm run build
```

Na **primeira instalação**, quando `APP_KEY` estiver vazia:

```powershell
php artisan key:generate
php artisan migrate
```

Em atualizações, mantenha o `.env`, a chave e o banco existentes; execute apenas as migrações pendentes com `php artisan migrate`.

## 3. Configurar a biblioteca

Edite o `.env`, por exemplo:

```dotenv
APP_URL=http://localhost
MEDIA_PORT=80
MEDIA_DIRECTORY="C:/Videos/Filmes e Series"
FFMPEG_BINARY="C:/Ferramentas/ffmpeg/bin/ffmpeg.exe"
FFPROBE_BINARY="C:/Ferramentas/ffmpeg/bin/ffprobe.exe"
```

Use barras `/` e aspas quando houver espaços. Se os executáveis estiverem no PATH, os valores podem ser apenas `ffmpeg` e `ffprobe`. `MEDIA_DIRECTORY` vazia usa `storage/app/private/library`, que foi criada no passo anterior.

```powershell
php artisan config:clear
```

## 4. Iniciar e acessar

Dê dois cliques em [`iniciar.cmd`](../../iniciar.cmd) ou execute:

```powershell
.\iniciar.cmd
```

O atalho verifica a porta, valida o Apache, aplica migrações pendentes e inicia uma instância dedicada do servidor e um worker. O navegador abre em [http://localhost](http://localhost).

Na TV, abra o endereço mostrado em **Acessar na TV**, como `http://192.168.1.50`. O computador deve permanecer ligado e conectado à mesma rede da TV.

Se a porta 80 estiver ocupada pelo Apache principal do Laragon, IIS ou outro servidor, encerre esse servidor pelo gerenciador correspondente. O helper informa o conflito e não encerra processos de outros projetos. Para usar outra porta, altere `MEDIA_PORT` e `APP_URL`, limpe a configuração e reinicie.

## 5. Encerrar

```powershell
.\parar.cmd
```

O atalho cancela uma preparação ativa, encerra o worker e fecha somente o Apache gerenciado por este projeto. Não instala serviços do Windows nem cria regras de firewall.

## 6. Verificar e resolver problemas

```powershell
php artisan test
```

- **TV não abre o site:** confira o IP atual, o perfil da rede, a permissão de entrada do Apache no firewall e se a TV está em uma rede de convidados isolada.
- **Vídeo sem som/formato não suportado:** escolha o áudio e use **Preparar MP4 para TV**.
- **Preparação parada na fila:** confira `storage/framework/cinema-server/worker-error.log` e o caminho do FFmpeg.
- **Erro do Apache/PHP:** confira `storage/framework/cinema-server/apache-error.log` e `storage/logs/laravel.log`.
- **Layout não atualiza:** encerre o Vite, execute `npm run build` e recarregue a página.

Para streaming no Windows, use o Apache deste guia. O [servidor embutido do PHP](https://www.php.net/manual/en/features.commandline.webserver.php) não oferece os workers experimentais nesse sistema, e uma transmissão longa pode bloquear outras requisições.
