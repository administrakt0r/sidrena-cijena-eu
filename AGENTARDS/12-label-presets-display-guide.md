# Prompt 12 — Label presets, display styles and storefront customisation guide

```
You are configuring and extending the display layer of the Sidrena Cijena EU plugin
(titanworks-bazna-cijena.php is the bootstrap). This prompt covers the full customisation surface
for how the anchor price appears on product cards and single-product pages.

BEFORE YOU START — read AGENTARDS/README.md and docs/legal-rules.md.

MANDATORY BRANCH & PULL REQUEST WORKFLOW:
If this task requires code, styling, or translation changes:
1. Checkout a dedicated branch:
   `git checkout -b feat/storefront-display-customization`
2. Commit changes atomically (`feat:`, `fix:`, `ux:`).
3. Push branch and open a Pull Request for user review:
   `git push -u origin feat/storefront-display-customization`
   `gh pr create --base master --title "feat: storefront label presets and display styling" --body "..."`
   Do NOT push directly to master or merge the PR yourself.

## What is configurable (as of v2.0.20)

### Anchor label presets (Display Settings → Anchor Label)

The plugin ships 7 presets. All are legally permitted per the Ministry of Economy
clarification (22 Sep 2026). Do not invent new label text without a legal basis.

| Preset key      | Display label (Croatian)              | Notes                                   |
|-----------------|---------------------------------------|-----------------------------------------|
| sidrena         | Sidrena cijena                        | Default, matches the law's own term     |
| bazna           | Bazna cijena                          | "Base price" — explicitly permitted     |
| referentna      | Referentna cijena                     | "Reference price"                       |
| dodatna         | Dodatna cijena                        | "Additional price"                      |
| cijena_na_dan   | Cijena na dan {datum}                 | Date is auto-substituted                |
| cijena_na       | Cijena na {datum}                     | Shorter form                            |
| najniza_redovna | Najniža redovna cijena ({datum})      | "Lowest regular price on [date]"        |
| custom          | (merchant enters free text)           | No token substitution unless {datum} included |

Dynamic date tokens — {datum}, {date}, {datum_iso} — are resolved by
PriceDisplay::format_anchor_label() per product's category:
- Legacy categories (food, beverages, cosmetics, cleaning, toiletries, household):
  reference date = 2025-05-02 → displayed as "2. 5. 2025."
- General categories (all other B2C retailers/services):
  reference date = 2026-09-10 → displayed as "10. 9. 2026."

### Display styles (Style Settings → Display Style)

14 built-in styles defined in src/Support/DisplayStyles.php:
tooltip, plain, badge, card, underline, outline, stripe, soft, mono, pill, minimal, tag, accent, subtle.
Scale: 40–160%, default 40%.

### Tooltip text (Display Settings → Tooltip Text)

Default (and legally required wording):
"Sidrena (bazna) cijena je redovna maloprodajna cijena na propisani referentni datum
prema mjerama izravne kontrole cijena."

Do NOT change this to anything resembling the 30-day lowest-price rule — that is a
different legal concept. The Settings::all() migration auto-corrects any old incorrect
tooltip text that described a "30-day" period.

### Anchor date display mode

Options: tooltip (default), inline, subtext, hidden.
- tooltip: date shown in the ? hover tooltip.
- inline: date appended to the label text.
- subtext: date on a separate line below.
- hidden: price shown but no date.

### Card visibility (product cards/archive)

By default anchors are hidden on product cards; each product can opt in via the native
Sidrena product tab in the WooCommerce product editor (CardVisibility meta).

## Common customisation tasks

### Task: Change the label to "Bazna cijena" for all products

In Display Settings (wp-admin/?page=sidrena-cijena-display):
1. Click the "Bazna cijena" pill.
2. The label field updates automatically.
3. Click Save.
4. No code change needed; all tests pass unchanged.

### Task: Show "Cijena na 2. 5. 2025." for legacy products, "Cijena na 10. 9. 2026." for general

Use the "Cijena na {datum}" preset. The token resolves per product category automatically.
Verify with PriceDisplayTest::test_format_anchor_label_resolves_datum_token (or similar).

### Task: Add a new custom label (e.g. "Redovna cijena na dan {datum}")

1. Checkout branch: `git checkout -b feat/custom-label-preset`
2. In src/Support/Settings.php → anchor_label_presets(), add new entry:
   'redovna_na_dan' => __( 'Redovna cijena na dan {datum}', 'titanworks-bazna-cijena' ),
3. Add Croatian translation to tools/i18n/hr_HR.php.
4. Run: `php tools/i18n/make-pot.php --locale=hr_HR` (must report 0 untranslated).
5. Run: `composer test:unit` (must pass).
6. Update DisplaySettingsPage to render the new pill.
7. Push branch and open PR via `gh pr create --base master`.

### Task: Debug why anchor price is not showing on a product card

Checklist:
1. CardVisibility: check _sidrena_card_visibility meta on the product. Must be 'show'.
2. Settings: check product_display_style. If 'tooltip' but no ? appears, check frontend.js.
3. Block theme: if shop uses Product Collection block, verify PriceDisplay::filter_price_block
   accepts 3 args and reads $block_instance->context['postId'].
4. Check display_location setting.
5. Run `composer test:unit -- --filter PriceDisplayTest`.

## Quality gates

After any code change to the display layer:
```bash
composer test:unit -- --filter "PriceDisplay|Settings|DisplayStyles|CardVisibility|LabelPreset"
php tools/i18n/make-pot.php --locale=hr_HR   # Must report 0 untranslated
npm run lint:js                               # For frontend.js changes
```

Push branch and open Pull Request:
```bash
git push -u origin <branch-name>
gh pr create --base master --title "<type>: <description>" --body "<details>"
```

Done when: the customisation is applied, tests pass, translations are complete (809/0),
branch is pushed, and a Pull Request is opened for user review.
```
