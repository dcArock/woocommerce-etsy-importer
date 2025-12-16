# WooCommerce Etsy Importer

A WordPress plugin that allows you to import Etsy listings as WooCommerce products with full support for images, variations, pricing, and product details.

## Features

- 🛍️ **Easy Import**: Paste one or more Etsy listing URLs and import them automatically
- 🖼️ **Complete Product Data**: Imports titles, descriptions, prices, images, and image galleries
- 🎨 **Variation Support**: Automatically imports product variations with matching prices
- 📊 **Progress Tracking**: Real-time progress bars for individual items and overall import
- 📝 **Detailed Logging**: Complete import log showing success/failure for each item
- ✅ **Product Management**: Direct links to edit imported products in WooCommerce
- 🔄 **Batch Import**: Import multiple listings at once, processed one at a time

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

### Method 1: Upload via WordPress Admin

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"
5. Click "Activate Plugin"

### Method 2: Manual Installation

1. Download or clone this repository
2. Upload the `woocommerce-etsy-importer` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

### Method 3: Install from GitHub

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/dcArock/woocommerce-etsy-importer.git
```

Then activate the plugin in WordPress admin.

## Usage

### Basic Import

1. Go to **Etsy Importer** in your WordPress admin menu
2. Enter one or more Etsy listing URLs (one per line) in the text area
3. Click **Start Import**
4. Watch the progress bars as each listing is imported
5. Review the imported products list and click "Edit Product" to customize

### Example Etsy URL

```
https://greenwoodcreation.etsy.com/listing/1896333507
https://www.etsy.com/listing/123456789/product-name
```

### What Gets Imported

For each Etsy listing, the plugin imports:

- ✅ Product title
- ✅ Product description (full and short)
- ✅ Product price
- ✅ Featured image (first image from listing)
- ✅ Image gallery (all additional images)
- ✅ Product variations (size, color, etc.)
- ✅ Variation-specific pricing
- ✅ Etsy listing ID (stored as metadata)

### Imported Product Status

All imported products are created with a **Draft** status, allowing you to review and edit them before publishing.

## Screenshots

### Admin Interface
The plugin adds a dedicated menu item "Etsy Importer" with an intuitive interface for entering URLs.

### Progress Tracking
- **Overall Progress Bar**: Shows total import progress across all listings
- **Current Item Progress Bar**: Shows progress for the current listing being imported
- **Real-time Status**: Displays current operation (fetching, scraping, creating product)
- **Import Log**: Console-style log with timestamps and color-coded messages

### Imported Products
After import completes, you'll see a list of all successfully imported products with direct links to edit them.

## How It Works

### 1. URL Validation
The plugin validates that URLs are valid Etsy listing URLs before processing.

### 2. Data Scraping
The plugin fetches the Etsy listing page and extracts:
- JSON-LD structured data (most reliable)
- Open Graph meta tags
- Page HTML content (fallback)

### 3. Product Creation
Based on the scraped data:
- **Simple products** are created for listings without variations
- **Variable products** are created for listings with variations
- Product attributes are created dynamically
- All variation combinations are generated automatically

### 4. Image Import
- Images are downloaded from Etsy and uploaded to your WordPress media library
- The first image becomes the featured image
- Remaining images are added to the product gallery

## Troubleshooting

### "Failed to scrape Etsy listing"
- Check that the URL is a valid Etsy listing URL
- Ensure your server can make outbound HTTP requests
- Try the listing URL in a browser to confirm it's accessible

### "WooCommerce Etsy Importer requires WooCommerce"
- Install and activate WooCommerce before using this plugin

### No images imported
- Check that `wp-content/uploads` is writable
- Verify your server allows downloading remote files
- Check PHP memory limits

### Variations not created correctly
- The plugin attempts to parse variations from Etsy's page structure
- Some complex variation setups may not import perfectly
- You can manually adjust variations after import

## Development

### File Structure

```
woocommerce-etsy-importer/
├── woocommerce-etsy-importer.php    # Main plugin file
├── includes/
│   ├── class-admin-page.php          # Admin interface
│   ├── class-etsy-scraper.php        # Etsy data scraper
│   └── class-wc-product-creator.php  # WooCommerce product creator
├── assets/
│   ├── css/
│   │   └── admin.css                 # Admin styles
│   └── js/
│       └── admin.js                  # Admin JavaScript
└── README.md
```

### Hooks and Filters

The plugin provides several hooks for customization:

```php
// Modify scraped data before product creation
add_filter('wcei_scraped_listing_data', function($data, $url) {
    // Modify $data array
    return $data;
}, 10, 2);

// Modify product data before creation
add_filter('wcei_product_data', function($product_data, $listing_data) {
    // Modify product settings
    return $product_data;
}, 10, 2);

// After product import
add_action('wcei_product_imported', function($product_id, $listing_data) {
    // Do something after import
}, 10, 2);
```

## Limitations

- The plugin scrapes public Etsy listing pages and doesn't use the Etsy API
- Some complex variation structures may not import perfectly
- Etsy may change their page structure, which could affect scraping
- Rate limiting: Consider adding delays between imports for large batches

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This plugin is licensed under the GPL v2 or later.

## Support

For issues, questions, or contributions:
- GitHub Issues: https://github.com/dcArock/woocommerce-etsy-importer/issues

## Changelog

### 1.0.1 (2025-01-16)
- **Critical Fix**: Fixed fatal error when plugin is activated
- Added class existence checks before initialization
- Added WooCommerce dependency verification in AJAX handlers
- Improved error handling for missing dependencies
- Plugin now gracefully handles activation when WooCommerce is not active

### 1.0.0 (2025-01-01)
- Initial release
- Basic import functionality
- Support for simple and variable products
- Progress tracking with real-time updates
- Image gallery import
- Variation import with pricing
- Import logging and error handling

## Credits

Developed by [Your Name](https://github.com/dcArock)

---

**Note**: This plugin is not affiliated with or endorsed by Etsy. Etsy is a trademark of Etsy, Inc.
