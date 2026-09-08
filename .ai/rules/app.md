---
paths:
  - 'app/**'
---

# App

## Storex tenant access, moderation and payment invariants
Platform admins may inspect all shops but must never edit their business data; User::manages deliberately returns false for platform admins, even for owned shops. Scope Backpack and custom endpoints through accessibleShops and authorize writes separately. Public shops/products require approved status; content changes re-enter pending and cannot release a freeze. SalesService owns integer-minor-unit order totals, expiring stock reservations, and idempotent Paystack settlement. Never fulfil from callback query parameters alone. Ledger receipts use the private local disk and must be downloaded through the tenant-authorized finance endpoint.

## Platform moderation excludes all shop financial access
Platform admins may inspect registered shops, their owners/staff, and inventory for moderation only. They must not access orders/sales, income or expense ledger entries, finance/tax summaries or exports, or either sales and expense receipts, including for shops they own. Enforce denials server-side in financial controllers as well as hiding navigation. Platform shop workspaces use admin.shop-moderation and must not query or pass sales totals, orders, or Paystack data.

## Admin storefront preview exception
The existing shop storefront URL permits authenticated Backpack platform admins to preview shops and products in any approval status. This is read-only: hide checkout controls and financial/payment details. Public users and shop staff still see only approved shops/products; query parameters must never grant preview access. Storefront responses are private/no-store to prevent caching admin-only content.

## Storefront preview permits moderation actions
Platform admin storefront previews now include Freeze shop and Reject shop actions through the existing authenticated moderation endpoint, with mandatory reasons and audit logs. The earlier read-only preview rule refers to business data and finance: moderation status changes are allowed. Never expose these controls to public users or shop staff.
