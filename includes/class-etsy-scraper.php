<?php
/**
 * Etsy Scraper Class
 *
 * Scrapes Etsy listing pages to extract product information
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Etsy_Scraper {

    /**
     * Validate Etsy URL
     */
    public function validate_url($url) {
        // Check if URL contains etsy.com and /listing/
        if (strpos($url, 'etsy.com') === false || strpos($url, '/listing/') === false) {
            return false;
        }

        // Extract listing ID
        $listing_id = $this->extract_listing_id($url);
        return !empty($listing_id);
    }

    /**
     * Extract listing ID from URL
     */
    private function extract_listing_id($url) {
        if (preg_match('/\/listing\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Scrape Etsy listing
     */
    public function scrape_listing($url) {
        if (!$this->validate_url($url)) {
            throw new Exception(__('Invalid Etsy listing URL', 'wc-etsy-importer'));
        }

        $listing_id = $this->extract_listing_id($url);

        // Fetch page content
        $html = $this->fetch_page($url);

        if (empty($html)) {
            throw new Exception(__('Failed to fetch Etsy listing page', 'wc-etsy-importer'));
        }

        // Parse the HTML to extract product data
        $data = $this->parse_listing_html($html, $listing_id);

        if (empty($data)) {
            throw new Exception(__('Failed to parse Etsy listing data', 'wc-etsy-importer'));
        }

        return $data;
    }

    /**
     * Fetch page content
     */
    private function fetch_page($url) {
        $args = array(
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers'     => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ),
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new Exception(sprintf(__('HTTP error %d when fetching Etsy listing', 'wc-etsy-importer'), $code));
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Parse listing HTML
     */
    private function parse_listing_html($html, $listing_id) {
        $data = array(
            'listing_id' => $listing_id,
            'title' => '',
            'description' => '',
            'price' => 0,
            'images' => array(),
            'variations' => array(),
        );

        // Load HTML into DOMDocument
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        // Try to extract JSON-LD data first (more reliable)
        $json_data = $this->extract_json_ld($dom);
        if ($json_data) {
            $data = array_merge($data, $json_data);
        }

        // Extract additional data from meta tags and page content
        $this->extract_meta_data($dom, $data);

        // Extract variations from the page
        $this->extract_variations($html, $data);

        // If we still don't have basic data, try alternative methods
        if (empty($data['title'])) {
            $this->extract_fallback_data($dom, $data);
        }

        return $data;
    }

    /**
     * Extract JSON-LD structured data
     */
    private function extract_json_ld($dom) {
        $xpath = new DOMXPath($dom);
        $scripts = $xpath->query('//script[@type="application/ld+json"]');

        foreach ($scripts as $script) {
            $json = json_decode($script->nodeValue, true);

            if (isset($json['@type']) && $json['@type'] === 'Product') {
                $data = array();

                if (isset($json['name'])) {
                    $data['title'] = $json['name'];
                }

                if (isset($json['description'])) {
                    $data['description'] = $json['description'];
                }

                if (isset($json['offers'])) {
                    $offers = $json['offers'];
                    if (isset($offers['price'])) {
                        $data['price'] = floatval($offers['price']);
                    } elseif (isset($offers['lowPrice'])) {
                        $data['price'] = floatval($offers['lowPrice']);
                    }
                }

                if (isset($json['image'])) {
                    if (is_array($json['image'])) {
                        $data['images'] = $json['image'];
                    } else {
                        $data['images'] = array($json['image']);
                    }
                }

                return $data;
            }
        }

        return null;
    }

    /**
     * Extract meta data
     */
    private function extract_meta_data($dom, &$data) {
        $xpath = new DOMXPath($dom);

        // Extract title from og:title
        $title = $xpath->query('//meta[@property="og:title"]/@content');
        if ($title->length > 0 && empty($data['title'])) {
            $data['title'] = $title->item(0)->nodeValue;
        }

        // Extract description from og:description or meta description
        $description = $xpath->query('//meta[@property="og:description"]/@content');
        if ($description->length > 0 && empty($data['description'])) {
            $data['description'] = $description->item(0)->nodeValue;
        } else {
            $description = $xpath->query('//meta[@name="description"]/@content');
            if ($description->length > 0 && empty($data['description'])) {
                $data['description'] = $description->item(0)->nodeValue;
            }
        }

        // Extract images from og:image
        $og_images = $xpath->query('//meta[@property="og:image"]/@content');
        if ($og_images->length > 0 && empty($data['images'])) {
            foreach ($og_images as $og_image) {
                $data['images'][] = $og_image->nodeValue;
            }
        }
    }

    /**
     * Extract variations from page HTML
     */
    private function extract_variations($html, &$data) {
        // Try to find variations in various script tags or data attributes
        // Etsy often stores this in __INITIAL_STATE__ or similar JSON objects

        // Pattern to find variation data
        if (preg_match('/\"variations\":\s*(\[.*?\])/s', $html, $matches)) {
            $variations_json = json_decode($matches[1], true);
            if (is_array($variations_json)) {
                $data['variations'] = $this->parse_variations($variations_json);
            }
        }

        // Alternative: Look for INITIAL_STATE
        if (preg_match('/"variation_options":\s*(\{.*?\})/s', $html, $matches)) {
            $variation_data = json_decode($matches[1], true);
            if (is_array($variation_data)) {
                $data['variations'] = $this->parse_variation_options($variation_data);
            }
        }
    }

    /**
     * Parse variations array
     */
    private function parse_variations($variations_json) {
        $parsed_variations = array();

        foreach ($variations_json as $variation) {
            if (isset($variation['name']) && isset($variation['value'])) {
                if (!isset($parsed_variations[$variation['name']])) {
                    $parsed_variations[$variation['name']] = array();
                }

                $variation_option = array(
                    'value' => $variation['value'],
                    'price' => isset($variation['price']) ? floatval($variation['price']) : 0,
                );

                $parsed_variations[$variation['name']][] = $variation_option;
            }
        }

        return $parsed_variations;
    }

    /**
     * Parse variation options
     */
    private function parse_variation_options($variation_data) {
        $parsed_variations = array();

        foreach ($variation_data as $name => $options) {
            $parsed_variations[$name] = array();

            if (is_array($options)) {
                foreach ($options as $option) {
                    if (is_array($option)) {
                        $parsed_variations[$name][] = array(
                            'value' => isset($option['formatted_value']) ? $option['formatted_value'] : (isset($option['value']) ? $option['value'] : ''),
                            'price' => isset($option['price_diff']) ? floatval($option['price_diff']) : 0,
                        );
                    } else {
                        $parsed_variations[$name][] = array(
                            'value' => $option,
                            'price' => 0,
                        );
                    }
                }
            }
        }

        return $parsed_variations;
    }

    /**
     * Extract fallback data when structured data is not available
     */
    private function extract_fallback_data($dom, &$data) {
        $xpath = new DOMXPath($dom);

        // Try to get title from h1
        if (empty($data['title'])) {
            $h1 = $xpath->query('//h1');
            if ($h1->length > 0) {
                $data['title'] = trim($h1->item(0)->nodeValue);
            }
        }

        // Try to get price from various selectors
        if (empty($data['price'])) {
            $price_selectors = array(
                '//p[contains(@class, "wt-text-title-03")]',
                '//div[contains(@class, "price")]',
                '//span[contains(@class, "currency-value")]',
            );

            foreach ($price_selectors as $selector) {
                $price_elements = $xpath->query($selector);
                if ($price_elements->length > 0) {
                    $price_text = $price_elements->item(0)->nodeValue;
                    $price_text = preg_replace('/[^\d.]/', '', $price_text);
                    if (!empty($price_text)) {
                        $data['price'] = floatval($price_text);
                        break;
                    }
                }
            }
        }

        // Try to get images from img tags
        if (empty($data['images'])) {
            $images = $xpath->query('//img[contains(@class, "wt-max-width-full") or contains(@data-src, "il_fullxfull")]/@src | //img[contains(@class, "wt-max-width-full") or contains(@data-src, "il_fullxfull")]/@data-src');
            foreach ($images as $img) {
                $src = $img->nodeValue;
                if (!empty($src) && strpos($src, 'data:image') !== 0) {
                    $data['images'][] = $src;
                }
            }
        }
    }
}
