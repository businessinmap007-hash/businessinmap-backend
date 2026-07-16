# BIM Mobile API v2 — Reference

The contract for the BIM app lives in [`openapi-v2.yaml`](openapi-v2.yaml)
(OpenAPI 3.0.3). v1 is abandoned — build against `/api/v2` only.

## View / use it

- **Swagger UI / editor**: open [editor.swagger.io](https://editor.swagger.io)
  and *File → Import File* → `openapi-v2.yaml`. Gives a browsable, try-it-out UI.
- **Postman**: *Import* → the YAML file. Postman turns every path into a request,
  grouped by tag. Set a collection variable for the bearer token.
- **Client generation**: `openapi-generator-cli generate -i openapi-v2.yaml
  -g dart -o ./client` (or `swift5`, `kotlin`, …).

## Server

Base URL is `https://{host}/api/v2` (host defaults to `businessinmap.com/testing`).
Every path in the spec is relative to `/api/v2`.

## Auth

Sanctum bearer tokens. Flow:

1. `POST /auth/register` or `POST /auth/login` → returns `{ data, token }`.
2. Send `Authorization: Bearer <token>` on every non-public request.
3. `POST /auth/logout` (this device) or `/auth/logout-all` (everywhere).

Public endpoints (no token) are marked with empty `security` in the spec:
register, login, the whole `password/*` flow, `discovery/*`, `offers/*` (browse),
`search/*`, and `wallet/topup/callback` (the payment gateway calls it).

## Response conventions

- Success: `{ "success": true, "data": ... }` (some also carry `message`).
- Lists are Laravel paginators: `{ data: [...], current_page, per_page, total,
  last_page, links }`.
- Validation errors: **422** `{ "message", "errors": { field: ["..."] } }`.
- Other errors: **401** (no/invalid token), **403** (not allowed), **404**
  (missing/not owned), **409** (wrong state for the action).

## Money & idempotency

- The **wallet is a points/credit store** — deposits, insurance, service fees,
  and P2P transfer. It does **not** pay for menu orders (those are cash on
  arrival).
- `wallet/deposit|withdraw|transfer` honour an **`Idempotency-Key`** header:
  resending the same key returns the first result instead of moving money twice.
- **Top-up** (`wallet/topup`) starts a hosted-checkout payment; the wallet is
  credited only by the gateway's server-to-server `wallet/topup/callback`, never
  on the app's return screen.

## Order lifecycle (for the app UI)

`status`: `cart → pending → completed | cancelled`. While `pending`, the business
drives a **`prep_status`**: `accepted → preparing → ready`. Delivery orders become
visible to drivers at `preparing`. Fulfilment completes via the QR flows
(handover for pickup/dine-in; the two-stage delivery loop for delivery), not via
`status` sub-states.

## Keeping this in sync

The spec is hand-maintained. When you add or change a `/api/v2` route or a
request/response shape, update `openapi-v2.yaml` in the same change.

`OpenApiSpecCoverageTest` enforces this: it fails if a route is undocumented, if
the spec documents a path no route serves, if a `$ref` dangles, or if an
operation uses an undeclared tag. Run it with

```bash
php artisan test --filter=OpenApiSpecCoverageTest
```

It checks that every path *exists*, not that its body is accurate — request and
response shapes are still on you. To eyeball the route inventory directly:
`php artisan route:list --path=api/v2`.
