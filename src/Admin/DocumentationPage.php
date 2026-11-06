<?php
/**
 * Administrator documentation and reference guide.
 *
 * @package SidrenaCijena\Admin
 */

namespace SidrenaCijena\Admin;

use SidrenaCijena\Compliance\RuleConfig;
use SidrenaCijena\Plugin;
use SidrenaCijena\Support\Dates;

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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'titanworks-bazna-cijena' ) );
		}

		$rule = RuleConfig::all();

		$general_date = isset( $rule['general_reference_date'] ) ? (string) $rule['general_reference_date'] : '2026-09-10';
		$legacy_date  = isset( $rule['legacy_reference_date'] ) ? (string) $rule['legacy_reference_date'] : '2025-05-02';

		echo '<div class="wrap sidrena-docs-wrap">';
		echo '<h1>' . esc_html__( 'Sidrena cijena: Dokumentacija i korisnički vodič', 'titanworks-bazna-cijena' ) . '</h1>';
		echo '<p class="description sidrena-docs-description">' . esc_html__( 'Cjelovit priručnik za rad s dodatkom, zakonska pravila, automatizaciju, skupno postavljanje cijena, WP-CLI naredbe i razvojne kuke.', 'titanworks-bazna-cijena' ) . '</p>';

		// Quick navigation cards.
		echo '<div class="sidrena-docs-nav">';
		echo '<a href="#zakonski-okvir" class="button">' . esc_html__( '1. Zakonski okvir i pravila', 'titanworks-bazna-cijena' ) . '</a>';
		echo '<a href="#skupno-postavljanje" class="button">' . esc_html__( '2. Skupno postavljanje cijena', 'titanworks-bazna-cijena' ) . '</a>';
		echo '<a href="#prikaz-trgovina" class="button">' . esc_html__( '3. Prikaz na trgovini', 'titanworks-bazna-cijena' ) . '</a>';
		echo '<a href="#dnevni-cjenik" class="button">' . esc_html__( '4. Dnevni cjenik i javni feedovi', 'titanworks-bazna-cijena' ) . '</a>';
		echo '<a href="#cron-automatizacija" class="button">' . esc_html__( '5. Cron i automatizacija (08:00)', 'titanworks-bazna-cijena' ) . '</a>';
		echo '<a href="#wp-cli" class="button">' . esc_html__( '6. WP-CLI naredbe', 'titanworks-bazna-cijena' ) . '</a>';
		echo '<a href="#razvojne-kuke" class="button">' . esc_html__( '7. Kuke za programere (Hooks)', 'titanworks-bazna-cijena' ) . '</a>';
		echo '</div>';

		// Section 1: Zakonski okvir.
		echo '<div class="card sidrena-docs-card" id="zakonski-okvir">';
		echo '<h2>' . esc_html__( '1. Zakonski okvir i propisani referentni datumi', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Zakon nalaže trgovcima prikaz cijene koja je vrijedila na točno propisane referentne datume, kao i najniže cijene u zadnjih 30 dana prilikom provođenja posebnih oblika prodaje.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<table class="widefat striped sidrena-docs-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Referentni datumi po kategorijama', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Kategorija / Vrsta', 'titanworks-bazna-cijena' ) . '</th><th scope="col">' . esc_html__( 'Referentni datum', 'titanworks-bazna-cijena' ) . '</th><th scope="col">' . esc_html__( 'Opis i primjena', 'titanworks-bazna-cijena' ) . '</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td><strong>' . esc_html__( 'Opći proizvodi i usluge', 'titanworks-bazna-cijena' ) . '</strong></td><td><code>' . esc_html( Dates::format_date_display( $general_date ) ) . '</code></td><td>' . esc_html__( 'Standardni referentni datum za sve kategorije koje ne pripadaju posebnim/osjetljivim skupinama.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Posebne (naslijeđene) kategorije', 'titanworks-bazna-cijena' ) . '</strong></td><td><code>' . esc_html( Dates::format_date_display( $legacy_date ) ) . '</code></td><td>' . esc_html__( 'Hrana, piće, kozmetika, higijenske potrepštine, sredstva za čišćenje i kućanstvo. Slugeve ovih kategorija obavezno mapirajte u Postavke → Pravna pravila.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Novi proizvodi (prva ponuda)', 'titanworks-bazna-cijena' ) . '</strong></td><td><em>' . esc_html__( 'Datum objave', 'titanworks-bazna-cijena' ) . '</em></td><td>' . esc_html__( 'Proizvodi koji se prvi put objave dok je dodatak aktivan automatski bilježe cijenu prve ponude i taj datum kao svoju sidrenu cijenu.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<p><strong>' . esc_html__( 'Važna pravna napomena o nepromjenjivosti dokaza:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Zapisi sidrenih cijena i povijest promjena cijena čine zakonski dokaz. Svaka izmjena evidentira se kao ispravak s obaveznim obrazloženjem i korisničkim tragom (audit trail). Izvorne vrijednosti se nikada ne brišu tiho.', 'titanworks-bazna-cijena' ) . '</p>';
		echo '</div>';

		// Section 2: Skupno postavljanje.
		echo '<div class="card sidrena-docs-card" id="skupno-postavljanje">';
		echo '<h2>' . esc_html__( '2. Skupno postavljanje sidrenih cijena (Bulk unos i alati)', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Kako ne biste morali ručno unositi sidrenu cijenu za svaki proizvod pojedinačno, dostupni su brzi skupni alati na stranici Sidrena cijena → Sidrene cijene:', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><strong>' . esc_html__( 'Postavi za sve proizvode odjednom:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Na vrhu stranice nalazi se kartica koja vam omogućuje da jednim klikom postavite sidrenu cijenu prema trenutnoj važećoj cijeni u katalogu za sve proizvode i varijacije. Možete odabrati primjenu samo na proizvode kojima sidrena cijena nedostaje ili na cijeli asortiman.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Brzi odabir na stranici:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Korištenjem gumba „Odaberi sve na ovoj stranici” i „Poništi odabir”, ili klikom na potvrdni okvir u zaglavlju tablice, možete u sekundi označiti sve prikazane proizvode i spremiti predložene vrijednosti.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Uvoz putem CSV datoteke:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Na stranici Uvoz možete učitati CSV datoteku s kolonama sku/product_id, reference_date, anchor_price i variation_id. Dostupan je probni prolaz (dry-run) za sigurnu provjeru prije trajnog upisa.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '</ul>';
		echo '<p><a class="button button-primary" href="' . esc_url( AdminMenu::url( BulkAnchorEntryPage::SLUG ) ) . '">' . esc_html__( 'Otvori unos sidrenih cijena', 'titanworks-bazna-cijena' ) . '</a></p>';
		echo '</div>';

		// Section 3: Prikaz na trgovini.
		echo '<div class="card sidrena-docs-card" id="prikaz-trgovina">';
		echo '<h2>' . esc_html__( '3. Prikaz sidrene i najniže 30-dnevne cijene na trgovini', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Dodatak automatski prikazuje propisane zakonske cijene uz redovnu cijenu proizvoda na pojedinačnim stranicama i u katalozima trgovine:', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><strong>' . esc_html__( 'Sidrena cijena:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Prikazuje se tekstom „Cijena na dan DD.MM.GGGG: X,XX €” (npr. 10.09.2026. ili 02.05.2025. ovisno o kategoriji). Mjesto prikaza možete podesiti u Postavke → Prikaz (ispod cijene, u istom retku ili zasebnom bloku).', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Stilovi i interaktivni tooltip:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Dostupni su unaprijed definirani stilovi: interaktivni tooltip s ikonom (?), minimalistički, bedž, kartica, obrub, meka pozadina, monokromatski, pilula, oznaka, naglašena traka i suptilni prikaz. U postavkama možete odabrati i način prikaza datuma (tooltip, u retku, podtekst ili skriveno) te debljinu fonta.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Najniža cijena u zadnjih 30 dana:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Prikazuje se automatski kada je proizvod na akciji ili sniženju (poseban oblik prodaje). Prozor se računa točno od 30 dana prije početka akcije do dana prije početka (dan početka je zakonski isključen).', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Kompatibilnost tema i graditelja stranica:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Podržane su klasične teme, moderne blok teme (Full Site Editing - FSE) te popularni page builderi (Elementor, Divi, Beaver Builder).', 'titanworks-bazna-cijena' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Section 4: Dnevni cjenik.
		echo '<div class="card sidrena-docs-card" id="dnevni-cjenik">';
		echo '<h2>' . esc_html__( '4. Dnevni cjenik, format naziva i javni feedovi', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Zakonski propis nalaže svakodnevno generiranje cjenika za svako prodajno mjesto u propisanom formatu:', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<div class="sidrena-docs-callout">';
		echo '<strong>' . esc_html__( 'Struktura naziva datoteke:', 'titanworks-bazna-cijena' ) . '</strong><br />';
		echo '<code>{OBLIK_OBJEKTA}_{ADRESA}_{OZNAKA_OBJEKTA}_{REDNI_BROJ}_{DDMMGGGG}_{HHMM}.{csv|xml}</code><br />';
		echo '<small>' . esc_html__( 'Primjer: PRODAVAONICA_ILICA-1_POSL-01_1_26092026_0800.csv', 'titanworks-bazna-cijena' ) . '</small>';
		echo '</div>';
		echo '<p><strong>' . esc_html__( 'Javni URL-ovi dostupni kupcima i inspekciji:', 'titanworks-bazna-cijena' ) . '</strong></p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><code>' . esc_html( home_url( '/sidrena-cijena.csv' ) ) . '</code> — ' . esc_html__( 'Javni CSV feed važećeg cjenika.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><code>' . esc_html( home_url( '/sidrena-cijena.xml' ) ) . '</code> — ' . esc_html__( 'Javni XML feed važećeg cjenika.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><code>' . esc_html( home_url( '/sidrena-cjenik/' ) ) . '</code> — ' . esc_html__( 'Pregledna HTML stranica s cjenikom artikala.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><code>' . esc_html( home_url( '/arhiva-sidrene-cijene/' ) ) . '</code> — ' . esc_html__( 'Javna arhiva svih generiranih cjenika (čuva se minimalno 30 dana).', 'titanworks-bazna-cijena' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Section 5: Cron i automatizacija.
		echo '<div class="card sidrena-docs-card" id="cron-automatizacija">';
		echo '<h2>' . esc_html__( '5. Cron poslovi, rok u 08:00 i automatsko nadoknađivanje', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Cjenici se moraju objaviti prije 08:00 sati svakog dana:', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<ul class="sidrena-docs-list">';
		echo '<li><strong>' . esc_html__( 'Dnevni cron posao:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Kuka sidrena_cijena_daily_event planira se u vremenskoj zoni Europe/Zagreb tako da generira i objavi cjenik prije 08:00 sati ujutro.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Automatsko nadoknađivanje (Catch-up):', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Ako WordPress cron nije pokrenut na vrijeme (npr. zbog izostanka posjeta na poslužitelju), dodatak detektira propušteni rok i automatski pokreće hitno nadoknadno generiranje, a u administraciji prikazuje vidljivo upozorenje.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Trenutna regeneracija za usluge:', 'titanworks-bazna-cijena' ) . '</strong> ' . esc_html__( 'Kada se promijeni cijena usluge (_sidrena_item_type=service), cjenik se prema zakonu mora ažurirati bez odgode, stoga dodatak automatski pokreće asinkrono regeneriranje.', 'titanworks-bazna-cijena' ) . '</li>';
		echo '</ul>';
		echo '</div>';

		// Section 6: WP-CLI.
		echo '<div class="card sidrena-docs-card" id="wp-cli">';
		echo '<h2>' . esc_html__( '6. WP-CLI naredbe za administratore', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Za napredno upravljanje poslužiteljem i automatizaciju kroz sistemski crontab dostupne su sljedeće naredbe:', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<table class="widefat striped sidrena-docs-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'WP-CLI naredbe', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Naredba', 'titanworks-bazna-cijena' ) . '</th><th scope="col">' . esc_html__( 'Opis djelovanja', 'titanworks-bazna-cijena' ) . '</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td><code>wp sidrena-cijena generate [--force]</code></td><td>' . esc_html__( 'Generira i objavljuje novi dnevni cjenik za sva aktivna prodajna mjesta.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena check [--export=&lt;putanja&gt;]</code></td><td>' . esc_html__( 'Pokreće provjeru usklađenosti i ispisuje pronađene GREŠKE i UPOZORENJA.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena status</code></td><td>' . esc_html__( 'Ispisuje status dodatka: broj artikala, sidrenih cijena, lokacija i zadnju objavu.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena anchors set &lt;id&gt; &lt;cijena&gt;</code></td><td>' . esc_html__( 'Postavlja sidrenu cijenu za određeni proizvod s opcionalnim parametrima --date i --reason.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>wp sidrena-cijena seed-test-data --scenario=sale</code></td><td>' . esc_html__( 'Stvara testne podatke povijesti cijena za provjeru izračuna najniže 30-dnevne cijene.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '</div>';

		// Section 7: Hooks.
		echo '<div class="card sidrena-docs-card" id="razvojne-kuke">';
		echo '<h2>' . esc_html__( '7. Kuke za programere (Filters & Actions)', 'titanworks-bazna-cijena' ) . '</h2>';
		echo '<p>' . esc_html__( 'Programeri mogu prilagoditi ponašanje dodatka kroz standardne WordPress filtre i akcije:', 'titanworks-bazna-cijena' ) . '</p>';
		echo '<table class="widefat striped sidrena-docs-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Kuke za programere', 'titanworks-bazna-cijena' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Kuka', 'titanworks-bazna-cijena' ) . '</th><th scope="col">' . esc_html__( 'Tip', 'titanworks-bazna-cijena' ) . '</th><th scope="col">' . esc_html__( 'Opis primjene', 'titanworks-bazna-cijena' ) . '</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td><code>sidrena_cijena_display_anchor</code></td><td>' . esc_html__( 'Filter', 'titanworks-bazna-cijena' ) . '</td><td>' . esc_html__( 'Omogućuje programsku kontrolu treba li prikazati sidrenu cijenu na određenom proizvodu ili stranici.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_thirty_day_label</code></td><td>' . esc_html__( 'Filter', 'titanworks-bazna-cijena' ) . '</td><td>' . esc_html__( 'Prilagođava tekst oznake najniže 30-dnevne cijene po artiklu i kontekstu prikaza.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_footer_link_text</code></td><td>' . esc_html__( 'Filter', 'titanworks-bazna-cijena' ) . '</td><td>' . esc_html__( 'Prilagođava tekst poveznice na arhivu cjenika u podnožju stranice.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_anchor_recorded</code></td><td>' . esc_html__( 'Action', 'titanworks-bazna-cijena' ) . '</td><td>' . esc_html__( 'Aktivira se nakon uspješnog evidentiranja nove sidrene cijene.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_publication_published</code></td><td>' . esc_html__( 'Action', 'titanworks-bazna-cijena' ) . '</td><td>' . esc_html__( 'Aktivira se nakon što se generira i objavi novi cjenik na poslužitelju.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_special_sale_changed</code></td><td>' . esc_html__( 'Action', 'titanworks-bazna-cijena' ) . '</td><td>' . esc_html__( 'Aktivira se prilikom izmjene ili ponovnog izračuna posebnog oblika prodaje.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '<tr><td><code>sidrena_cijena_enable_standalone_catalog</code></td><td>' . esc_html__( 'Filter', 'titanworks-bazna-cijena' ) . '</td><td>' . esc_html__( 'Dopušta registraciju samostalnog kataloga (CPT) čak i ako je WooCommerce aktivan.', 'titanworks-bazna-cijena' ) . '</td></tr>';
		echo '</tbody></table>';
		echo '</div>';

		echo '</div>';
	}
}
