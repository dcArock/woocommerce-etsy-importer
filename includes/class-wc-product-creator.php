<?php
/**
 * WooCommerce Product Creator Class
 *
 * Creates WooCommerce products from Etsy listing data
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Product_Creator {

    /**
     * Create WooCommerce product from Etsy data
     */
    public function create_product($listing_data) {
        if (empty($listing_data['title'])) {
            return new WP_Error('missing_title', __('Product title is required', 'wc-etsy-importer'));
        }

        // Check if product has variations
        $has_variations = !empty($listing_data['variations']) && is_array($listing_data['variations']);

        if ($has_variations) {
            return $this->create_variable_product($listing_data);
        } else {
            return $this->create_simple_product($listing_data);
        }
    }

    /**
     * Create simple product
     */
    private function create_simple_product($listing_data) {
        $product = new WC_Product_Simple();

        // Set basic product data
        $product->set_name($listing_data['title']);
        $product->set_status('draft'); // Set as draft for review
        $product->set_catalog_visibility('visible');

        // Set description
        if (!empty($listing_data['description'])) {
            $product->set_description($listing_data['description']);
            $product->set_short_description($this->create_short_description($listing_data['description']));
        }

        // Set price
        if (!empty($listing_data['price'])) {
            $product->set_regular_price($listing_data['price']);
        }

        // Save the product first to get an ID
        $product_id = $product->save();

        if (!$product_id) {
            return new WP_Error('product_creation_failed', __('Failed to create product', 'wc-etsy-importer'));
        }

        // Add images
        if (!empty($listing_data['images'])) {
            $this->add_product_images($product_id, $listing_data['images']);
        }

        // Add Etsy listing ID as meta
        if (!empty($listing_data['listing_id'])) {
            update_post_meta($product_id, '_etsy_listing_id', $listing_data['listing_id']);
        }

        return $product_id;
    }

    /**
     * Create variable product with variations
     */
    private function create_variable_product($listing_data) {
        $product = new WC_Product_Variable();

        // Set basic product data
        $product->set_name($listing_data['title']);
        $product->set_status('draft'); // Set as draft for review
        $product->set_catalog_visibility('visible');

        // Set description
        if (!empty($listing_data['description'])) {
            $product->set_description($listing_data['description']);
            $product->set_short_description($this->create_short_description($listing_data['description']));
        }

        // Save the product first to get an ID
        $product_id = $product->save();

        if (!$product_id) {
            return new WP_Error('product_creation_failed', __('Failed to create variable product', 'wc-etsy-importer'));
        }

        // Create attributes from variations
        $attributes = $this->create_attributes($listing_data['variations'], $product_id);
        $product->set_attributes($attributes);
        $product->save();

        // Create variations
        $this->create_variations($product_id, $listing_data['variations'], $listing_data['price']);

        // Add images
        if (!empty($listing_data['images'])) {
            $this->add_product_images($product_id, $listing_data['images']);
        }

        // Add Etsy listing ID as meta
        if (!empty($listing_data['listing_id'])) {
            update_post_meta($product_id, '_etsy_listing_id', $listing_data['listing_id']);
        }

        // Sync variable product
        WC_Product_Variable::sync($product_id);

        return $product_id;
    }

    /**
     * Create product attributes from variations
     */
    private function create_attributes($variations_data, $product_id) {
        $attributes = array();
        $position = 0;

        foreach ($variations_data as $attribute_name => $options) {
            $attribute = new WC_Product_Attribute();

            // Generate attribute slug
            $attribute_slug = sanitize_title($attribute_name);

            // Get or create the attribute taxonomy
            $attribute_id = $this->get_or_create_attribute_taxonomy($attribute_name, $attribute_slug);

            if ($attribute_id) {
                // Use taxonomy attribute
                $attribute->set_id($attribute_id);
                $attribute->set_name('pa_' . $attribute_slug);

                // Get or create terms
                $term_ids = array();
                foreach ($options as $option) {
                    $term_id = $this->get_or_create_term($option['value'], 'pa_' . $attribute_slug);
                    if ($term_id) {
                        $term_ids[] = $term_id;
                    }
                }

                $attribute->set_options($term_ids);
            } else {
                // Use custom attribute (non-taxonomy)
                $attribute->set_name($attribute_name);
                $values = array_map(function($option) {
                    return $option['value'];
                }, $options);
                $attribute->set_options($values);
            }

            $attribute->set_position($position);
            $attribute->set_visible(true);
            $attribute->set_variation(true);

            $attributes[] = $attribute;
            $position++;
        }

        return $attributes;
    }

    /**
     * Get or create attribute taxonomy
     */
    private function get_or_create_attribute_taxonomy($name, $slug) {
        global $wpdb;

        $attribute_id = wc_attribute_taxonomy_id_by_name($slug);

        if (!$attribute_id) {
            $attribute_id = wc_create_attribute(array(
                'name' => $name,
                'slug' => $slug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ));

            // Register the taxonomy
            if (!is_wp_error($attribute_id)) {
                register_taxonomy('pa_' . $slug, array('product'), array());
            }
        }

        return is_wp_error($attribute_id) ? false : $attribute_id;
    }

    /**
     * Get or create term
     */
    private function get_or_create_term($name, $taxonomy) {
        $term = get_term_by('name', $name, $taxonomy);

        if (!$term) {
            $result = wp_insert_term($name, $taxonomy);
            if (is_wp_error($result)) {
                return false;
            }
            return $result['term_id'];
        }

        return $term->term_id;
    }

    /**
     * Create product variations
     */
    private function create_variations($product_id, $variations_data, $base_price) {
        $attribute_names = array_keys($variations_data);
        $variation_combinations = $this->generate_variation_combinations($variations_data);

        foreach ($variation_combinations as $combination) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_status('publish');

            // Set attributes for this variation
            $variation_attributes = array();
            $variation_price_diff = 0;

            foreach ($combination as $attribute_name => $option) {
                $attribute_slug = sanitize_title($attribute_name);

                // Check if taxonomy exists
                if (taxonomy_exists('pa_' . $attribute_slug)) {
                    $term = get_term_by('name', $option['value'], 'pa_' . $attribute_slug);
                    if ($term) {
                        $variation_attributes['pa_' . $attribute_slug] = $term->slug;
                    }
                } else {
                    $variation_attributes[$attribute_name] = $option['value'];
                }

                // Add price difference
                if (isset($option['price']) && $option['price'] > 0) {
                    $variation_price_diff += $option['price'];
                }
            }

            $variation->set_attributes($variation_attributes);

            // Set variation price
            $variation_price = floatval($base_price) + $variation_price_diff;
            $variation->set_regular_price($variation_price);

            // Save variation
            $variation->save();
        }
    }

    /**
     * Generate all possible combinations of variations
     */
    private function generate_variation_combinations($variations_data) {
        $combinations = array(array());

        foreach ($variations_data as $attribute_name => $options) {
            $new_combinations = array();

            foreach ($combinations as $combination) {
                foreach ($options as $option) {
                    $new_combination = $combination;
                    $new_combination[$attribute_name] = $option;
                    $new_combinations[] = $new_combination;
                }
            }

            $combinations = $new_combinations;
        }

        return $combinations;
    }

    /**
     * Add product images
     */
    private function add_product_images($product_id, $images) {
        $image_ids = array();

        foreach ($images as $index => $image_url) {
            // Download and attach image
            $image_id = $this->upload_image_from_url($image_url, $product_id);

            if ($image_id && !is_wp_error($image_id)) {
                $image_ids[] = $image_id;

                // Set first image as featured image
                if ($index === 0) {
                    set_post_thumbnail($product_id, $image_id);
                }
            }
        }

        // Set gallery images (exclude first one as it's the featured image)
        if (count($image_ids) > 1) {
            $gallery_ids = array_slice($image_ids, 1);
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }

        return $image_ids;
    }

    /**
     * Upload image from URL
     */
    private function upload_image_from_url($image_url, $product_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download the image
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Get file name from URL
        $file_array = array();
        preg_match('/[^\?]+\.(jpg|jpeg|gif|png|webp)/i', $image_url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;

        // If error storing temporarily, unlink
        if (is_wp_error($tmp)) {
            @unlink($file_array['tmp_name']);
            return $tmp;
        }

        // Do the upload
        $image_id = media_handle_sideload($file_array, $product_id);

        // If error storing permanently, unlink
        if (is_wp_error($image_id)) {
            @unlink($file_array['tmp_name']);
            return $image_id;
        }

        return $image_id;
    }

    /**
     * Create short description from full description
     */
    private function create_short_description($description) {
        // Strip HTML tags
        $text = wp_strip_all_tags($description);

        // Limit to 160 characters
        if (strlen($text) > 160) {
            $text = substr($text, 0, 157) . '...';
        }

        return $text;
    }
}
