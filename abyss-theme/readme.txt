=== Abyss ===

Contributors: abyssmedia
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

An affiliate publishing theme for AI/programming and personal-finance coverage.

== What is in the box ==

Templates
  front-page.php   Homepage: lead story + rate snapshot, lane cards, comparison
                   table, latest grid, product picks, newsletter.
  single.php       Article with dek, byline, read time, sticky "keep reading"
                   rail and newsletter card.
  archive.php      Category / tag / author archives in the 3-up card grid.
  index.php        Blog index fallback. search.php, page.php, 404.php also included.

Content types
  Rates & offers (abyss_offer)  Powers the sortable comparison table and the
                                homepage rate snapshot. Fields: headline rate,
                                rate unit, minimum, monthly fee, worth knowing,
                                affiliate URL, button label, snapshot toggle.
  Product picks (abyss_pick)    Powers "Tested this month". Fields: kicker,
                                price, merchant, affiliate URL, review post ID.
                                Uses the featured image and excerpt.

Posts also gain a "Standfirst / dek" field and an "article contains affiliate
links" checkbox, which prints the inline disclosure above the body copy.

== Setup ==

1. Appearance > Themes > Add New > Upload Theme, then activate.
2. Appearance > Menus: create menus and assign them to Primary (header),
   Footer column 1-3 and Legal.
3. Appearance > Customize > Abyss theme:
   - Brand & palette: pick one of four palettes (two dark, two light) and set
     the footer description. Upload a logo under Site Identity.
   - Live ticker: one item per line, "label | value | change | up or down".
   - Affiliate disclosure: minimal line, prominent bar, or off. It renders
     directly above the footer on every page.
   - Newsletter block: headline, copy, small print, and a shortcode field for
     Mailchimp / MailPoet / Kit. Leave the shortcode blank to use the built-in
     placeholder form.
   - Homepage sections: toggle the snapshot, lanes, comparison table and picks.
4. Reading > "Your homepage displays" > A static page, and pick any page as the
   homepage so front-page.php is used. (It also works with "Your latest posts".)
5. Mark one post as sticky to pin it as the hero lead story.

== Affiliate links ==

Every affiliate link the theme prints carries rel="sponsored nofollow noopener"
and target="_blank". Use abyss_affiliate_link( $url, $label ) in child themes to
keep that behaviour consistent.

== Filters ==

abyss_newsletter_action   Form action URL for the built-in signup form.

== Changelog ==

= 1.0.0 =
* Initial release.
