# Dlabsit XML Feed — Multi-Platform Product Feed Generator for Magento 2

Open-source Magento 2 module that generates product feeds for multiple price-comparison
and marketplace platforms from a single configurable source. Pluggable architecture —
enable only the feeds you need, add more via a single interface.

**Supported feeds out of the box (9 platforms):**

| Platform | Region | Format | URL |
| --- | --- | --- | --- |
| **Skroutz.gr** | Greece | Custom XML (`<mywebstore>`) | `/feed/skroutz` |
| **Google Shopping** | Global | RSS 2.0 + `g:` namespace | `/feed/google` |
| **Facebook / Meta Catalog** | Global | RSS 2.0 + `g:` namespace | `/feed/facebook` |
| **Bing Shopping / Microsoft** | Global | RSS 2.0 + `g:` namespace (Google-compatible) | `/feed/bing` |
| **Bestprice.gr** | Greece | Custom XML (`<store>`) | `/feed/bestprice` |
| **Pricerunner** | Nordic + UK | Custom XML (`<products>`) | `/feed/pricerunner` |
| **Idealo** | DE / EU | Custom XML (Idealo columns) | `/feed/idealo` |
| **Ceneo.pl** | Poland | Custom XML (`<offers>/<o>`) | `/feed/ceneo` |
| **Kelkoo** | Europe (FR/UK/IT/DE/ES/NL) | Custom XML (`<products>`) | `/feed/kelkoo` |

Plus Skroutz Analytics tracking (optional) and a legacy `/skroutz/feed/index` URL
for backwards compatibility with earlier Skroutz-only installs.

