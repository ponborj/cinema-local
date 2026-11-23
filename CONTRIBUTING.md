# Como contribuir

## Preparar o ambiente

Siga o guia do seu sistema em [`docs/instalacao`](docs/instalacao). O projeto usa PHP 8.3+, Composer 2, Node.js 24 LTS, FFmpeg/FFprobe e SQLite.

Antes de abrir uma alteração, execute:

```sh
composer validate --strict
php artisan test
npm run build
```

O teste de conversão precisa de `FFMPEG_BINARY` e `FFPROBE_BINARY` apontando para os executáveis. Ele usa um vídeo sintético, sem depender da biblioteca de filmes.

## Código e alterações

- Preserve a separação entre controllers, serviços, jobs, views Blade e componentes Alpine.
- Mantenha os arquivos de mídia originais somente para leitura.
- Acrescente testes quando mudar streaming, validação de caminhos, legendas ou conversão.
- Atualize a documentação quando mudar configuração ou comandos de execução.
- Nunca inclua `.env`, banco local, logs, filmes ou cópias convertidas nos commits.

Use branches curtas como `feat/filtros` ou `fix/legendas`. Prefira mensagens como `feat(player): adicionar controle de velocidade` e `fix(streaming): validar intervalos inválidos`.

## Versão e releases

A versão de referência fica em [`VERSION`](VERSION); `package.json` e `package-lock.json` devem ter o mesmo valor. O Composer obtém a versão do projeto pelo Git, sem um campo `version` fixo no `composer.json`.

1. Registre a mudança em [`CHANGELOG.md`](CHANGELOG.md).
2. Atualize `VERSION` e execute `npm version <versao> --no-git-tag-version`.
3. Execute as verificações acima e revise os arquivos com `git diff`.
4. Faça o commit e crie uma tag anotada, por exemplo `git tag -a v0.1.0 -m "Cinema Local 0.1.0"`.
5. Envie o commit e a tag quando estiver pronto para publicar.

Enquanto o projeto estiver em `0.x`, mudanças ainda podem alterar os comandos e as APIs de desenvolvimento. O changelog deve explicar essas alterações.
