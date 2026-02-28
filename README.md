# Listing Archive Combos (Region × Category)

A WordPress plugin that provides an admin UI to define custom content per **(Region × Category)** combination for `job_listing` archives (e.g. Listify / WP Job Manager). Each combo can include intro sections, service cards, and FAQs with optional schema.org markup.

## Description

- **Admin:** Tools → Listing Archive Combos — add rows for each Region × Category pair and edit content.
- **Sections per combo:** Section 1 (heading + WYSIWYG), Section 2 (intro + 4 cards: Coworking, Day Offices, Meeting & Event, Private Offices), Section 3 (FAQs with JSON-LD).
- **Access:** Admins and users with the SEO Manager role (`wpseo_manager`) can manage combos.
- **Data:** Stored in option `jl_region_category_content`; inline styles (e.g. white text, Google Docs formatting) are cleaned on save.

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Taxonomies: `job_listing_region`, `job_listing_category` (e.g. from Listify/WP Job Manager)

## Installation

1. Clone or download into `wp-content/plugins/`:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/YOUR_USERNAME/listing-archive-combos.git
   ```
2. In **Plugins**, activate **Listing Archive Combos (Region × Category)**.

## Usage

### Admin

1. Go to **Tools → Listing Archive Combos**.
2. Use **Add another combination**, choose Region and (optionally) Category.
3. Fill Section 1 (heading + content), Section 2 (intro + 4 cards with image + content), Section 3 (FAQs).
4. Click **Save Combos**.

### Frontend (theme/template)

```php
// Output sections for the current archive (region + category from query)
jl_the_archive_combo_sections_from_query($print_schema = true);

// Get combo data for a specific region/category
$combo = jl_get_archive_combo($region_term_id, $category_term_id);

// Render sections from a combo row
jl_render_archive_combo_sections($row, $print_schema = true);
```

## Features

- Section 1: heading + WYSIWYG content.
- Section 2: intro WYSIWYG + 4 cards (image + content each).
- Section 3: FAQs (question + WYSIWYG answer); optional FAQPage JSON-LD when `$print_schema` is true.
- Auto-clean of problematic inline styles on save; optional “Clean ALL White Text” for existing content.
- Access for SEO Managers (same as admins for this page).
- Unsaved-changes warning, save feedback, and duplicate Region×Category validation.

## File Structure

```
listing-archive-combos/
├── listing-archive-combos.php   # Main plugin
├── includes/
│   └── admin-page.php           # Admin page view
├── templates/
│   └── row-template.html        # Row template (JS)
├── assets/
│   ├── admin.css
│   └── admin.js
├── README.md
├── LICENSE
└── .gitignore
```

## Changelog

### 1.5.1
- Current release (inline style cleaning, SEO Manager access, FAQ schema, etc.).

### 1.3.0
- TinyMCE sync before save, FAQ persistence, unsaved changes warning, improved validation and messages.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Author

**ARBAB KHIZAR**
