# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

## [v1.1.0] - 2025-11-16

### Changed
- Update PHPDocs to English

## [v1.0.0] - 2025-11-16

### Added
- add documentation
- Add PHPUnit configuration and bootstrap; update .gitignore
- chore: update GitHub Actions for PHPUnit coverage and add export-ignore attrs

### Changed
- Update README.md
- Update phpunit.xml
- chore: rename pjpunit.yml to phpunit.yml
- build(deps): update composer.json
- chore: initial commit of Vinti4Net Legacy PHP SDK
- Initial commit

## [1.0.0] - 2025-11-16

### Adicionado
- Integração Legacy PHP SDK para Vinti4Net (PHP 5.6+)
- Suporte a:
  - Compra (Purchase) com 3D Secure
  - Pagamento de serviços
  - Recarga de contas e cartões
  - Reembolso (Refund)
- Criação de formulários HTML auto-submit
- Processamento de respostas do gateway com verificação de fingerprint

### Corrigido
- Normalização de dados de billing
- Cálculo de fingerprint para transações e reembolsos

### Observações
- Embora funcione em Laravel, **recomendamos usar `erilshk/vinti4net` moderno** para projetos PHP 8.1+
- Testes unitários completos com PHPUnit 10