**License:** OSL-3.0
**Magento:** 2.4.7 / 2.4.8+
**PHP:** 8.2 / 8.3 / 8.4
**Depends on:** [Dlabsit_Core](https://github.com/dlabsit/m2-core)

---

## Features

### Core
- **Pluggable writers** — add a new feed by implementing `FeedWriterInterface`
- **Streaming XMLWriter** — memory-safe generation for catalogs of any size
- **Batch processing** — configurable batch size
- **Configurable products** handled correctly per platform:
  - Skroutz: 1 product per color, size as `<variations>`
  - Google/Facebook: each variant = its own `<item>`, linked by `g:item_group_id`
  - Bestprice: 1 row per fully-qualified variant
  - Pricerunner: 1 row per variant
- **Category filtering** (include/exclude)
- **Out-of-stock handling** (include/exclude)
- **Automatic gzip** for feeds > 10 MB
- **Dedicated log** at `var/log/xml_feed.log`
- **Cron** generates all enabled feeds on a schedule
- **CLI** for manual/ad-hoc generation

### Skroutz-specific
- Color-split configurables (one `<product>` per color)
- Size variations under `<variations><variation>...</variation></variations>`
- Greek availability strings
- **Skroutz Analytics** — tracking script + ecommerce tracking on checkout success
- CSP whitelist for `skroutza.skroutz.gr`

### Google Shopping-specific
- RSS 2.0 + `xmlns:g="http://base.google.com/ns/1.0"` namespace
- Price format `"99.00 EUR"` (number + space + ISO 4217)
- Availability values: `in_stock` / `out_of_stock`
- Condition: `new` / `refurbished` / `used`
- `g:item_group_id` groups variants of the same parent
- `g:gtin` with **check-digit validation** (8/12/13/14 digits)
- Optional `g:google_product_category`, `g:shipping`, `g:shipping_weight`
- Apparel: `g:gender`, `g:age_group`, `g:color`, `g:size`
- Auto-emits `g:identifier_exists=no` when no brand/GTIN/MPN available

### Facebook / Meta Catalog-specific
- Inherits Google structure but with key differences:
- Availability uses **spaces**: `"in stock"` / `"out of stock"` (not underscores)
- Optional `g:fb_product_category`
- Optional `g:rich_text_description` (HTML in CDATA)
- Optional `g:quantity_to_sell_on_facebook` for Shops checkout

### Bestprice.gr-specific
- Root element `<store>` with top-level `<date>`
- Category path with `->` separator
- `<imagesURL>` container with `<img1>`, `<img2>`... children
- Greek availability strings
- Warranty fields (provider + duration)

### Pricerunner-specific
- Numeric `StockStatus` mode OR enum (InStock/OutOfStock/PreOrder)
- Shipping cost, lead time
- Price in local currency (no currency symbol)

### Bing Shopping / Microsoft Merchant-specific
- Uses Google Shopping format (RSS 2.0 + `g:` namespace) — 100% Google-compatible
- Separate filename/config so you can tune independently from Google

### Idealo-specific
- Fields match Idealo's CSV-importer spec: `sku`, `brand`, `title`, `url`, `price`, `delivery`, `deliveryCosts_*`, `paymentCosts_*`, `eans`, `hans` (MPN), `categoryPath`
- Out-of-stock products are **omitted** from the feed (per Idealo guidance)
- No availability enum — `delivery` field expresses availability
- Images semicolon-separated in `imageUrls`

### Ceneo.pl-specific
- Compact `<offers version="1">/<group>/<o>` structure
- Attributes on `<o>`: `id`, `url`, `price` (PLN), `avail` (0-5), `stock`, `weight`, `basket`
- Category path with `/` separator (Polish taxonomy)
- `<attrs>` block with `<a name="...">value</a>` pairs for Producent/EAN/Kolor/Rozmiar
- `<imgs>/<main url="..."/>` + `<i url="..."/>` for image gallery
- Out-of-stock products are omitted

### Kelkoo-specific
- `<products>/<product>` with tag-based fields (`id`, `title`, `product-url`, `price`, `merchant-category`)
- Title limited to ~80 chars
- Description ~300 chars, no HTML
- Availability: numeric 1 (in stock) to 6 (out of stock)
- Multi-value colours semicolon-separated
- Price in local currency (inferred from Kelkoo country — no currency code in the tag)

---

## Installation

### Composer
```bash
composer require dlabsit/module-xml-feed
bin/magento module:enable Dlabsit_XmlFeed
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Manual
Copy contents to `app/code/Dlabsit/XmlFeed/` then run the four commands above.

---

## Configuration

Navigate to **Stores → Configuration → XML Feeds**.

### 1. General & Shared Settings
Settings that apply to **all feeds**:

- **Unique ID Source** — Product ID / SKU / Custom Attribute
- **Attribute Mapping** — Manufacturer/Brand, MPN, EAN/GTIN, Color, Size, Weight
- **Description Source** — full/short/meta
- **Category Filter** — All / Include selected / Exclude selected
- **Stock** — include out-of-stock yes/no
- **Batch Size** (default: 500)
- **Default VAT Rate** (for Skroutz/Bestprice)
- **Gzip** enable (for feeds > 10 MB)
- **Cron schedule** (default: every 2 hours)

### 2. Per-platform sections

Each section has an **Enable** toggle + **Filename** (defaults like `skroutz.xml`).

- **Skroutz.gr** — availability string, analytics (`SA-XXXX-YYYY`)
- **Google Shopping** — store name, default condition, Google Product Category, apparel defaults, shipping
- **Facebook / Meta Catalog** — store name, condition, FB category, rich description toggle, quantity toggle
- **Bestprice.gr** — availability string, warranty provider/duration
- **Pricerunner** — stock mode (enum/qty), lead time, shipping cost

### 3. Product attributes

Two attributes are auto-created on install (retained from v1 Skroutz module for compatibility):
- `skroutz_ean` — EAN/GTIN
- `skroutz_mpn` — MPN

You can map these to ANY existing attribute via Shared Settings.

---

## Usage

### Generate one feed
```bash
bin/magento xml-feed:generate --feed=google --store-id=1
```

### Generate all enabled feeds
```bash
bin/magento xml-feed:generate --all --store-id=1
```

### Legacy Skroutz command (backwards compatible)
```bash
bin/magento skroutz:feed:generate --store-id=1
```

### Access URLs
- `https://your-store.test/feed/skroutz`
- `https://your-store.test/feed/google`
- `https://your-store.test/feed/facebook`
- `https://your-store.test/feed/bing`
- `https://your-store.test/feed/bestprice`
- `https://your-store.test/feed/pricerunner`
- `https://your-store.test/feed/idealo`
- `https://your-store.test/feed/ceneo`
- `https://your-store.test/feed/kelkoo`
- `https://your-store.test/skroutz/feed/index` (legacy, Skroutz only)

---

## Submitting feeds to platforms

### Skroutz
1. Merchants Panel → Products → XML Feed
2. Submit: `https://your-store.test/feed/skroutz`
3. Validate: https://validator.skroutz.gr

### Google Shopping
1. https://merchants.google.com → Products → Feeds → Add primary feed
2. Input method: **Scheduled fetch**
3. File URL: `https://your-store.test/feed/google`

### Facebook / Meta Catalog
1. Commerce Manager → Catalog → Data sources
2. Add data source → **Scheduled feed**
3. URL: `https://your-store.test/feed/facebook`

### Bestprice.gr
1. Merchant portal: https://merchants.bestprice.gr/
2. Submit feed URL
3. Validate: https://merchants.bestprice.gr/xml-validator/

### Pricerunner
1. Merchant Center → Feed configuration
2. URL: `https://your-store.test/feed/pricerunner`

### Bing Shopping / Microsoft Merchant
1. https://ads.microsoft.com → Tools → Microsoft Merchant Center
2. Add feed → Scheduled URL fetch
3. URL: `https://your-store.test/feed/bing` (uses Google format, MSFT compatible)

### Idealo
1. https://partner.idealo.com → Offer management → Add offer feed
2. URL: `https://your-store.test/feed/idealo`
3. Supports HTTP-Basic auth + gzip

### Ceneo.pl
1. https://panel.ceneo.pl → Integracja → Plik z ofertami
2. URL: `https://your-store.test/feed/ceneo`
3. Must be in PLN; Polish category paths recommended

### Kelkoo
1. https://merchants.kelkoogroup.com → Feed setup
2. URL: `https://your-store.test/feed/kelkoo`
3. File naming convention: `<sitename>_kelkoo_<country>.xml`

---

## Extending — add a new feed

1. Create a class extending `Dlabsit\XmlFeed\Model\Feed\Writer\AbstractWriter`
2. Implement `getCode()`, `getLabel()`, `getDefaultFilename()`, `startDocument()`, `endDocument()`, `writeSimpleProduct()` (optionally override `writeConfigurableProduct()`)
3. Register it in `etc/di.xml` under `Dlabsit\XmlFeed\Model\Feed\WriterPool.writers`
4. Optionally add admin config section and controller

Example stub:
```php
class MyPlatformWriter extends AbstractWriter {
    public function getCode(): string { return 'myplatform'; }
    public function getLabel(): string { return 'My Platform'; }
    public function getDefaultFilename(): string { return 'myplatform.xml'; }

    protected function startDocument(int $storeId): void {
        $this->xml->startElement('root');
    }
    protected function endDocument(): void {
        $this->xml->endElement();
    }
    protected function writeSimpleProduct(Product $product, int $storeId): void {
        $this->xml->startElement('product');
        $this->writeElement('id', $this->mapper->getUniqueId($product, $storeId));
        // ...
        $this->xml->endElement();
    }
}
```

---

## Troubleshooting

- **Feed URL returns 404?** Enable the feed in admin (it's off by default).
- **Empty feed?** Check product visibility, status (enabled), category filter, stock filter.
- **Log file:** `var/log/xml_feed.log`
- **Google GTIN validation fails?** The module validates check-digit; a malformed EAN is silently skipped. Fix the value in Magento.
- **Memory errors on big catalogs?** Reduce batch size; ensure `include_out_of_stock` is off if you don't need those products.

---

## Upgrading from Dlabsit_SkroutzFeed v1

1. Install this module: `composer require dlabsit/module-xml-feed`
2. Disable legacy: `bin/magento module:disable Dlabsit_SkroutzFeed`
3. Enable new: `bin/magento module:enable Dlabsit_XmlFeed`
4. `bin/magento setup:upgrade && setup:di:compile && cache:flush`
5. Your `skroutz_ean`/`skroutz_mpn` product attributes are **reused**; no data loss.
6. Admin config keys changed — re-configure under **XML Feeds** section.
7. The legacy `/skroutz/feed/index` URL still works.
8. The legacy `skroutz:feed:generate` CLI alias still works.

---

## License

Open Software License (OSL 3.0) — see [LICENSE.md](LICENSE.md).

---

## Contributing / Support

- Issues: https://github.com/dlabsit/lab-xml-feed/issues
- PRs welcome — adding a new platform is ~100 lines of code (see Extending section above).
