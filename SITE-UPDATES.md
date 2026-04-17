# Haley Yachts - Site Update Instructions

A running reference for how to update common pieces of the website. Keep this doc open when making edits so you know which file to touch.

---

## Featured Yachts (Home + Buy pages)

The same list of featured yachts appears in two places:
- Home page, "Featured Yachts" section (below the hero video)
- Buy page, "Featured Yachts" sub-tab

Both pages read from one source: `js/featured-yachts.js`.

### Easy method: Featured Image Maintenance screen

Open `/admin/featured-yachts.html` in your browser (Chrome or Edge). It gives you a form with:
- Position (1, 2, or 3)
- Image name, Title (max 60 chars), Description (max 250 chars)

Fill it in and click **SAVE**.

**First time only:** Chrome will ask you to locate `js/featured-yachts.js` on your computer. Pick it once - the browser remembers that file handle, so every future SAVE writes directly to it. You may be asked to re-grant write permission each browser session (one click).

After saving, refresh the Home or Buy page to see the change.

### Manual method

Edit `js/featured-yachts.js` directly in a text editor.

### Fields for each yacht

- `image` - path to the yacht photo (leave empty `''` to show a placeholder)
- `name` - headline shown on the card (e.g., "2024 Riviera 68 Sports Motor Yacht")
- `description` - short blurb under the name (price, key specs, highlights)
- `link` - where the "Inquire" button goes (usually `contact.html`)
- `linkText` - button label (default: `Inquire`)

### To add a new featured yacht

1. Save the yacht photo to `images/yachts/featured/` (lowercase filename, hyphens between words, e.g., `riviera-68-smy.jpg`)
2. Open `js/featured-yachts.js`
3. Copy an existing yacht block (the `{ ... }` object) and paste a new one into the array
4. Update the fields
5. Save. Refresh the browser on the Home or Buy page to confirm.

### To remove a yacht

1. Open `js/featured-yachts.js`
2. Delete the entire `{ ... }` block for that yacht (including the trailing comma)
3. Save.

### To reorder yachts

Cut and paste the `{ ... }` blocks in the order you want them to appear. Top of the array = leftmost card.

### Example entry

```javascript
{
    image: 'images/yachts/featured/riviera-68-smy.jpg',
    name: '2024 Riviera 68 Sports Motor Yacht',
    description: 'Twin Volvo IPS1200s, four staterooms, hydraulic swim platform. Asking $4.9M.',
    link: 'contact.html',
    linkText: 'Inquire'
}
```

---

## Images

**Folder structure:**
- `images/brand/` - logo, favicon, monograms
- `images/yachts/` - yacht photos (stock, featured, new builds)
- `images/lifestyle/` - people, cruising, ambiance shots
- `images/people/` - headshots
- `images/graphics/` - icons, diagrams
- `images/video/` - hero video and other video assets

**Naming rules:**
- All lowercase
- Hyphens between words (not underscores or spaces)
- Example: `riviera-68-stern.jpg`, not `Riviera 68 Stern.JPG`

---

## Hero Video (Home page)

**Video file:** `images/video/home-header-video.mp4`
**Poster image (fallback):** `images/hero-poster.jpg`

To swap the video:
1. Replace `home-header-video.mp4` with the new file (same name)
2. Regenerate the poster still if desired (extract a frame from the new video)

---

---

## Admin Security Setup (one-time, on GoDaddy)

The admin area (`/admin/`) is protected by an `.htaccess` file that requires HTTP Basic Authentication. You must activate it on the server the first time you upload the site.

### Step-by-step in cPanel

1. Log in to your GoDaddy account and open **cPanel** for this hosting site.
2. In cPanel, find **Directory Privacy** (sometimes listed as "Password Protect Directories").
3. Browse to `public_html/admin` and click the folder.
4. Check **"Password protect this directory"**.
5. Give the protected area a name (e.g., `Haley Yachts Admin`). Click **Save**.
6. Back on the same page, scroll down to **"Create User"**. Add a username (e.g., `clark`) and a strong password. Click **Add/Modify Authorized User**.

That's it. Anyone visiting `haleyyachts.com/admin/` will now be prompted for credentials.

### What the `.htaccess` files do

- **`/admin/.htaccess`** - triggers the password prompt for everything inside `/admin/`. Also blocks direct access to `.htaccess`, `.htpasswd`, and `config.yml`. When you set up Directory Privacy in cPanel, it may overwrite this file - that's fine, cPanel's version does the same job.
- **`/.htaccess`** (site root) - enforces HTTPS redirect (commented out until SSL is active), blocks dotfiles and backup files, sets security headers, enables compression and browser caching.

### After SSL is installed

