# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-03-05

### Added

- Laravel 12-compatible test matrix with updated `orchestra/testbench` and `phpunit` ranges.
- New feature tests for order creation flow and legacy purchase unit normalization.
- New internal `BogConfig` helper for unified package config access.

### Changed

- Default iPay API endpoints updated to official BOG iPay paths (`https://ipay.ge/opay/api/v1/...`).
- `createOrder` flow refactored for stable payload normalization, basket extraction, and status persistence.
- Callback processing improved to correctly handle `purchase_units.basket`.
- Package now supports both `config('bog-payment.*')` and legacy `config('services.bog.*')`.

### Fixed

- Removed broken route binding to non-existent `BogCardController::saveCard`.
- SQLite-safe rollback in payment migration (`user_id` index drop before column drop).
- Card/payment migrations no longer assume app-level `users` table name.

## [1.0.0] - 2025-01-10

### Added

- Initial release of BOG Payment package
- BogPayment model for payment transactions
- BogCard model for saved payment cards
- BogAuthService for OAuth2 authentication
- BogPaymentService for payment operations
- Configuration file for BOG API settings
- Database migrations for bog_payments, bog_cards, and bog_payment_product tables
- Service provider for Laravel auto-discovery
- Comprehensive documentation

### Features

- Create payment orders
- Save cards for future payments
- Payment with saved cards
- Card management (CRUD operations)
- Payment history tracking
- OAuth2 authentication with BOG API
- Callback handling support
- Product linking support
