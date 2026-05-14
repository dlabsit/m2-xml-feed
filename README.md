<div align="center">

# Dlabsit XML Feed

**Pluggable product feed generator for Magento 2.**
One module, 11 supported channels, one interface to add more.

[![Latest Version](https://img.shields.io/packagist/v/dlabsit/module-xml-feed?style=flat-square)](https://packagist.org/packages/dlabsit/module-xml-feed)
[![PHP](https://img.shields.io/packagist/php-v/dlabsit/module-xml-feed?style=flat-square)](composer.json)
[![Magento](https://img.shields.io/badge/Magento-2.4.7%20%7C%202.4.8-orange?style=flat-square)](https://github.com/magento/magento2)
[![License: OSL-3.0](https://img.shields.io/badge/license-OSL--3.0-blue.svg?style=flat-square)](LICENSE.md)

</div>

---

## What it does

Generates XML product feeds for price-comparison sites and marketplaces from a single Magento 2 source. Pluggable — enable only the channels you need.

| Channel | Region | Endpoint |
|---|---|---|
| Skroutz | GR | `/feed/skroutz` |
| Google Shopping | Global | `/feed/google` |
| Facebook / Meta Catalog | Global | `/feed/facebook` |
| Bing Shopping | Global | `/feed/bing` |
| Bestprice | GR | `/feed/bestprice` |
| Pricerunner | Nordic / UK | `/feed/pricerunner` |
| Idealo | DE / EU | `/feed/idealo` |
| Ceneo | PL | `/feed/ceneo` |
| Kelkoo | EU | `/feed/kelkoo` |
| Shopflix | GR | `/feed/shopflix` |
| eMAG Marketplace | RO / BG / HU | `/feed/emag` |

## Highlights

- Streaming `XMLWriter` — memory-safe for any catalog size
- Configurable products handled per platform spec (color-split / variant rows / `item_group_id`)
- Cron + CLI + on-demand admin generation
- Auto-gzip for feeds > 10 MB
- Category / stock filters, attribute mapping, GTIN check-digit validation

## Install

```bash
composer require dlabsit/module-xml-feed
bin/magento module:enable Dlabsit_XmlFeed
bin/magento setup:upgrade && bin/magento setup:di:compile
bin/magento cache:flush
```

Configure under **Stores → Configuration → XML Feeds**.

## Usage

```bash
bin/magento xml-feed:generate --feed=google --store-id=1   # one feed
bin/magento xml-feed:generate --all --store-id=1           # all enabled
```

## Documentation

- [Configuration reference](docs/configuration.md)
- [Per-channel field mappings](docs/channels.md)
- [Submitting feeds to platforms](docs/submission.md)
- [Adding a new channel](docs/extending.md)
- [Upgrading from `Dlabsit_SkroutzFeed` v1](docs/upgrade-v1.md)
- [Troubleshooting](docs/troubleshooting.md)

## Requirements

- PHP **8.2+**
- Magento **2.4.7** or **2.4.8**
- [`dlabsit/module-core`](https://github.com/dlabsit/m2-core) **≥ 1.0**

## Contributing

PRs welcome. Adding a new platform ≈ 100 LOC — see [docs/extending.md](docs/extending.md).
Bugs & feature requests: [GitHub Issues](https://github.com/dlabsit/m2-xml-feed/issues).

## License

[Open Software License v3.0](LICENSE.md) © Dlabsit
