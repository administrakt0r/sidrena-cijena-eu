<div align="center">

<img src=".github/readme-banner.svg" alt="Sidrena Cijena EU — hrvatski WordPress dodatak za sidrenu cijenu, najnižu cijenu u 30 dana i javne cjenike" width="100%">

### Sidrena cijena, najniža cijena u zadnjih 30 dana i dnevni javni cjenici

Besplatan WordPress dodatak za hrvatske trgovce — radi s WooCommerceom ili s ugrađenim samostalnim katalogom.

[![Preuzmi dodatak](https://img.shields.io/badge/preuzmi-sidrena--cijena--eu.zip-1d6b55?style=flat-square)](https://github.com/administrakt0r/sidrena-cijena-eu/releases/latest/download/sidrena-cijena-eu.zip)
[![Verzija 2.0.14](https://img.shields.io/badge/verzija-2.0.14-1d6b55?style=flat-square)](https://github.com/administrakt0r/sidrena-cijena-eu/releases)
[![WordPress 6.8+](https://img.shields.io/badge/WordPress-6.8%2B-3858e9?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Licenca GPL-2.0-or-later](https://img.shields.io/badge/licenca-GPL--2.0--or--later-1d6b55?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

[Glavna stranica](https://sidrenacijena.eu/) · [Demo trgovina](https://wordpress.sidrenacijena.eu/) · [Preuzmi dodatak](https://github.com/administrakt0r/sidrena-cijena-eu/releases/latest/download/sidrena-cijena-eu.zip) · [Sva izdanja](https://github.com/administrakt0r/sidrena-cijena-eu/releases)

</div>

## Sadržaj

- [O dodatku](#o-dodatku)
- [Što dodatak radi](#što-dodatak-radi)
- [Kako radi](#kako-radi)
- [Brzi početak](#brzi-početak)
- [Zahtjevi](#zahtjevi)
- [Javna sučelja](#javna-sučelja)
- [WP-CLI naredbe](#wp-cli-naredbe)
- [Pravne napomene](#pravne-napomene)
- [Službeni propisi i izvori](#službeni-propisi-i-izvori)
- [Privatnost i zaštita podataka](#privatnost-i-zaštita-podataka)
- [Repozitorij i izdanja](#repozitorij-i-izdanja)
- [Česta pitanja](#česta-pitanja)
- [Licenca](#licenca)

## O dodatku

Sidrena Cijena EU pomaže trgovcima bilježiti i prikazivati primjenjivu referentnu (sidrenu) cijenu, izračunavati zasebnu najnižu cijenu u prethodnih 30 dana za posebne oblike prodaje te objavljivati dnevne cjenike u strojno čitljivim CSV i XML formatima.

Uključuje javni HTML arhiv cjenika, aktualni cjenik s pretraživanjem, provjere podataka, povijest izmjena i WP-CLI naredbe. Objavljeni cjenici i podaci o prodajnim mjestima javno su dostupni; trgovac odabire što objavljuje i odgovoran je za točnost svojih podataka.

> **Napomena:** Dodatak je tehnički alat, a ne pravni savjet niti jamstvo usklađenosti. Obveze ovise o trgovcu, proizvodu, usluzi, kanalu prodaje i važećim propisima. Provjerite primjenu pravila sa stručnom osobom.

## Što dodatak radi

- **Evidencija sidrene cijene** — trajni zapisi s datumom, izvorom, autorom i razlogom svakog ispravka, uz potpun revizijski trag.
- **Prikaz u trgovini** — aktualna i sidrena cijena uz proizvod, uključujući odabrane WooCommerce varijacije, s više gotovih prikaza (tooltip, minimal, tag, accent, subtle) i mogućnošću skrivanja datuma.
- **Najniža cijena u zadnjih 30 dana** — zaseban izračun za propisane posebne oblike prodaje (akcija, sniženje, sezonsko sniženje, rasprodaja), s objašnjenjem razdoblja.
- **Dnevni cjenici** — CSV i XML za svako prodajno mjesto, generiranje svaki dan u 08:00, uz validaciju, kontrolne sažetke, kontrolne sume i arhivu objava.
- **Javna sučelja** — cjenik s pretraživanjem, arhiva objava, REST, CSV i XML izlazi te poveznice u podnožju i u WordPress XML sitemapu (zadano uključeno).
- **Kontrola podataka** — validator s razinama ozbiljnosti, uvoz CSV-a s pregledom i povratom, izvještaji, strukturirane zapise i obrazložene ispravke.
- **Nema izmišljanja povijesti** — nedostajući povijesni podaci prijavljuju se kao nalaz; automatski se bilježe samo stvarno viđene prve ponude.
- **WooCommerce nije obavezan** — jednaki tijek rada radi i s ugrađenim samostalnim katalogom.
- **WP-CLI** — status, validacija, generiranje, povijest, uvoz i čišćenje iz terminala.

## Kako radi

1. **Postavke** — unesite prodajna mjesta, način prikaza i pravna pravila (referentni datumi).
2. **Cijene** — evidentirajte sidrene cijene ručno, uvozom CSV-a ili automatski za stvarno viđene prve ponude.
3. **Posebna prodaja** — evidentirajte akcije i sniženja; dodatak za njih računa najnižu cijenu u zadnjih 30 dana.
4. **Objava** — svaki dan u 08:00 dodatak generira CSV i XML cjenik po prodajnom mjestu i puni arhivu objava.
5. **Provjera** — pregledajte javni cjenik, arhivu i feedove prije nego se oslonite na automatsko objavljivanje.

## Brzi početak

1. Preuzmite [ZIP za učitavanje u WordPress](https://github.com/administrakt0r/sidrena-cijena-eu/releases/latest/download/sidrena-cijena-eu.zip).
2. U administraciji WordPressa otvorite **Dodaci → Dodaj novi dodatak → Učitaj dodatak**, odaberite ZIP i instalirajte ga.
3. Aktivirajte **Sidrena Cijena EU** te u **Sidrena cijena → Postavke** pregledajte referentne datume i način prikaza.
4. Unesite prodajna mjesta, zatim evidenticirajte ili uvezite sidrene cijene za svoj katalog.
5. Izradite cjenik i provjerite javni prikaz, arhivu i feedove prije oslanjanja na automatsko objavljivanje.

Ugrađeni vodič za postavljanje (checklist) vodi kroz prodajna mjesta, pravna pravila, sidrene cijene, cjenike i završnu provjeru.

## Zahtjevi

| Komponenta | Zahtjev |
| --- | --- |
| WordPress | 6.8 ili noviji (testirano do 7.1) |
| PHP | 7.4 – 8.4 |
| Baza podataka | MySQL 5.7+ ili MariaDB 10.4+ |
| WooCommerce | 8+ (opcionalno) |
| WP-CLI | opcionalno, za naredbe iz terminala |

## Javna sučelja

### Shortcodeovi

| Shortcode | Opis | Parametri |
| --- | --- | --- |
| `[sidrena_cijena]` | Aktualna cijena, sidrena cijena i najniža cijena u 30 dana za jedan proizvod | `product_id`, `variation_id`, `show_current`, `show_anchor`, `show_30_day`, `label`, `thirty_day_label` |
| `[sidrena-cjenik]` | Javni cjenik s pretraživanjem i straničenjem | `q`, `type`, `paged` |
| `[sidrena-arhiva]` | Arhiva objavljenih cjenika | `paged` |

### REST i izlazne datoteke

Sve rute pod `https://vaša-trgovina.hr/wp-json/sidrena-cijena/v1/`:

| Poziv | Opis |
| --- | --- |
| `GET /prices` | Aktualni cjenik u JSON-u, sa straničenjem |
| `GET /prices.csv` | Cjenik u CSV formatu |
| `GET /prices.xml` | Cjenik u XML formatu |
| `GET /publications` | Popis objavljenih cjenika (`location`, `format`, `page`, `per_page`) |
| `GET /status` | Status dodatka (zahtijeva prijavu i dozvolu) |

Javni feedovi i arhiva namjerno su dostupni bez prijave, uz ograničenje broja zahtjeva. Objavite samo podatke koje želite javno prikazati.

## WP-CLI naredbe

```bash
wp sidrena-cijena status [--format=table|json]
wp sidrena-cijena validate [--format=csv|xml|both] [--location=<id>] [--severity=ERROR|WARNING|INFO] [--format-output=table|json|csv]
wp sidrena-cijena generate [--format=csv|xml|both] [--location=<id>] [--force] [--dry-run]
wp sidrena-cijena history <product_id> [--variation=<id>] [--type=<type>] [--limit=<n>]
wp sidrena-cijena import <file> [--dry-run] [--confirm-overwrite]
wp sidrena-cijena cleanup [--dry-run]
```

Primjer: provjera nalaza prije objave i ručno generiranje svih cjenika.

```bash
wp sidrena-cijena validate
wp sidrena-cijena generate --force
```

## Pravne napomene

Dodatak implementira dokumentirane tehničke obveze, ali **ne zamjenjuje pravni savjet**. Trgovac je odgovoran za točnost unesenih cijena, datuma, identiteta proizvoda i klasifikacije posebnog oblika prodaje.

- Općeniti referentni datum je **10. rujna 2026.**
- Za stare kategorije (hrana, pića, kozmetika, sredstva za čišćenje, higijenski i kućanski proizvodi) koristi se **2. svibnja 2025.**
- Oba datuma podesiva su pod **Sidrena cijena → Postavke → Pravna pravila**, a svaka se promjena bilježi u revizijskom tragu.
- Dodatak ne pogađa kategorije: prije objave mapirajte svoje stvarne kategorije i provjerite podatke.

## Službeni propisi i izvori

Ovi službeni izvori relevantni su za funkcionalnosti dodatka. Provjerite važeće propise i njihovu primjenu na svoje poslovanje.

- [NN 101/2026-1212 — Odluka o isticanju dodatne cijene kao izravnoj mjeri kontrole cijena](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html)
- [NN 101/2026-1213 — Odluka o objavi cjenika proizvoda i usluga](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html)
- [Ministarstvo gospodarstva — pojašnjenja za primjenu pravila od 1. listopada 2026.](https://mingo.gov.hr/vijesti/pojasnjenja-za-primjenu-dodatne-cijene-i-objavu-cjenika-od-1-listopada/10440)
- [Direktiva (EU) 2019/2161 — Omnibus direktiva](https://eur-lex.europa.eu/eli/dir/2019/2161/oj)
- [Direktiva 2011/83/EU o pravima potrošača](https://eur-lex.europa.eu/eli/dir/2011/83/oj)

## Privatnost i zaštita podataka

- Dodatak ne šalje podatke kataloga niti telemetriju u vanjske servise; cijene, provjere i generiranje datoteka ostaju na vašoj WordPress instalaciji.
- Ne dodaje kolačiće za praćenje niti prikuplja podatke o kupcima.
- Objavljena polja cjenika, adrese prodajnih mjesta i generirane datoteke javni su po dizajnu, uključujući REST feed i zadržanu arhivu — ograničite katalog na podatke namijenjene objavi.
- Zapisi o cijenama i dokazima ostaju u bazi i uploads direktoriju; opcionalno čišćenje pri deinstalaciji uklanja operativne podatke, ali čuva dokaze o sidrenoj cijeni i 30-dnevnoj povijesti.

## Repozitorij i izdanja

Svaka verzija dostupna je kao instalabilni ZIP s kontrolnom sumom:

```
versions/
  2.0.13/
    sidrena-cijena-eu.zip
    SHA256SUMS.txt
  2.0.12/
    ...
```

Provjera preuzetog paketa:

```bash
sha256sum -c SHA256SUMS.txt
```

Izdanja su objavljena i na [stranici GitHub izdanja](https://github.com/administrakt0r/sidrena-cijena-eu/releases). Potpuni popis izmjena po verzijama nalazi se u `readme.txt` unutar ZIP-a i u administraciji dodatka.

### Najnovije izdanje — 2.0.13

- Bolje administracijske forme: zauzeto stanje gumba kod duljih akcija (pokretanje provjere, trenutno generiranje, skupno postavljanje sidrenih cijena) sprječava dvostruke predaje.
- Jedinstveni hrvatski prikaz datuma i vremena na svim administracijskim zaslonima, u dokumentaciji i u pregledu CSV uvoza.
- Praktičniji unosi: savjeti i primjeri formata za cijene (`inputmode="decimal"`) te dosljedno straničenje tablica (50 redaka).
- Kvaliteta: 456 unit testova (1623 provjere), čist PHPCS, PHPStan i ESLint te 100 % hrvatski prijevod (768 prevedenih natpisa).

Izdanja od 2.0.5 do 2.0.12 donose sigurnosni pregled postavki i pravila, tvrđe WooCommerce forme proizvoda, PHPStan na razini 7 bez pogrešaka te brojna poboljšanja stabilnosti i pristupačnosti.

## Česta pitanja

**Je li ovo pravni savjet?**
Ne. Dodatak je tehnička pomoć koja implementira dokumentirane obveze; za svoj slučaj konzultirajte pravnog ili računovodstvenog stručnjaka.

**Je li WooCommerce obavezan?**
Ne. Integracija s WooCommerceom uključuje se automatski kad je WooCommerce aktivan; inače se koristi samostalni katalog.

**Mijenja li dodatak moje postojeće cijene?**
Ne. Povijesne cijene se ne izmišljaju: nedostajući podaci prijavljuju se kao nalaz, a automatski se bilježe samo stvarno viđene prve ponude.

**Gdje se uređuju referentni datumi?**
Pod **Sidrena cijena → Postavke → Pravna pravila**, uz zapis svake promjene u revizijskom tragu.

**Gdje mogu prijaviti problem?**
Preko [GitHub Issues](https://github.com/administrakt0r/sidrena-cijena-eu/issues) ili na [sidrenacijena.eu](https://sidrenacijena.eu/).

## Licenca

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
