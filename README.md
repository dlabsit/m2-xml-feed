<div align="center">

<a href="https://dlabsit.nl">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset=".github/assets/logo-dark.svg">
    <img alt="d-labs it" src=".github/assets/logo-light.svg" width="220">
  </picture>
</a>

# XML Feed for Magento 2

**Pluggable product feed generator for 11 marketplaces and price-comparison engines.**
One module, one feed registry, one extension point to add more.

[![Latest Version](https://img.shields.io/packagist/v/dlabsit/module-xml-feed?style=flat-square)](https://packagist.org/packages/dlabsit/module-xml-feed)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)](composer.json)
[![Magento](https://img.shields.io/badge/Magento-2.4.7%20%7C%202.4.8-EE672F?style=flat-square&logo=magento&logoColor=white)](https://github.com/magento/magento2)
[![License: FSL-1.1-MIT](https://img.shields.io/badge/license-FSL--1.1--MIT-blue.svg?style=flat-square)](LICENSE.md)

</div>

---

## Table of contents

- [What it does](#what-it-does)
- [Supported channels](#supported-channels)
- [How it works](#how-it-works)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Configuration](#configuration)
- [CLI usage](#cli-usage)
- [Cron](#cron)
- [Public feed URLs](#public-feed-urls)
- [Architecture](#architecture)
- [Extending: adding a channel](#extending-adding-a-channel)
- [Troubleshooting](#troubleshooting)
- [Requirements](#requirements)
- [Contributing](#contributing)
- [License](#license)

---

## What it does

Turns your Magento 2 catalog into XML feeds that marketplaces and price-comparison sites can consume. The module ships writers for 11 platforms and a registry-driven setup so a single store can run multiple feeds per channel (for example: one Google feed for adults, one for kids, each filtered to a different category tree).

It is built around a streaming `XMLWriter`, so feed size is bounded only by disk space, not by PHP memory.

## Supported channels

| Channel | Region | Default filename | Format |
|---|---|---|---|
| Skroutz | GR | `skroutz.xml` | Custom `<mywebstore>` (color-grouped, size variations) |
| Google Shopping | Global | `google.xml` | RSS 2.0 + `xmlns:g` (per-variant `item_group_id`) |
| Facebook / Meta Catalog | Global | `facebook.xml` | Google-compatible, Facebook-specific tags |
| Bing Shopping | Global | `bing.xml` | Google-compatible |
| Bestprice | GR | `bestprice.xml` | Greek price-comparison format |
| Pricerunner | Nordic / UK | `pricerunner.xml` | TSV-like XML with stock enums |
| Idealo | DE / EU | `idealo.xml` | DE delivery + payment costs |
| Ceneo | PL | `ceneo.xml` | Polish availability codes |
| Kelkoo | EU | `kelkoo.xml` | Kelkoo Merchant format |
| Shopflix | GR | `shopflix.xml` | Skroutz-style with Shopflix extensions |
| eMAG Marketplace | RO / BG / HU | `emag.xml` | eMAG offers/products XML |

## How it works

```
       Admin / CLI / Cron
              │
              ▼
   ┌──────────────────────┐
   │  Feed (DB registry)  │  slug, channel_code, store_id, filters
   └──────────┬───────────┘
              │
              ▼
   ┌──────────────────────┐
   │      Generator       │  picks writer by channel_code
   └──────────┬───────────┘
              │
      ┌───────┴────────────────────┐
      ▼                            ▼
┌──────────────┐          ┌──────────────────┐
│ProductCollect│ yields → │  ChannelWriter   │ → pub/media/xmlfeed/<file>.xml
└──────────────┘          └──────────────────┘
                                  │
                                  ▼
                         optional gzip if > 10 MB
```

Every feed lives in the `dlabsit_xmlfeed_feed` table as a row. The Generator picks the matching writer from `WriterPool` (assembled via DI), streams products from `ProductCollector`, and writes the XML to `pub/media/xmlfeed/`. The frontend controller serves the latest generated file, transparently negotiating gzip via `Accept-Encoding`.

## Installation

```bash
composer require dlabsit/module-xml-feed
bin/magento module:enable Dlabsit_XmlFeed
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Setup patches will:

- Create `skroutz_ean` and `skroutz_mpn` product attributes (grouped under "XML Feed").
- Create `color` and `size` attributes if they are missing (clean Magento installs don't have them).

## Quick start

1. Open **Stores → Configuration → Dlabsit - XML Feed** and set shared defaults (manufacturer attribute, EAN attribute, VAT rate, etc.).
2. Open **Catalog → XML Feeds** in the admin menu and add a feed:
   - Pick a channel (e.g. Google Shopping).
   - Give it a slug (e.g. `google-adult`).
   - Set the store view, filename, and category include/exclude rules.
3. Generate it once from the admin grid or via CLI:
   ```bash
   bin/magento xml-feed:generate --slug=google-adult
   ```
4. The file is now at `pub/media/xmlfeed/google-adult.xml` and served at `https://yourstore.com/feed/google-adult`.

## Configuration

All shared settings live under **Stores → Configuration → Dlabsit - XML Feed**. Most settings are scope-aware (default / website / store view).

### Shared

| Setting | Purpose |
|---|---|
| `Unique ID Source` | Use product ID, SKU, or a custom attribute for the unique identifier emitted by every writer. |
| `Manufacturer / Brand Attribute` | Which product attribute holds the brand name. |
| `MPN Attribute` | Source for the Manufacturer Part Number (defaults to SKU). |
| `EAN / GTIN Attribute` | Source for the barcode. Empty by default. |
| `Color` / `Size` / `Weight Attribute` | Source attribute codes for configurable resolution and apparel feeds. |
| `Description Source` | `description` (default) or `short_description`. |
| `Category Filter Mode` | `all` (no filter), `include`, or `exclude` shared category set. |
| `Include Out of Stock` | Off by default. |
| `Batch Size` | Products per page when streaming. Default 500. Lower for memory-tight servers. |
| `Default VAT Rate (%)` | Used by Skroutz / Bestprice. Default 24. |
| `Enable Gzip` | When on, feeds larger than 10 MB are also written as `.gz` and served gzip-compressed when the client supports it. |

### Per channel

Each channel has its own section with platform-specific knobs:

- **Google / Facebook / Bing:** condition, default category, gender, age group, shipping country/service/price.
- **Skroutz:** default availability string, optional Skroutz Analytics shop account ID.
- **Bestprice:** Greek availability string, warranty provider and duration.
- **Pricerunner:** stock mode (`enum` or `quantity`), lead time, shipping cost.
- **Idealo:** German delivery time, condition (`Neu` / `Gebraucht`), DHL and prepayment costs.
- **Ceneo:** availability code, basket support flag.
- **Kelkoo:** default category, warranty, delivery cost and time.

See `etc/config.xml` for the full default schema.

## CLI usage

```bash
# Generate one feed by slug
bin/magento xml-feed:generate --slug=google-adult

# Generate every active feed in the registry
bin/magento xml-feed:generate --all

# Show available slugs (also printed when neither flag is given)
bin/magento xml-feed:generate
```

Each run logs duration, output path, and final file size to `var/log/xmlfeed.log`.

## Cron

A single cron job runs every two hours by default:

```
0 */2 * * *  (configurable under General → Cron Schedule)
```

It iterates the feed registry and regenerates every row where `is_active = 1`, in `sort_order` ascending. Failures are logged and do not stop the queue.

## Public feed URLs

Every feed is reachable at:

```
https://yourstore.com/feed/<slug>
```

Resolved by `Dlabsit\XmlFeed\Controller\Feed\Dynamic`. Behavior:

- Returns `404` if the slug doesn't exist, is disabled, or has not been generated yet.
- Serves the gzipped variant when both are present and the client sends `Accept-Encoding: gzip`.
- Always emits `Content-Type: application/xml; charset=UTF-8` and `Vary: Accept-Encoding`.

## Architecture

### Layers

```
Api/                Contracts (FeedInterface, FeedRepositoryInterface,
                    FeedGeneratorInterface, FeedWriterInterface)
Block/              Admin grid, edit form, system config info banner,
                    frontend analytics blocks
Console/Command/    xml-feed:generate
Controller/         Adminhtml/Feed CRUD + frontend Feed/Dynamic
Cron/               GenerateFeeds — iterates active feeds
Helper/Config       Centralized scope-aware config reader
Logger/             Dedicated logger writing to var/log/xmlfeed.log
Model/Feed/         Generator, WriterPool, ProductCollector,
                    AttributeMapper, FeedRepository, ResourceModel
Model/Feed/Writer/  AbstractWriter + one writer per channel
Setup/Patch/Data/   EAV attribute patches, legacy config migrator
etc/                module.xml, db_schema.xml, di.xml, system.xml,
                    config.xml, crontab.xml, routes (admin + frontend)
view/               Admin grid layout, frontend analytics templates
```

### Key types

- **`FeedWriterInterface`** — one method (`write(string $filePath, \Generator $productSource, int $storeId)`) plus identity helpers. Implementations extend `AbstractWriter`, which owns the XMLWriter lifecycle, configurable-product dispatch, UTF-8 sanitization, and CDATA escape.
- **`WriterPool`** — DI-assembled map of `code => FeedWriterInterface`. Adding a writer means appending to the `arguments` array in `etc/di.xml`.
- **`ProductCollector`** — paginated generator over `Magento\Catalog\Model\ResourceModel\Product\Collection` with stock, visibility, type, and category filters applied. Used by both legacy single-channel generation and the new per-Feed generation.
- **`AttributeMapper`** — normalizes Magento attributes into values writers can use directly: brand, MPN, EAN, color label, size label, weight in grams, deepest category path, validated image URLs, and stock quantity. Caches category paths within a single run.

### Storage

A single InnoDB table `dlabsit_xmlfeed_feed`:

| Column | Purpose |
|---|---|
| `slug` | URL slug, unique per store. |
| `channel_code` | Maps to a writer in the pool. |
| `store_id` | Store view scope. |
| `is_active` | Cron and `--all` only touch active feeds. |
| `filter_mode` + `category_ids` | `all` / `include` / `exclude`. |
| `channel_settings` | JSON for per-feed overrides. |
| `sort_order` | Run order in cron. |

### Configurable product handling

Every channel resolves configurables according to its spec:

- **Google / Bing / Facebook:** one `<item>` per child variant, all sharing `g:item_group_id`. Variants get per-child `g:id`, color, size, MPN, EAN.
- **Skroutz:** one `<product>` per *color group*. Sizes inside a color are emitted as `<variation>` rows with their own MPN / EAN / quantity. Color groups get a unique link via a `#skroutz_color=` URL fragment so the platform doesn't flag duplicate URLs.
- **Idealo / Pricerunner / Ceneo / Kelkoo:** flatten to one row per child variant.
- **Facebook** extends `GoogleShoppingWriter` and overrides `googleAvailability()` (spaces instead of underscores) plus `afterItemTags()` for `g:fb_product_category` and `g:rich_text_description`.

### Safety

- `AbstractWriter::sanitizeXmlText` strips XML 1.0 illegal control characters and recovers from invalid UTF-8 byte sequences without aborting the whole feed.
- `sanitizeCdata` breaks any literal `]]>` sequence inside CDATA blocks.
- Files are written to a `.tmp` path, then atomically renamed — clients never see a partially written feed.
- Gzip compression streams 8 KB chunks through `gzopen`/`gzwrite`, so memory stays flat for 100+ MB feeds.
- Google Shopping feeds run a GTIN check-digit validator before emitting `g:gtin`.

## Extending: adding a channel

Adding a new platform is roughly 100 lines of PHP plus DI wiring.

1. Create `Model/Feed/Writer/MyChannelWriter.php` extending `AbstractWriter` (or `GoogleShoppingWriter` if the format is Google-compatible).
2. Implement `getCode()`, `getLabel()`, `getDefaultFilename()`, `startDocument()`, `endDocument()`, and `writeSimpleProduct()`. Override `writeConfigurableProduct()` if the platform needs grouped or split variants.
3. Register the writer in `etc/di.xml`:
   ```xml
   <type name="Dlabsit\XmlFeed\Model\Feed\WriterPool">
       <arguments>
           <argument name="writers" xsi:type="array">
               <item name="mychannel" xsi:type="object">Dlabsit\XmlFeed\Model\Feed\Writer\MyChannelWriter</item>
           </argument>
       </arguments>
   </type>
   ```
4. Add a config section in `etc/adminhtml/system.xml` and defaults in `etc/config.xml` if the channel has its own knobs.
5. Create a feed row through the admin UI (or a data patch) using the new `channel_code`.

The writer is now available to CLI, cron, and the public `/feed/<slug>` endpoint.

## Troubleshooting

**Feed file is missing or stale.** Check `bin/magento cron:run --group=xmlfeed`. Look at `var/log/xmlfeed.log` for errors. The cron schedule is in **Configuration → Dlabsit - XML Feed → General → Cron Schedule**.

**`No feed found with slug 'X'`.** The slug doesn't exist in the registry or you spelled it differently. Run `bin/magento xml-feed:generate` with no arguments to see the available slugs.

**Skroutz reports duplicate links.** Configurable products with color attributes get a `#skroutz_color=<id>` fragment automatically. If you still see duplicates, check that each configurable child has a distinct color option.

**Google rejects `g:gtin`.** The built-in GTIN validator enforces correct length and check digit. If the field is dropped, your EAN attribute holds an invalid value for that product.

**Out-of-memory during generation.** Lower the batch size under **Shared Settings → Batch Size**. Default 500 is safe for ~512 MB PHP processes on 50k+ catalogs.

**Feed file exceeds 10 MB and downloads slowly.** Enable **Shared Settings → Enable Gzip**. The next run will also write a `.gz` companion, and the frontend controller will serve it to gzip-aware clients automatically.

**XML output contains `&#xFFFD;` or strange characters.** Source data has invalid UTF-8 bytes. The sanitizer attempts `iconv //IGNORE` and a fallback `mb_convert_encoding`; if symptoms persist, clean the product attribute at source.

## Requirements

- PHP **8.2+**
- Magento **Open Source / Commerce 2.4.7** or **2.4.8**
- [`dlabsit/module-core`](https://github.com/dlabsit/m2-core) **>= 1.0** (admin tab grouping, license validation contract, module registry)

## Contributing

Bug reports and pull requests are welcome at [GitHub Issues](https://github.com/dlabsit/m2-xml-feed/issues).

By submitting a pull request you agree that your contribution is licensed under the same FSL-1.1-MIT terms as the rest of this repository (inbound = outbound).

Project conventions:

- PHP 8.2 syntax, `declare(strict_types=1)`, constructor property promotion.
- Magento Coding Standard, severity 8: `composer lint`.
- PHPStan level matches the project baseline: `composer phpstan`.
- Both checks are wired into `composer ci`.

## License

[Functional Source License v1.1 with MIT Future License](LICENSE.md) (FSL-1.1-MIT)
Copyright (c) 2026 Dlabsit.

You may:

- Install, run, and use this software on any Magento store you or your company operate, including commercial use.
- Modify the source code for your own use.
- Redistribute unmodified copies free of charge with this license intact.

You may not:

- Sell this software, in original or modified form.
- Offer it as a hosted or managed service that competes with Dlabsit.
- Repackage it as your own product.

Two years after each release date, that release becomes available under the standard MIT license automatically. See [LICENSE.md](LICENSE.md) for the full text.

---

<div align="center">

Made by [Dlabsit](https://dlabsit.nl). Other Magento 2 modules in development: Courier Center, Elta Courier, Skroutz Marketplace.

</div>