1. Open `/.htaccess` in the site root.
2. Uncomment the HTTPS redirect block (four lines near the top starting with `# RewriteEngine On`).
3. Uncomment the `Strict-Transport-Security` header line at the bottom of the headers block.
4. Save and re-upload.

### Testing locally vs. live

- `.htaccess` files only work when served by Apache - they do nothing when opening files directly in a browser (`file://`).
- To test auth locally you'd need a local Apache (MAMP or XAMPP). Otherwise, verify once on GoDaddy.
- The Featured Image Maintenance tool's SAVE button requires HTTPS (or localhost) to work. GoDaddy with SSL will satisfy that.

---

## Articles (blog posts)

Articles live on haleyyachts.com in a simple, fast, search-engine-friendly format. Clark writes the source material; Claude reformats for the web and uploads.

### Categories (locked on the Articles page)

- **Boat Reviews** - folder: `articles/boat-reviews/`
- **Newsletters** - folder: `articles/newsletters/`
- **How To** - folder: `articles/how-to/`
- **Travel** - folder: `articles/travel/`
- **Industry News** - folder: `articles/industry-news/`

Each category is a sub-tab on `articles.html`. Articles don't need a category picker - they just live in the right folder.

### What Clark sends Claude

For each new article, send one zip or email with:

1. **The Word doc** (or Google Doc, plain text - whatever's easy). Include:
   - Headline (plus 1-2 alternate headlines if you can't decide)
   - The body of the article
   - Any callouts, pull quotes, or bullet lists you want highlighted
2. **Photos** - hero image (required) plus any inline images. Any size is fine; Claude resizes as needed. Include a short caption for each photo.
3. **Category** - which of the five buckets it belongs in
4. **Editing level** - one of:
   - *Light touch* - typos and HTML only, keep my words
   - *Moderate edit* - tighten sentences, preserve voice
   - *Full rewrite in my voice* - use as source, rewrite in the style from mvroam.com / svdoublewide.com
5. **Target publish date** (optional) - if you want it held until a specific day

### What Claude does with it

Every article gets the full SEO treatment automatically. The template at `articles/_template.html` includes:

- `<title>` and `<meta description>` tuned for search (60-char title, 155-char description)
- Open Graph tags for Facebook, LinkedIn, iMessage, Slack previews
- Twitter / X card tags
- Canonical URL
- JSON-LD `Article` schema markup (Google rich results eligible)
- JSON-LD `BreadcrumbList` schema (improved search listing)
- Author attribution tied to Clark's About page
- Published and modified timestamps
- Semantic HTML (`<article>`, `<time>`, `<figure>`, `<h1>` through `<h3>`)
- `alt` text on every image
- `loading="lazy"` on inline images, `fetchpriority="high"` on hero
- Internal links to Contact / About where natural
- Breadcrumb navigation
- Author bio card at the end with CTA to Contact
- "Back to [Category]" link

### File naming convention

- URL slug: lowercase, hyphens, keyword-rich. Example: `riviera-68-sports-motor-yacht-first-impressions.html`
- Hero image: `hero.jpg` or descriptive slug like `riviera-68-at-dock.jpg` inside `articles/<category>/images/`
- One `images/` subfolder per category folder (keeps asset paths clean)

### Publish step

1. Claude creates the new article HTML file in the right category folder
2. Claude adds a new `<div class="article-card">` to `articles.html` with thumbnail, headline, excerpt, date - linked to the article
3. Claude updates `sitemap.xml` (once created) with the new URL
4. Clark reviews the live page; Claude adjusts if anything's off

### After the site is live

Every new article should be:
- Posted to LinkedIn with a personal hook
- Shared to Facebook Business page
- Teased on Instagram / X with the hero image
- Optionally: included in a periodic email newsletter

Claude can draft the social teasers at the same time as the article - just ask.

---

## Worldwide Listings (Buy page)

The "World Wide Listings" sub-tab on `buy.html` uses a third-party embed from YachtSite.com. It pulls live listings from their MLS feed and renders them inside the page.

### Where it lives

File: `buy.html`, inside the `<div id="worldwide">` block. The embed is a single `<script>` tag:

```html
<script src="https://www.yachtsite.com/wp-content/themes/catsite2/js/listings-embed.js"
        power_sail="" min_length="" max_length="" per_page="100"></script>
```

### Tunable parameters

- `power_sail` - `"power"`, `"sail"`, or blank for both
- `min_length` - minimum length in feet (blank = no minimum)
- `max_length` - maximum length in feet (blank = no maximum)
- `per_page` - how many results per page (currently 100)

To change the filter, edit those attribute values directly in `buy.html`.

### Testing

The embed may not render fully on `file://` or localhost - test on a real host (staging or production) to confirm listings load and links work.

---

*This doc will grow as we add more update procedures. Next sections to add: blog/articles (via DecapCMS), contact form routing, navigation/menu changes.*
