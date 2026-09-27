<?php
/**
 * Administrator documentation and reference guide.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the in-dashboard Croatian documentation and operational reference.
 */
class DocumentationPage {

	const SLUG = 'sidrena-cijena-docs';

	/**
	 * Render the documentation screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sidrena-cijena-eu' ) );
		}

		$rule = RuleConfig::all();

		$general_date = isset( $rule['general_reference_date'] ) ? (string) $rule['general_reference_date'] : '2026-09-10';
		$legacy_date  = isset( $rule['legacy_reference_date'] ) ? (string) $rule['legacy_reference_date'] : '2025-05-02';

		echo '<div class="wrap sidrena-docs-wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Dokumentacija i korisnički vodič', 'sidrena-cijena-eu' ) . '</h1>';
		echo '<p class="description sidrena-docs-description">' . esc_html__( 'Cjelovit priručnik za rad s dodatkom, zakonska pravila, automatizaciju, skupno postavljanje cijena, WP-CLI naredbe i razvojne kuke.', 'sidrena-cijena-eu' ) . '</p>';

		// Quick navigation cards.
		echo '<div class="sidrena-docs-nav">';
		echo '<a href="#zakonski-okvir" class="button">' . esc_html__( '1. Zakonski okvir i pravila', 'sidrena-cijena-eu' ) . '</a>';
		echo '<a href="#skupno-postavljanje" class="button">' . esc_html__( '2. Skupno postavljanje cijena', 'sidrena-cijena-eu' ) . '</a>';
		echo '<a href="#prikaz-trgovina" class="button">' . esc_html__( '3. Prikaz na trgovini', 'sidrena-cijena-eu' ) . '</a>';
		echo '<a href="#dnevni-cjenik" class="button">' . esc_html__( '4. Dnevni cjenik i javni feedovi', 'sidrena-cijena-eu' ) . '</a>';
		echo '<a href="#cron-automatizacija" class="button">' . esc_html__( '5. Cron i automatizacija (08:00)', 'sidrena-cijena-eu' ) . '</a>';
		echo '<a href="#wp-cli" class="button">' . esc_html__( '6. WP-CLI naredbe', 'sidrena-cijena-eu' ) . '</a>';
		echo '<a href="#razvojne-kuke" class="button">' . esc_html__( '7. Kuke za programere (Hooks)', 'sidrena-cijena-eu' ) . '</a>';
		echo '</div>';

		// Section 1: Zakonski okvir.
		echo '<div class="card sidrena-docs-card" id="zakonski-okvir">';
		echo '<h2>' . esc_html__( '1. Zakonski okvir i propisani referentni datumi', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Zakon nalaže trgovcima prikaz cijene koja je vrijedila na točno propisane referentne datume, kao i najniže cijene u zadnjih 30 dana prilikom provođenja posebnih oblika prodaje.', 'sidrena-cijena-eu' ) . '</p>';
		echo '<table class="widefat striped sidrena-docs-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Referentni datumi po kategorijama', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Kategorija / Vrsta', 'sidrena-cijena-eu' ) . '</th><th scope="col">' . esc_html__( 'Referentni datum', 'sidrena-cijena-eu' ) . '</th><th scope="col">' . esc_html__( 'Opis i primjena', 'sidrena-cijena-eu' ) . '</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td><strong>' . esc_html__( 'Opći proizvodi i usluge', 'sidrena-cijena-eu' ) . '</strong></td><td><code>' . esc_html( $general_date ) . '</code></td><td>' . esc_html__( 'Standardni referentni datum za sve kategorije koje ne pripadaju posebnim/osjetljivim skupinama.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Posebne (naslijeđene) kategorije', 'sidrena-cijena-eu' ) . '</strong></td><td><code>' . esc_html( $legacy_date ) . '</code></td><td>' . esc_html__( 'Hrana, piće, kozmetika, higijenske potrepštine, sredstva za čišćenje i kućanstvo. Slugeve ovih kategorija obavezno mapirajte u Postavke → Pravna pravila.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Novi proizvodi (prva ponuda)', 'sidrena-cijena-eu' ) . '</strong></td><td><em>' . esc_html__( 'Datum objave', 'sidrena-cijena-eu' ) . '</em></td><td>' . esc_html__( 'Proizvodi koji se prvi put objave dok je dodatak aktivan automatski bilježe cijenu prve ponude i taj datum kao svoju sidrenu cijenu.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html__( 'Važna pravna napomena o nepromjenjivosti dokaza:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Zapisi sidrenih cijena i povijest promjena cijena čine zakonski dokaz. Svaka izmjena evidentira se kao ispravak s obaveznim obrazloženjem i korisničkim tragom (audit trail). Izvorne vrijednosti se nikada ne brišu tiho.', 'sidrena-cijena-eu' ) . '</p>';
		echo '</div>';

		// Section 2: Skupno postavljanje.
		echo '<div class="card sidrena-docs-card" id="skupno-postavljanje">';
		echo '<h2>' . esc_html__( '2. Skupno postavljanje sidrenih cijena (Bulk unos i alati)', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Kako ne biste morali ručno unositi sidrenu cijenu za svaki proizvod pojedinačno, dostupni su brzi skupni alati na stranici Sidrena cijena → Sidrene cijene:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><strong>' . esc_html__( 'Postavi za sve proizvode odjednom:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Na vrhu stranice nalazi se kartica koja vam omogućuje da jednim klikom postavite sidrenu cijenu prema trenutnoj važećoj cijeni u katalogu za sve proizvode i varijacije. Možete odabrati primjenu samo na proizvode kojima sidrena cijena nedostaje ili na cijeli asortiman.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Brzi odabir na stranici:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Korištenjem gumba „Odaberi sve na ovoj stranici” i „Poništi odabir”, ili klikom na potvrdni okvir u zaglavlju tablice, možete u sekundi označiti sve prikazane proizvode i spremiti predložene vrijednosti.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Uvoz putem CSV datoteke:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Na stranici Uvoz možete učitati CSV datoteku s kolonama sku/product_id, reference_date, anchor_price i variation_id. Dostupan je probni prolaz (dry-run) za sigurnu provjeru prije trajnog upisa.', 'sidrena-cijena-eu' ) . '</li>';
		echo '</ul>';
		echo '<p><a class="button button-primary" href="' . esc_url( AdminMenu::url( BulkAnchorEntryPage::SLUG ) ) . '">' . esc_html__( 'Otvori unos sidrenih cijena', 'sidrena-cijena-eu' ) . '</a></p>';
		echo '</div>';

		// Section 3: Prikaz na trgovini.
		echo '<div class="card sidrena-docs-card" id="prikaz-trgovina">';
		echo '<h2>' . esc_html__( '3. Prikaz sidrene i najniže 30-dnevne cijene na trgovini', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Dodatak automatski prikazuje propisane zakonske cijene uz redovnu cijenu proizvoda na pojedinačnim stranicama i u katalozima trgovine:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><strong>' . esc_html__( 'Sidrena cijena:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Prikazuje se tekstom „Cijena na dan DD.MM.GGGG: X,XX €” (npr. 10.09.2026. ili 02.05.2025. ovisno o kategoriji). Mjesto prikaza možete podesiti u Postavke → Prikaz (ispod cijene, u istom retku ili zasebnom bloku).', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Najniža cijena u zadnjih 30 dana:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Prikazuje se automatski kada je proizvod na akciji ili sniženju (poseban oblik prodaje). Prozor se računa točno od 30 dana prije početka akcije do dana prije početka (dan početka je zakonski isključen).', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Kompatibilnost tema i graditelja stranica:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Podržane su klasične teme, moderne blok teme (Full Site Editing - FSE) te popularni page builderi (Elementor, Divi, Beaver Builder).', 'sidrena-cijena-eu' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Section 4: Dnevni cjenik.
		echo '<div class="card sidrena-docs-card" id="dnevni-cjenik">';
		echo '<h2>' . esc_html__( '4. Dnevni cjenik, format naziva i javni feedovi', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Zakonski propis nalaže svakodnevno generiranje cjenika za svako prodajno mjesto u propisanom formatu:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<div class="sidrena-docs-callout">';
		echo '<strong>' . esc_html__( 'Struktura naziva datoteke:', 'sidrena-cijena-eu' ) . '</strong><br />';
		echo '<code>{OBLIK_OBJEKTA}_{ADRESA}_{OZNAKA_OBJEKTA}_{REDNI_BROJ}_{DDMMGGGG}_{HHMM}.{csv|xml}</code><br />';
		echo '<small>' . esc_html__( 'Primjer: PRODAVAONICA_ILICA-1_POSL-01_1_26092026_0800.csv', 'sidrena-cijena-eu' ) . '</small>';
		echo '</div>';
		echo '<p><strong>' . esc_html__( 'Javni URL-ovi dostupni kupcima i inspekciji:', 'sidrena-cijena-eu' ) . '</strong></p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><code>' . esc_html( home_url( '/sidrena-cijena.csv' ) ) . '</code> — ' . esc_html__( 'Javni CSV feed važećeg cjenika.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><code>' . esc_html( home_url( '/sidrena-cijena.xml' ) ) . '</code> — ' . esc_html__( 'Javni XML feed važećeg cjenika.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><code>' . esc_html( home_url( '/sidrena-cjenik/' ) ) . '</code> — ' . esc_html__( 'Pregledna HTML stranica s cjenikom artikala.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><code>' . esc_html( home_url( '/arhiva-sidrene-cijene/' ) ) . '</code> — ' . esc_html__( 'Javna arhiva svih generiranih cjenika (čuva se minimalno 30 dana).', 'sidrena-cijena-eu' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Section 5: Cron i automatizacija.
		echo '<div class="card sidrena-docs-card" id="cron-automatizacija">';
		echo '<h2>' . esc_html__( '5. Cron poslovi, rok u 08:00 i automatsko nadoknađivanje', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Cjenici se moraju objaviti prije 08:00 sati svakog dana:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><strong>' . esc_html__( 'Dnevni cron posao:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Kuka sidrena_cijena_daily_event planira se u vremenskoj zoni Europe/Zagreb tako da generira i objavi cjenik prije 08:00 sati ujutro.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Automatsko nadoknađivanje (Catch-up):', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Ako WordPress cron nije pokrenut na vrijeme (npr. zbog izostanka posjeta na poslužitelju), dodatak detektira propušteni rok i automatski pokreće hitno nadoknadno generiranje, a u administraciji prikazuje vidljivo upozorenje.', 'sidrena-cijena-eu' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Trenutna regeneracija za usluge:', 'sidrena-cijena-eu' ) . '</strong> ' . esc_html__( 'Kada se promijeni cijena usluge (_sidrena_item_type=service), cjenik se prema zakonu mora ažurirati bez odgode, stoga dodatak automatski pokreće asinkrono regeneriranje.', 'sidrena-cijena-eu' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Section 6: WP-CLI.
		echo '<div class="card sidrena-docs-card" id="wp-cli">';
		echo '<h2>' . esc_html__( '6. WP-CLI naredbe za administratore', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Za napredno upravljanje poslužiteljem i automatizaciju kroz sistemski crontab dostupne su sljedeće naredbe:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<table class="widefat striped sidrena-docs-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'WP-CLI naredbe', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Naredba', 'sidrena-cijena-eu' ) . '</th><th scope="col">' . esc_html__( 'Opis djelovanja', 'sidrena-cijena-eu' ) . '</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td><code>wp sidrena-cijena generate [--force]</code></td><td>' . esc_html__( 'Generira i objavljuje novi dnevni cjenik za sva aktivna prodajna mjesta.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena check [--export=&lt;putanja&gt;]</code></td><td>' . esc_html__( 'Pokreće provjeru usklađenosti i ispisuje pronađene GREŠKE i UPOZORENJA.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena status</code></td><td>' . esc_html__( 'Ispisuje status dodatka: broj artikala, sidrenih cijena, lokacija i zadnju objavu.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena anchors set &lt;id&gt; &lt;cijena&gt;</code></td><td>' . esc_html__( 'Postavlja sidrenu cijenu za određeni proizvod s opcionalnim parametrima --date i --reason.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena seed-test-data --scenario=sale</code></td><td>' . esc_html__( 'Stvara testne podatke povijesti cijena za provjeru izračuna najniže 30-dnevne cijene.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '</div>';

		// Section 7: Hooks.
		echo '<div class="card sidrena-docs-card" id="razvojne-kuke">';
		echo '<h2>' . esc_html__( '7. Kuke za programere (Filters & Actions)', 'sidrena-cijena-eu' ) . '</h2>';
		echo '<p>' . esc_html__( 'Programeri mogu prilagoditi ponašanje dodatka kroz standardne WordPress filtre i akcije:', 'sidrena-cijena-eu' ) . '</p>';
		echo '<table class="widefat striped sidrena-docs-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Kuke za programere', 'sidrena-cijena-eu' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Kuka', 'sidrena-cijena-eu' ) . '</th><th scope="col">' . esc_html__( 'Tip', 'sidrena-cijena-eu' ) . '</th><th scope="col">' . esc_html__( 'Opis primjene', 'sidrena-cijena-eu' ) . '</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td><code>sidrena_cijena_display_anchor</code></td><td>' . esc_html__( 'Filter', 'sidrena-cijena-eu' ) . '</td><td>' . esc_html__( 'Omogućuje programsku kontrolu treba li prikazati sidrenu cijenu na određenom proizvodu ili stranici.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_anchor_recorded</code></td><td>' . esc_html__( 'Action', 'sidrena-cijena-eu' ) . '</td><td>' . esc_html__( 'Aktivira se nakon uspješnog evidentiranja nove sidrene cijene.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_publication_published</code></td><td>' . esc_html__( 'Action', 'sidrena-cijena-eu' ) . '</td><td>' . esc_html__( 'Aktivira se nakon što se generira i objavi novi cjenik na poslužitelju.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_special_sale_changed</code></td><td>' . esc_html__( 'Action', 'sidrena-cijena-eu' ) . '</td><td>' . esc_html__( 'Aktivira se prilikom izmjene ili ponovnog izračuna posebnog oblika prodaje.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_enable_standalone_catalog</code></td><td>' . esc_html__( 'Filter', 'sidrena-cijena-eu' ) . '</td><td>' . esc_html__( 'Dopušta registraciju samostalnog kataloga (CPT) čak i ako je WooCommerce aktivan.', 'sidrena-cijena-eu' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '</div>';

		echo '</div>';
	}
}
