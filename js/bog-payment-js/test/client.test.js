import test from "node:test";
import assert from "node:assert/strict";
import { BogPaymentClient } from "../src/index.js";

function jsonResponse(body, status = 200) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "content-type": "application/json" }
  });
}

test("createOrder sends request to checkout/orders", async () => {
  const calls = [];
  const fetchImpl = async (url, options) => {
    calls.push({ url, options });

    if (url.includes("/oauth/token")) {
      return jsonResponse({
        access_token: "token_1",
        token_type: "Bearer",
        expires_in: 3600
      });
    }

    if (url.includes("/checkout/orders")) {
      return jsonResponse({
        id: "order_123",
        status: "created",
        _links: { redirect: { href: "https://pay.example/redirect" } }
      });
    }

    return jsonResponse({ message: "Not found" }, 404);
  };

  const client = new BogPaymentClient({
    clientId: "cid",
    clientSecret: "secret",
    authUrl: "https://mock.local/oauth/token",
    baseUrl: "https://mock.local/opay/api/v1",
    fetchImpl
  });

  const response = await client.createOrder(
    {
      callback_url: "https://example.com/callback",
      purchase_units: {
        total_amount: 100,
        currency: "GEL",
        basket: [{ product_id: "1", name: "Product", quantity: 1, unit_price: 100 }]
      },
      redirect_urls: {
        success: "https://example.com/success",
        fail: "https://example.com/fail"
      }
    },
    { idempotencyKey: "idem-1", acceptLanguage: "ka" }
  );

  assert.equal(response.id, "order_123");
  assert.equal(calls.length, 2);
  assert.match(calls[1].url, /\/checkout\/orders$/);
  assert.equal(calls[1].options.headers["Idempotency-Key"], "idem-1");
  assert.equal(calls[1].options.headers["Accept-Language"], "ka");
});

test("getAccessToken uses cache by default", async () => {
  let tokenCalls = 0;
  const fetchImpl = async (url) => {
    if (url.includes("/oauth/token")) {
      tokenCalls += 1;
      return jsonResponse({
        access_token: "cached_token",
        token_type: "Bearer",
        expires_in: 3600
      });
    }

    return jsonResponse({
      id: "order_1",
      status: "completed"
    });
  };

  const client = new BogPaymentClient({
    clientId: "cid",
    clientSecret: "secret",
    authUrl: "https://mock.local/oauth/token",
    baseUrl: "https://mock.local/opay/api/v1",
    fetchImpl
  });

  await client.getAccessToken();
  await client.getAccessToken();
  await client.getOrderDetails("order_1");

  assert.equal(tokenCalls, 1);
});

