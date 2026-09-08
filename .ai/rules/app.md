---
paths:
  - 'app/**'
---

# App

## Storex tenant access, moderation and payment invariants
Platform admins may inspect all shops but must never edit their business data; User::manages deliberately returns false for platform admins, even for owned shops. Scope Backpack and custom endpoints through accessibleShops and authorize writes separately. Public shops/products require approved status; content changes re-enter pending and cannot release a freeze. SalesService owns integer-minor-unit order totals, expiring stock reservations, and idempotent Paystack settlement. Never fulfil from callback query parameters alone. Ledger receipts use the private local disk and must be downloaded through the tenant-authorized finance endpoint.

## Platform moderation excludes all shop financial access
Platform admins may inspect registered shops, their owners/staff, and inventory for moderation only. They must not access orders/sales, income or expense ledger entries, finance/tax summaries or exports, or either sales and expense receipts, including for shops they own. Enforce denials server-side in financial controllers as well as hiding navigation. Platform shop workspaces use admin.shop-moderation and must not query or pass sales totals, orders, or Paystack data.
