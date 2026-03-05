import { BogPaymentError } from "./errors.js";

const DEFAULT_AUTH_URL =
  "https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token";
const DEFAULT_BASE_URL = "https://ipay.ge/opay/api/v1";

function trimSlash(value) {
  return String(value || "").replace(/\/+$/, "");
}

export class BogPaymentClient {
  constructor(options = {}) {
    this.clientId = options.clientId;
    this.clientSecret = options.clientSecret;
    this.authUrl = options.authUrl || DEFAULT_AUTH_URL;
    this.baseUrl = trimSlash(options.baseUrl || DEFAULT_BASE_URL);
    this.timeoutMs = options.timeoutMs ?? 30000;
    this.fetchImpl = options.fetchImpl || globalThis.fetch;
    this.cachedToken = null;
    this.cachedTokenExpiresAt = 0;

    if (!this.fetchImpl) {
      throw new BogPaymentError(
        "Fetch API is not available. Use Node 18+ or pass fetchImpl."
      );
    }
  }

  async getAccessToken(forceRefresh = false) {
    const now = Date.now();
    if (
      !forceRefresh &&
      this.cachedToken &&
      this.cachedTokenExpiresAt > now + 10_000
    ) {
      return this.cachedToken;
    }

    if (!this.clientId || !this.clientSecret) {
      throw new BogPaymentError(
        "Missing BOG credentials: clientId and clientSecret are required."
      );
    }

    const body = new URLSearchParams({ grant_type: "client_credentials" });

    const basicAuth = Buffer.from(
      `${this.clientId}:${this.clientSecret}`,
      "utf8"
    ).toString("base64");

    const response = await this.#fetchWithTimeout(this.authUrl, {
      method: "POST",
      headers: {
        Authorization: `Basic ${basicAuth}`,
        "Content-Type": "application/x-www-form-urlencoded",
        Accept: "application/json"
      },
      body
    });

    const payload = await this.#parseJson(response);
    if (!response.ok) {
      throw new BogPaymentError("BOG authentication failed.", {
        status: response.status,
        body: payload
      });
    }

    this.cachedToken = payload.access_token;
    const expiresInSeconds = Number(payload.expires_in || 3600);
    this.cachedTokenExpiresAt = now + expiresInSeconds * 1000;

    return this.cachedToken;
  }

  async createOrder(payload, options = {}) {
    return this.#authorizedRequest("/checkout/orders", {
      method: "POST",
      body: payload,
      idempotencyKey: options.idempotencyKey,
      acceptLanguage: options.acceptLanguage
    });
  }

  async getOrderDetails(orderId) {
    if (!orderId) {
      throw new BogPaymentError("orderId is required.");
    }

    return this.#authorizedRequest(`/checkout/payment/${orderId}`, {
      method: "GET"
    });
  }

  async payWithSavedCard(parentOrderId, payload, options = {}) {
    if (!parentOrderId) {
      throw new BogPaymentError("parentOrderId is required.");
    }

    return this.#authorizedRequest(`/checkout/orders/${parentOrderId}/payments`, {
      method: "POST",
      body: payload,
      idempotencyKey: options.idempotencyKey,
      acceptLanguage: options.acceptLanguage
    });
  }

  async saveCard(orderId, options = {}) {
    if (!orderId) {
      throw new BogPaymentError("orderId is required.");
    }

    return this.#authorizedRequest(`/checkout/orders/${orderId}/save-card`, {
      method: "POST",
      body: {},
      idempotencyKey: options.idempotencyKey
    });
  }

  async deleteSavedCard(orderId, options = {}) {
    if (!orderId) {
      throw new BogPaymentError("orderId is required.");
    }

    if (!options.idempotencyKey) {
      throw new BogPaymentError("idempotencyKey is required.");
    }

    return this.#authorizedRequest(`/checkout/orders/${orderId}/saved-card`, {
      method: "DELETE",
      idempotencyKey: options.idempotencyKey
    });
  }

  async confirmPreAuthorization(orderId, payload = {}) {
    if (!orderId) {
      throw new BogPaymentError("orderId is required.");
    }

    return this.#authorizedRequest(
      `/checkout/orders/${orderId}/preauthorization/confirm`,
      {
        method: "POST",
        body: payload
      }
    );
  }

  async rejectPreAuthorization(orderId, payload = {}) {
    if (!orderId) {
      throw new BogPaymentError("orderId is required.");
    }

    return this.#authorizedRequest(
      `/checkout/orders/${orderId}/preauthorization/reject`,
      {
        method: "POST",
        body: payload
      }
    );
  }

  async #authorizedRequest(path, options = {}) {
    const token = await this.getAccessToken();
    const url = `${this.baseUrl}${path}`;

    const headers = {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      ...(options.body ? { "Content-Type": "application/json" } : {})
    };

    if (options.idempotencyKey) {
      headers["Idempotency-Key"] = options.idempotencyKey;
    }

    if (options.acceptLanguage) {
      headers["Accept-Language"] = options.acceptLanguage;
    }

    const response = await this.#fetchWithTimeout(url, {
      method: options.method || "GET",
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined
    });

    const payload = await this.#parseJson(response);
    if (!response.ok) {
      throw new BogPaymentError("BOG API request failed.", {
        status: response.status,
        body: payload
      });
    }

    return payload;
  }

  async #fetchWithTimeout(url, options) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);

    try {
      return await this.fetchImpl(url, {
        ...options,
        signal: controller.signal
      });
    } catch (error) {
      if (error.name === "AbortError") {
        throw new BogPaymentError("BOG API request timed out.", {
          code: "REQUEST_TIMEOUT"
        });
      }

      throw new BogPaymentError(error.message || "BOG API network error.", {
        code: "NETWORK_ERROR"
      });
    } finally {
      clearTimeout(timer);
    }
  }

  async #parseJson(response) {
    const contentType = response.headers.get("content-type") || "";
    if (contentType.includes("application/json")) {
      return response.json();
    }

    const text = await response.text();
    try {
      return JSON.parse(text);
    } catch {
      return { raw: text };
    }
  }
}
