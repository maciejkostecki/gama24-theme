# Changelog

Notable changes to the **Storefront Child** theme for gama24.pl.

Entries begin **2026-09-06**; earlier work is recorded only in git history. Each
entry names its commit. The theme's `Version:` header is deliberately *not*
bumped per change — see [Known issues](#known-issues), because that has
consequences.

---

## 2026-09-07

### Removed the RSD/XML-RPC discovery tag — `354cef5`

Dropped `<link rel="EditURI" href=".../xmlrpc.php?rsd">` via
`remove_action( 'wp_head', 'rsd_link' )`.

This **de-advertises** XML-RPC; it does not disable it. `POST /xmlrpc.php` still
returns `200` by design — the `jetpack/v4` namespace registered by WooCommerce's
connection package uses XML-RPC for site connection, so disabling the endpoint
is a separate and riskier decision.

`wlwmanifest_link` needed no handling; it was dropped from core in WordPress 6.3.

*Files:* `functions.php`

### Closed author/user disclosure — `b669db3`

Before this, production leaked two accounts: `gama` via `/wp-json/wp/v2/users`,
and `wweb` via the oembed endpoint, with `/?author=2` issuing a `301` to
`/author/gama/` that confirmed the slug.

Note that **only two of the four vectors were REST** — disabling the API, which
was the original instinct, would not have closed `/?author=N` or the author
archives.

- `rest_endpoints` — hides `/wp/v2/users` **from logged-out requests only**. The
  condition is load-bearing: the block editor reads that route to populate its
  author control, so removing it outright breaks editing.
- `oembed_response_data` — strips `author_name` / `author_url`.
- `template_redirect` at **priority 5** — turns author queries into 404s. The
  priority matters: core's `redirect_canonical()` runs on the same hook at 10
  and would otherwise answer `/?author=N` with a redirect that discloses the
  slug without ever rendering a page. One `is_author()` check covers both the
  archive and the numeric probe.
- `author_link` → home URL. Storefront prints an author link in every post's
  meta (`storefront_post_meta()`), assembled inline with no filter of its own,
  so with archives 404ing those links would otherwise be dead ends.
- `remove_action` on `rest_output_link_wp_head` / `rest_output_link_header` —
  drops the API discovery tag and `Link:` header. Obscurity, not security; the
  endpoint sits at a well-known path regardless.

The REST API itself stays on, and must: Contact Form 7 posts submissions to
`contact-form-7/v1` with a public permission callback, the homepage's block
add-to-cart talks to `wc/store/v1`, and the editor and WooCommerce admin run on
`wp/v2` and `wc-admin`.

*Files:* `functions.php`, `header.php` (new)

### Added `header.php` override — `b669db3`

Byte-for-byte copy of `storefront/header.php` minus one line:
`<link rel="pingback">`. Storefront prints that tag unconditionally, so unlike
the `X-Pingback` header — removed with a `wp_headers` filter — it cannot be
taken out from `functions.php`.

> **Re-sync this file when Storefront updates.** A stale template override is
> how a parent-theme update quietly stops reaching the front end. The returning
> `rel="pingback"` tag is the tell; the verification script below catches it.

*Files:* `header.php`

---

## 2026-09-06

### Disabled comments and product reviews site-wide — `27a4971`

WordPress has no single switch for this, and WooCommerce reviews are comments
underneath, so both are closed in one place.

`comments_open` / `pings_open` are the load-bearing filters — core gates **every
write path** on them: the front-end form, direct POSTs to `wp-comments-post.php`
via `wp_handle_comment_submission()`, the REST controller's `create_item`, and
XML-RPC's `wp.newComment` and `pingback.ping`. No separate REST or XML-RPC
unhooking is needed for submissions.

Reads needed separate handling, because `comments_open` does not gate them:

- `rest_endpoints` — removes the `/wp/v2/comments` routes, which otherwise serve
  stored comments publicly.
- `template_redirect` — returns **410** for comment feeds, which build their own
  query and are reached by neither `comments_open` nor `comments_array`.
- `comments_array` — hides any stored row from front-end templates.

Reviews are disabled through `pre_option_woocommerce_enable_reviews` → `no`.
This is WooCommerce's own switch and the supported route: it is the condition
under which WooCommerce adds `comments` support to the `product` post type at
all (`class-wc-post-types.php`), so forcing it removes reviews at the source
rather than by stripping post-type support by hand.

**`shop_order` is deliberately excluded** from the comment-support removal:
WooCommerce order notes are stored as comments (`comment_type = 'order_note'`).
Verified after deploy that both private and customer notes still work.

Also: `pre_option_default_comment_status` / `default_ping_status` → `closed`,
Comments removed from the admin menu and toolbar, and the `X-Pingback` header
dropped.

*Files:* `functions.php`

### Product Collection block titles now match the archive loop — `878cf60`

Product names in Gutenberg-generated product lists rendered 23px, purple and
underlined, against 16px / 400 / `#333` / no underline in the archive loop.

Storefront styles loop titles for the classic `ul.products` loop and for the
*legacy* grid blocks (`.wc-block-grid__product-title`), but ships nothing for
the Product Collection block, which renders the name as
`h2.wp-block-post-title > a`. Unstyled, it fell back to generic page-content
styling.

The nesting is what makes the two diverge at all: the archive is `a > h2`, so
the heading colour wins for the title text; the block is `h2 > a`, so the link
colour does. `color: inherit` on the anchor hands the heading colour back and
keeps tracking the Customizer rather than hardcoding `#333`.

`!important` is load-bearing on `font-size` **and nowhere else** — WordPress
emits `.has-medium-font-size { font-size: var(--wp--preset--font-size--medium)
!important }` (23px here), which no selector can outweigh. The other
declarations win on specificity: the selector is (0,3,2) against Storefront's
(0,3,0).

Line-height and margins were left alone on purpose: the block prints those
inline from its own editor settings, so overriding them would need a second
`!important` and would quietly break those controls in the editor.

*Files:* `style.scss`, `style.css`, `style.css.map`

### Removed the footer credit block — `798858b`

`.site-footer .site-info` — the copyright line, the "Built with WooCommerce"
credit and the privacy policy link — is produced entirely by the parent's
`storefront_credit()`, hooked to `storefront_footer` at priority 20. Unhooking
it removes the markup outright rather than hiding it with CSS.

Hooked on `init`, not at file scope: the parent's `functions.php` loads *after*
the child's, so a top-level `remove_action()` would run before the action
exists.

*Files:* `functions.php`

---

## Known issues

Open by decision, not oversight.

**Asset cache-busting.** `style.css` and the theme's JS are versioned by the
child theme's `Version:` header — `1.0.0`, never bumped. Storefront enqueues the
stylesheet with `$child_theme->get( 'Version' )`
(`storefront/inc/class-storefront.php:418`), and production serves assets
`cache-control: public, max-age=604800`. A CSS or JS deploy is therefore
invisible to returning visitors for up to **7 days**, while `curl` shows the
server file is correct — easy to misdiagnose as a build or specificity problem.
The `Version:` header lives in `style.scss`, which compiles into `style.css`, so
bumping only the `.css` is undone by the next Sass build. Permanent fix would be
to filter the enqueued version to `filemtime()` for both the stylesheet and the
two JS files.

**Discussion state left in the database.** 26,478 rows still carry
`comment_status = 'open'` (mostly attachments and products), and the stock
sample comment row remains. All of it is inert behind the filters above and
unreachable through every public path, but the protection is theme-bound: swap
away from this child theme and it reopens. A dry-run-tested purge script exists
but has not been run.

**Settings screens are now inert.** WooCommerce → Settings → Products → "Enable
product reviews" and Settings → Discussion defaults read as off and cannot be
toggled while the `pre_option_*` filters are in place. This is intended — the
state lives in code — but it will confuse anyone looking at those screens.

**Blog bylines still print the author's display name.** Slugs are gone
everywhere; the rendered name is not. Removing it means overriding Storefront's
whole post-meta block.

**None of this stops brute-forcing.** Hiding usernames raises the cost of
finding a valid login; it does not protect the login itself. Rate-limiting and
2FA are the controls that would.

---

## Verifying a deploy

Anonymous checks, safe to re-run at any time — and worth running after any
Storefront update, since the `header.php` override can go stale:

```bash
B=https://gama24.pl
for u in wp-json/wp/v2/users wp-json/wp/v2/comments "?author=2" author/gama/ \
         comments/feed/ wp-json/ wp-json/wp/v2/posts \
         wp-json/wc/store/v1/products wp-json/contact-form-7/v1 feed/; do
  printf "%-40s %s\n" "$u" "$(curl -s -o /dev/null -A Mozilla/5.0 -w '%{http_code}' "$B/$u")"
done
```

First five should read `404 404 404 404 410`; the last five `200`.

The `<head>` of any page should contain no `rel="pingback"`, no `EditURI` and no
`api.w.org`, and responses should carry no `X-Pingback` header — while
`POST /xmlrpc.php` still answers `200`.

These cannot be checked anonymously and need a logged-in session:

- Order notes on a real order, **both private and customer notes**
- The block editor loads, its author control populates, and pages save
- WooCommerce Analytics / Home screens
- Comments absent from the admin menu (`/wp-admin/edit-comments.php` still
  loads directly, by design)
- A real Contact Form 7 submission arriving by email
- Block add-to-cart on the homepage
