# Changelog

Este arquivo registra as mudanças do Cinema Local. As versões seguem `MAJOR.MINOR.PATCH` (SemVer).

## [0.1.0] - 2026-11-23

### Adicionado

- Catálogo de filmes, séries e pastas com Laravel 13, Blade, Tailwind CSS 4 e Alpine.js.
- Busca e filtros na pasta atual, breadcrumbs e navegação por controle remoto.
- Player HTML5 com streaming parcial, avanço, retrocesso, tela cheia e retomada por dispositivo.
- Legendas SRT/VTT externas e extração de legendas de texto embutidas.
- Preparação de MP4 H.264/AAC, escolha de áudio, fila persistente, progresso, cancelamento e reutilização das cópias.
- Restrição à rede local, proteção de caminhos e proteção CSRF.
- Início e encerramento do Apache/worker no Windows, com porta 80 por padrão.
- Guias de execução para Linux, macOS e Windows, orientações de contribuição e pasta de screenshots.
- Testes de integração do catálogo, player, segurança, legendas e conversão.

### Observações

- Primeira versão de desenvolvimento; a compatibilidade precisa ser conferida no navegador de cada TV.
- Não há sincronização de progresso entre dispositivos nem autenticação de usuários.

### Corrigido

- Jobs repetidos não refazem preparações já concluídas.
- Cancelamento preserva o estado de um MP4 já pronto.
- Jobs de uma mesma preparação não são executados ao mesmo tempo por workers diferentes.
