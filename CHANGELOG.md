# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
