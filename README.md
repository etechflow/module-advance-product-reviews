# ETechFlow Advanced Product Reviews for Magento 2

Rich, conversion-focused product reviews for Magento 2 (Open Source **and** Adobe
Commerce / Cloud). Replaces the bare core review experience with photos & video,
pros/cons, verified-buyer badges, helpful voting, review summaries, admin
replies, customer Q&A, post-purchase reminders with auto-generated coupons,
AI auto-translation, an analytics dashboard, and a full **headless (GraphQL +
REST)** layer with a **Hyvä** theme integration.

- **Vendor / module:** `ETechFlow_AdvancedProductReviews`
- **Compatibility:** Magento 2.4.4 – 2.4.8, PHP 8.1 – 8.4, CE / EE / Cloud
- **Themes:** Luma, Hyvä (via the companion `ETechFlow_AdvancedProductReviewsHyva` module), and headless storefronts (PWA Studio / custom)

---

## Features

### Storefront reviews
- **Photos & video** on reviews (real MIME sniffing, not just extension checks, to block spoofed uploads)
- **Pros / cons** lists and a **"would recommend"** flag
- **Verified Buyer** badge (detected from the customer's order history)
- **"Was this helpful?"** voting with duplicate-vote protection (per customer, and per guest via a privacy-preserving hash)
- **Summary bar**: average rating, star distribution, recommend %, verified count
- **Filtering & sorting**: by rating, verified-only, with-media; newest / oldest / most-helpful / highest / lowest
- **Comments** on reviews, including store (admin) replies
- **AI auto-translation** of reviews into the storefront language, powered by the **Claude API**, cached per language so each review is translated once

### Customer Q&A
- Customers ask questions on a product; staff or other customers answer
- Moderation workflow (pending / approved / rejected), admin answers flagged as official

### Post-purchase review reminders
- Schedules a reminder email a configurable number of days after an order completes
- Optional **auto-generated coupon** as a thank-you incentive (uses Magento SalesRule coupon generation)
- Skips customers who have already reviewed; idempotent per order; cron-driven

### Admin
- Configuration under **Stores ▸ Configuration ▸ ETechFlow ▸ Advanced Product Reviews**
- Grids for **Comments**, **Questions**, and **Reminders** with mass actions
- **Analytics dashboard** (Reports ▸ Reviews Analytics): KPIs, rating distribution, 12-month trend, top products — dependency-free inline charts

### Headless (Phase 7)
- **GraphQL** API for summary, review list, Q&A, and mutations (vote / comment / ask / translate)
- **REST** API for review summary and review-extra data
- **Hyvä** companion module renders the whole UI from the GraphQL API (Tailwind + Alpine)

---

## Installation

### Composer (recommended)
```bash
composer require etechflow/module-advanced-product-reviews
bin/magento module:enable ETechFlow_AdvancedProductReviews
bin/magento setup:upgrade
bin/magento setup:di:compile        # production mode only
bin/magento cache:flush
```

### Manual
Copy the module to `app/code/ETechFlow/AdvancedProductReviews`, then run the same
`module:enable` / `setup:upgrade` / `cache:flush` commands.

> Hyvä stores should also install the companion module
> `etechflow/module-advanced-product-reviews-hyva` (see its own README).

---

## Configuration

`Stores ▸ Configuration ▸ ETechFlow ▸ Advanced Product Reviews`

| Section | Key settings |
|---|---|
| **General** | Enable module, allow guest reviews, auto-approve |
| **Review elements** | Pros/cons, recommend, helpful voting, comments (+ guest), Q&A |
| **Media** | Enable images / videos, max counts & sizes, allowed video types |
| **Translation (Claude)** | Enable, **Claude API key** (stored encrypted), model, auto-translate |
| **Spam** | CAPTCHA toggle |
| **Reminders** | Enable, delay (days), coupon toggle + SalesRule id, email template |

**Translation and Reminders ship OFF by default.** Enable them and supply the
Claude API key / coupon rule when you're ready. The Claude key is stored with
Magento's encrypted-config field and is never committed to source.

---

## Headless API

### GraphQL
All types are namespaced with `etf` / `Etf` so they never collide with core.

Queries: `etfReviewSummary`, `etfProductReviews` (paged + filter + sort),
`etfProductQuestions`, and `etf_review_summary` added onto `ProductInterface`
(so a `products` query can pull the summary inline).

Mutations: `etfVoteReviewHelpful`, `etfPostReviewComment`,
`etfAskProductQuestion`, `etfTranslateReview`.

```graphql
{
  etfReviewSummary(sku: "24-MB01") {
    review_count
    average_rating
    rating_distribution { rating count percent }
  }
  etfProductReviews(sku: "24-MB01", pageSize: 5, sort: HELPFUL,
                    filter: { verified_only: true }) {
    total_count
    items { review_id title rating nickname pros cons helpful_count
            media { media_type url } comments { author_name comment } }
  }
}
```

### REST
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/V1/etechflow-reviews/summary/product/:productId` | anonymous |
| GET | `/V1/etechflow-reviews/summary/sku/:sku` | anonymous |
| GET | `/V1/etechflow-reviews/extra/review/:reviewId` | anonymous |
| GET | `/V1/etechflow-reviews/extra/:extraId` | admin |
| GET | `/V1/etechflow-reviews/extra/search` | admin |
| POST | `/V1/etechflow-reviews/extra` | admin |
| DELETE | `/V1/etechflow-reviews/extra/:extraId` | admin |

```bash
curl https://store.example.com/rest/V1/etechflow-reviews/summary/sku/24-MB01
```

---

## Data model

Eight tables, all `etechflow_*`, foreign-keyed to core `review` with
`ON DELETE CASCADE` (child rows clean up when a review is deleted):
`review_extra`, `review_media`, `review_vote`, `review_comment`,
`review_translation`, `qa_question`, `qa_answer`, `review_reminder`.

> `product_id` columns are **indexed, not hard-FK'd**, so the module stays
> compatible with Adobe Commerce Content Staging (which swaps the catalog
> primary key to `row_id`).

---

## Cron

| Job | Schedule | Purpose |
|---|---|---|
| `etechflow_reviews_send_reminders` | hourly (`0 * * * *`) | Send due review reminders + issue coupons |

---

## Quality

- Coding standard: **Magento2** (PHP_CodeSniffer) — **0 errors**
- PHP 8.1–8.4, `declare(strict_types=1)` throughout, constructor property promotion
- Service contracts (`Api/`) for repository + summary management; GraphQL and REST share the same service layer

---

## Licensing & Activation

This module is commercially licensed. On a production host it stays **inactive
until a valid license key is present** — every storefront, headless
(GraphQL/REST), and admin surface is gated, so there is no ungated bypass.

- **Activate:** Stores ▸ Configuration ▸ eTechFlow ▸ Advanced Product Reviews ▸
  **License** → paste your key. Or open any admin grid (Comments / Q&A /
  Reminders / Analytics) to reach the in-admin **gate page**, choose a plan and
  pay by card (Stripe) — your subscription key is issued by the eTechFlow portal
  and saved automatically.
- **Key types:** a per-module HMAC key, the shared eTechFlow **bundle** key (one
  key activates every eTechFlow module on the host), or an **SP-XXXX**
  subscription key validated against the licensing portal (with domain + server-IP
  checks and soft expiry).
- **Dev/staging is free:** `localhost`, `*.test`, `*.local`, `staging./dev.`
  hosts, `*.magento.cloud`, ngrok tunnels, etc. bypass licensing automatically.
  You can also set **Production Environment = No** to bypass on any host.
- HMAC and bundle keys validate **offline** (no phone-home); only SP-XXXX keys
  contact the portal, and results are cached.

## Uninstall
```bash
bin/magento module:disable ETechFlow_AdvancedProductReviews
# optional data removal:
bin/magento setup:upgrade
```
Drop the `etechflow_*` tables manually if you want to remove stored data.

---

## License
OSL-3.0 / AFL-3.0. See [LICENSE.txt](LICENSE.txt).

## Author
ETechFlow — etechflow0@gmail.com
