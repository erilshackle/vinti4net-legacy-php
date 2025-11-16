# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

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

