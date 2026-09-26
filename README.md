<div align="center">

<img src=".github/readme-banner.svg" alt="Sidrena Cijena EU — Croatian anchor price and price-list plugin for WordPress" width="100%">

### Croatian anchor prices, 30-day sale history, and published price lists for WordPress

An open-source WordPress plugin for `sidrena cijena`, Croatian price transparency,
and WooCommerce price history.

[Main site](https://sidrenacijena.eu/) · [Live demo shop](https://wordpress.sidrenacijena.eu/) · [Download 2.0.0 for WordPress](https://github.com/administrakt0r/sidrena-cijena-eu/releases/latest/download/sidrena-cijena-eu.zip)

![Version 2.0.0](https://img.shields.io/badge/version-2.0.0-1d6b55?style=flat-square)
![WordPress 6.8+](https://img.shields.io/badge/WordPress-6.8%2B-3858e9?style=flat-square&logo=wordpress&logoColor=white)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-1d6b55?style=flat-square)

</div>

## Sidrena Cijena EU for WordPress and WooCommerce

Sidrena Cijena EU helps Croatian merchants record and display the applicable
anchor price, calculate the separate lowest price in the previous 30 days for
special sales, and publish daily machine-readable CSV/XML price lists. It works
with WooCommerce or its built-in standalone catalog.

The plugin also provides a public HTML price-list archive, a searchable current
price list, validation tools, audit history, and WP-CLI commands. Generated
price lists and shop-location details are public by design; merchants choose
what to publish and remain responsible for checking their catalog and locations.

> **Legal notice:** Sidrena Cijena EU is a technical aid, not legal advice or a
> guarantee of compliance. Requirements can depend on the merchant, product,
> service, sales channel, and applicable law. Verify your setup with qualified
> legal or accounting advisers.

## Features

- Append-only anchor-price records with reference dates, evidence, authorship,
  and reasoned corrections.
- Storefront display of current and anchor prices, including selected
  WooCommerce variations.
- A separate 30-day lowest-price calculation for classified special sales; it
  is never substituted for the anchor price.
- Per-location daily CSV and XML price-list generation, validation, checksums,
  and retained publication history.
- Public searchable price-list and archive pages, with optional footer and
  WordPress XML sitemap links enabled by default.
- JSON, CSV, and XML price endpoints, plus CSV import with preview and
  transactional commit, compliance reports, and WP-CLI commands.
- WooCommerce integration is optional; standalone catalog workflows remain
  available without WooCommerce.

## Download and install

1. Download the [latest WordPress upload ZIP](https://github.com/administrakt0r/sidrena-cijena-eu/releases/latest/download/sidrena-cijena-eu.zip).
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**, choose the
   ZIP, and install it.
3. Activate **Sidrena Cijena EU** and follow the setup guide to configure
   locations, legal reference dates, and price evidence.
4. Generate a price list and verify the public current list and archive before
   relying on scheduled publication.

Requirements: WordPress 6.8 or later, PHP 7.4 or later, and MySQL 5.7 or
MariaDB 10.4 or later. WooCommerce 8 or later is optional.

## Relevant Croatian and EU rules

These primary sources inform the plugin's documented behavior. Read the current
rules for your circumstances; the plugin does not replace legal advice.

- [NN 101/2026-1212 — Decision on displaying the additional price as a direct
  price-control measure](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html)
- [NN 101/2026-1213 — Decision on publishing product and service price lists](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html)
- [Croatian Ministry of Economy clarification for the 1 October 2026 rules](https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440)
- [Directive (EU) 2019/2161 (Omnibus Directive)](https://eur-lex.europa.eu/eli/dir/2019/2161/oj/eng)
- [Directive 2011/83/EU on consumer rights](https://eur-lex.europa.eu/eli/dir/2011/83/oj/eng)

See [the implementation notes and legal assumptions](docs/legal-rules.md) for
the reference dates, 30-day calculation, machine-readable price lists, and
documented uncertainties.

## Development

```bash
git clone https://github.com/administrakt0r/sidrena-cijena-eu.git
cd sidrena-cijena-eu
composer install
npm install
npm run wp-env start
```

Run the available checks with `tools/verify.sh`. The WordPress integration and
Playwright browser suites require Docker and the local wp-env test environment.
See [development and test instructions](docs/development.md) before running
them.

## More information

- [Croatian plugin website](https://sidrenacijena.eu/)
- [Live WordPress demo shop](https://wordpress.sidrenacijena.eu/)
- [Implementation and legal notes](docs/legal-rules.md)
- [Price-list format](docs/price-list-format.md)
- [WooCommerce integration](docs/woocommerce.md)
- [Releases and WordPress upload ZIPs](https://github.com/administrakt0r/sidrena-cijena-eu/releases)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
