# Contract: Price-list XML format

**Feature**: `001-sidrena-cijena-compliance` | **Version**: 1.0

This is the documented, deterministic XML schema required by FR-036/FR-037. No XML schema is prescribed by the Odluka, so this schema is the plugin's documented format and carries an explicit version identifier.

## Document rules

| Property | Value |
|----------|-------|
| Encoding | UTF-8, no BOM |
| XML declaration | `<?xml version="1.0" encoding="UTF-8"?>` |
| Element names | ASCII transliteration of the Croatian field names (`sifra`, `sidrenaCijena`, …) |
| Empty values | Element omitted entirely (no empty elements) |
| Escaping | All values XML-escaped (`&amp;`, `&lt;`, `&gt;`, `&quot;`, `&apos;`) |
| Indentation | Two spaces, LF line endings |
| Booleans | `true` / `false` |
| Decimals | Decimal point `.` with two decimal places (XML convention; the CSV contract uses comma) |
| Determinism | Same catalog state, rule version and location produce byte-identical output (excluding the generation timestamp) |

## Structure

```xml
<?xml version="1.0" encoding="UTF-8"?>
<cjenik verzija="1.0" pravilo="2026-10-01" generirano="2026-10-01T07:50:00+02:00">
  <prodajnoMjesto oznaka="P1" naziv="Prodavaonica Vlaška" oblik="prodavaonica"
                  adresa="Vlaška 68" grad="Zagreb">
    <stavka vrsta="proizvod">
      <naziv>Kava mljevena 250 g</naziv>
      <sifra>KAV-250</sifra>
      <marka>Franck</marka>
      <jedinicaMjere>kg</jedinicaMjere>
      <cijenaZaJedinicuMjere>28.00</cijenaZaJedinicuMjere>
      <maloprodajnaCijena>6.99</maloprodajnaCijena>
      <posebanOblikProdaje>true</posebanOblikProdaje>
      <nazivPosebnogOblikaProdaje>akcija</nazivPosebnogOblikaProdaje>
      <sidrenaCijena>7.49</sidrenaCijena>
      <barkod>3851234567890</barkod>
      <dostupnost>dostupno</dostupnost>
    </stavka>
    <stavka vrsta="usluga">
      <naziv>Šišanje kratke kose</naziv>
      <maloprodajnaCijena>15.00</maloprodajnaCijena>
      <posebanOblikProdaje>false</posebanOblikProdaje>
      <sidrenaCijena>20.00</sidrenaCijena>
    </stavka>
  </prodajnoMjesto>
</cjenik>
```

A canonical example lives at `tests/fixtures/example-price-list.xml`.

## Element semantics

| Element / attribute | Required | Notes |
|---------------------|----------|-------|
| `cjenik/@verzija` | yes | Format version (`1.0`) |
| `cjenik/@pravilo` | yes | Legal-rule version identifier in force at generation |
| `cjenik/@generirano` | yes | ISO 8601 with site time zone offset |
| `prodajnoMjesto/@oznaka` | yes | Facility identifier (filename component) |
| `prodajnoMjesto/@naziv`, `@oblik`, `@adresa`, `@grad` | yes (address/grad for physical locations) | Location metadata |
| `stavka/@vrsta` | yes | `proizvod` or `usluga` |
| `naziv` | yes | Item name |
| `sifra` | yes for products | Code/SKU |
| `marka`, `jedinicaMjere`, `cijenaZaJedinicuMjere`, `barkod` | product-only, optional | Omitted when not applicable |
| `maloprodajnaCijena` | yes | Effective retail price at generation time |
| `posebanOblikProdaje` | yes | `true`/`false` |
| `nazivPosebnogOblikaProdaje` | when `posebanOblikProdaje=true` | Sale name |
| `sidrenaCijena` | yes | Anchor price; omitted only when no anchor exists (validation warning) |
| `dostupnost` | yes for products | `dostupno` / `nedostupno` |

Row order matches the CSV contract: products first, then services, each ordered by `sifra`, `naziv`, item ID.
