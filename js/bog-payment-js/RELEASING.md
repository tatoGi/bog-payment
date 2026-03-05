# Releasing `tatogi-bog-payment-js`

## One-time setup

1. Authenticate with npm:
   ```bash
   npm login
   ```
2. Verify access:
   ```bash
   npm whoami
   ```

## Manual publish

Run from `js/bog-payment-js`:

```bash
npm test
npm publish --access public
```

## Versioned publish

```bash
npm run release:patch
# or
npm run release:minor
# or
npm run release:major
```

## GitHub Actions publish

Workflow publishes automatically when a tag with `js-v*` is pushed, for example:

```bash
git tag js-v1.0.0
git push origin js-v1.0.0
```

Required repository secret:

- `NPM_TOKEN`: npm automation token with publish permission.
