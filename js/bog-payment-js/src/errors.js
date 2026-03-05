export class BogPaymentError extends Error {
  constructor(message, options = {}) {
    super(message);
    this.name = "BogPaymentError";
    this.status = options.status ?? null;
    this.body = options.body ?? null;
    this.code = options.code ?? null;
  }
}
