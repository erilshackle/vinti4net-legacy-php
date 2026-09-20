# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

## [2.0.0] - 2026-09-20

### Added

- Added automatic generation of 15-character merchant references.
- Added automatic merchant session generation.
- Added `setMerchant()` for explicitly configuring merchant reference and session.
- Added normalized operation detection for `purchase`, `service_payment`, `recharge`, and `refund` responses.
- Added explicit `SUCCESS`, `ERROR`, `CANCELLED`, and `INVALID_FINGERPRINT` response statuses.
- Added safe PAN masking in normalized responses.
- Added normalized Dynamic Currency Conversion (DCC) extraction.
- Added transaction-specific request validation.
- Added merchant reference and merchant session length validation.
- Added callback URL and message-language validation.
- Added refund clearing period and transaction ID validation.
- Added support for friendly and native SISP billing field names.
- Added phone, account information, and address-match normalization for 3D Secure billing data.
- Added `getRequest()` for inspecting the currently prepared transaction.
- Added `Vinti4Exception` for standalone configuration, request, and response errors.

### Changed

- Reworked the legacy implementation as a standalone single-file SDK while retaining PHP 5.6+ compatibility.
- Reworked transaction preparation so a client instance can prepare subsequent transactions instead of being limited to one request.
- Purchase, service payment, recharge, and refund preparation now use a normalized internal request flow.
- Refund requests are now built and validated separately from regular payment requests.
- Request amounts are validated instead of being silently cast to integers.
- Fingerprint amount conversion no longer depends on BCMath.
- Currency handling now consistently uses ISO 4217 numeric strings internally.
- Merchant references now use a 15-character timestamp-and-random-suffix format.
- Merchant sessions default to a 15-character timestamp-based value.
- Successful responses now require both a compatible message type and transaction result before being classified as successful, except for successful refund responses.
- Fingerprint validation now uses timing-safe comparison where supported.
- User cancellation detection accepts normalized boolean values.
- Provider error messages are resolved from multiple known SISP response fields.
- DCC parsing now reports malformed data instead of silently discarding it.
- Billing normalization has been simplified and aligned with the standalone API.
- `purchaseRequest` contains the normalized billing payload without flattening billing fields into the payment POST body.
- Generated payment form values and action URLs are HTML-escaped.
- PAN values returned in normalized responses are masked.
- Normalized responses now include the detected transaction `operation`.

### Removed

- Removed `setRequestParams()` from the legacy 1.x API.
- Removed the one-payment-per-instance restriction.
- Removed the BCMath dependency used by fingerprint amount conversion.
- Removed automatic flattening of billing fields into the payment POST body.
- Removed legacy `user` sub-array billing inference.
- Removed exposure of unmasked PAN values from normalized responses.

### Fixed

- Fixed refund response classification and success handling.
- Fixed transaction success detection so a successful message type alone does not imply a successful purchase.
- Fixed merchant reference generation to satisfy the 15-character SISP requirement.
- Fixed handling of malformed or missing DCC response data.
- Fixed amount conversion for fingerprints to avoid floating-point rounding.
- Fixed validation of custom endpoints, callback URLs, currencies, languages, and transaction-specific fields.

### Compatibility

- Requires PHP 5.6 or later.
- Does not require Composer.
- Does not require BCMath.
- Does not require external dependencies.
- Receipt rendering is intentionally not included in the standalone legacy build.


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

