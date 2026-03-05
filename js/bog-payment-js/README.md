# tatogi-bog-payment-js

JavaScript client for Bank of Georgia iPay API.

## Install

```bash
npm install tatogi-bog-payment-js
```

## Usage

```js
import { BogPaymentClient } from "tatogi-bog-payment-js";

const bog = new BogPaymentClient({
  clientId: process.env.BOG_CLIENT_ID,
  clientSecret: process.env.BOG_CLIENT_SECRET
});

const order = await bog.createOrder(
  {
    callback_url: "https://example.com/bog/callback",
    external_order_id: "order-1001",
    purchase_units: {
      total_amount: 100,
      currency: "GEL",
      basket: [
        {
          product_id: "product-1",
          name: "T-Shirt",
          quantity: 1,
          unit_price: 100
        }
      ]
    },
    redirect_urls: {
      success: "https://example.com/checkout/success",
      fail: "https://example.com/checkout/fail"
    }
  },
  {
    idempotencyKey: crypto.randomUUID(),
    acceptLanguage: "en"
  }
);

console.log(order._links.redirect.href);
```

## Configuration

- `clientId` (required)
- `clientSecret` (required)
- `authUrl` (optional)  
  default: `https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token`
- `baseUrl` (optional)  
  default: `https://ipay.ge/opay/api/v1`
- `timeoutMs` (optional)  
  default: `30000`
- `fetchImpl` (optional) for custom runtime fetch

## Methods

- `getAccessToken(forceRefresh = false)`
- `createOrder(payload, { idempotencyKey, acceptLanguage } = {})`
- `getOrderDetails(orderId)`
- `payWithSavedCard(parentOrderId, payload, { idempotencyKey, acceptLanguage } = {})`
- `saveCard(orderId, { idempotencyKey } = {})`
- `deleteSavedCard(orderId, { idempotencyKey })`
- `confirmPreAuthorization(orderId, payload = {})`
- `rejectPreAuthorization(orderId, payload = {})`

## Test

```bash
npm test
```

## Publish

```bash
npm login
npm publish --access public
```

For automated publish from GitHub Actions:
- add repository secret `NPM_TOKEN`
- push a tag like `js-v1.0.0`
