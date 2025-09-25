// Add this code to your theme's functions.php file  
// Easy Way to Update Product Prices in WooCommerce
add_action( 'template_redirect', function() {

    // Only logged-in admin users
    if ( ! is_user_logged_in() || ! current_user_can( 'administrator' ) ) {
        return;
    }

    $percent     = 0;
    $action      = '';
    $round       = isset($_GET['round']) ? sanitize_text_field($_GET['round']) : '';
    $roundbase   = isset($_GET['roundbase']) ? intval($_GET['roundbase']) : 1000; //Default is 1000 Toman
    if ( $roundbase <= 0 ) $roundbase = 1;
    $saleprice   = ( isset($_GET['saleprice']) && $_GET['saleprice'] === 'true' );
    $productType = isset($_GET['product']) ? sanitize_text_field($_GET['product']) : 'all'; // Default all

    $offset      = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    $limit       = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;

    if ( isset($_GET['increase']) ) {
        $percent = intval($_GET['increase']);
        $action  = 'increase';
    }
    if ( isset($_GET['decrease']) ) {
        $percent = intval($_GET['decrease']);
        $action  = 'decrease';
    }

    if ( ! $action || ! $percent ) {
        return;
    }

    // Apply rounding based on round and roundbase
    $apply_rounding = function( $value ) use ( $round, $roundbase ) {
        if ( $value === null ) return null;
        if ( ! is_numeric( $value ) ) return null;
        $v = (float) $value;
        if ( $round === 'up' ) {
            return ceil( $v / $roundbase ) * $roundbase;
        } elseif ( $round === 'down' ) {
            return floor( $v / $roundbase ) * $roundbase;
        }
        return $v;
    };

    // Calculate new price (avoid negative) - returns final value (with rounding)
    $calculate_new_price = function( $price ) use ( $action, $percent, $apply_rounding ) {
        if ( $price === '' || ! is_numeric( $price ) ) {
            return null;
        }
        $price = (float) $price;
        $new_price = $action === 'increase'
            ? $price * ( 1 + $percent / 100 )
            : $price * ( 1 - $percent / 100 );
        $new_price = max( 0, $new_price );
        return $apply_rounding( $new_price );
    };

    // Helper: calculate a value lower than regular price with respect to roundbase step
    $floor_below_regular = function( $regular ) use ( $roundbase ) {
        $r = (float) $regular;
        $candidate = floor( ( $r - 1 ) / $roundbase ) * $roundbase;
        if ( $candidate < 0 ) $candidate = 0;
        return $candidate;
    };

    // Get total count of all products (only IDs)
    $all_ids = wc_get_products([
        'status' => 'publish',
        'limit'  => -1,
        'return' => 'ids',
    ]);
    $total_count = is_array( $all_ids ) ? count( $all_ids ) : 0;

    // Get product batch with offset/limit
    $args = [
        'status' => 'publish',
        'limit'  => $limit,
        'offset' => $offset,
        'return' => 'ids',
    ];
    $products = wc_get_products( $args );
    $changed = 0;
    $processed = 0;

    foreach ( $products as $product_id ) {
        $processed++;
        $product = wc_get_product( $product_id );
        if ( ! $product ) continue;

        // Filter by product type
        if ( $productType === 'simple' && ! $product->is_type( 'simple' ) ) {
            continue;
        }
        if ( $productType === 'variable' && ! $product->is_type( 'variable' ) ) {
            continue;
        }

        // --- Simple products ---
        if ( $product->is_type( 'simple' ) ) {
            $reg_raw  = $product->get_regular_price();
            $sale_raw = $product->get_sale_price();

            $new_reg = $calculate_new_price( $reg_raw );
            if ( $new_reg === null ) {
                continue;
            }

            $product_changed = false;
            // If the actual price has changed, then set and save
            if ( (string)$product->get_regular_price() !== (string)$new_reg ) {
                $product->set_regular_price( $new_reg );
                $product_changed = true;
            }

            // ===== Manage sale price =====
            if ( $saleprice ) {
                // If sale exists, try to keep the previous discount percentage
                if ( $sale_raw !== '' && is_numeric( $sale_raw ) && is_numeric( $reg_raw ) && (float)$reg_raw > 0 ) {
                    $orig_reg = (float)$reg_raw;
                    $orig_sale = (float)$sale_raw;
                    $discount_pct = ( $orig_reg - $orig_sale ) / $orig_reg; // عدد بین 0 و 1

                    // If rounding caused sale >= regular -> fallback: largest value below regular based on roundbase
                    $new_sale_unrounded = (float)$new_reg * ( 1 - $discount_pct );
                    $new_sale = $apply_rounding( $new_sale_unrounded );

                    
                    if ( $new_sale === null || $new_sale >= $new_reg ) {
                        $fallback_sale = $floor_below_regular( $new_reg );
                        $new_sale = $fallback_sale;
                    }

                    // Set if changed
                    if ( (string)$product->get_sale_price() !== (string)$new_sale ) {
                        $product->set_sale_price( $new_sale );
                        $product_changed = true;
                    }

                } elseif ( $sale_raw !== '' && is_numeric( $sale_raw ) ) {
                    // If original regular was invalid, use absolute difference
                    $orig_sale = (float)$sale_raw;
                    $orig_reg = is_numeric( $reg_raw ) ? (float)$reg_raw : null;
                    if ( $orig_reg !== null ) {
                        $diff = max(0, $orig_reg - $orig_sale);
                        $new_sale_unrounded = max(0, $new_reg - $diff);
                        $new_sale = $apply_rounding( $new_sale_unrounded );
                        if ( $new_sale === null || $new_sale >= $new_reg ) {
                            $new_sale = $floor_below_regular( $new_reg );
                        }
                        if ( (string)$product->get_sale_price() !== (string)$new_sale ) {
                            $product->set_sale_price( $new_sale );
                            $product_changed = true;
                        }
                    }
                }
                // If sale_raw == '' do nothing (since there is no sale)
            } else {
                // saleprice = false => usually don’t touch sale only if sale >= new regular, move it one step below to avoid deletion
                if ( $sale_raw !== '' && is_numeric( $sale_raw ) && (float)$sale_raw >= (float)$new_reg ) {
                    $new_sale = $floor_below_regular( $new_reg );
                    if ( (string)$product->get_sale_price() !== (string)$new_sale ) {
                        $product->set_sale_price( $new_sale );
                        $product_changed = true;
                    }
                }
            }

            if ( $product_changed ) {
                $product->save();
                $changed++;
            }
        }

        // --- Variable products ---
        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $child_id ) {
                $variation = wc_get_product( $child_id );
                if ( ! $variation ) continue;

                $reg_raw  = $variation->get_regular_price();
                $sale_raw = $variation->get_sale_price();

                $new_reg = $calculate_new_price( $reg_raw );
                if ( $new_reg === null ) {
                    continue;
                }

                $variation_changed = false;
                if ( (string)$variation->get_regular_price() !== (string)$new_reg ) {
                    $variation->set_regular_price( $new_reg );
                    $variation_changed = true;
                }

                if ( $saleprice ) {
                    if ( $sale_raw !== '' && is_numeric( $sale_raw ) && is_numeric( $reg_raw ) && (float)$reg_raw > 0 ) {
                        $orig_reg = (float)$reg_raw;
                        $orig_sale = (float)$sale_raw;
                        $discount_pct = ( $orig_reg - $orig_sale ) / $orig_reg;

                        $new_sale_unrounded = (float)$new_reg * ( 1 - $discount_pct );
                        $new_sale = $apply_rounding( $new_sale_unrounded );

                        if ( $new_sale === null || $new_sale >= $new_reg ) {
                            $new_sale = $floor_below_regular( $new_reg );
                        }

                        if ( (string)$variation->get_sale_price() !== (string)$new_sale ) {
                            $variation->set_sale_price( $new_sale );
                            $variation_changed = true;
                        }

                    } elseif ( $sale_raw !== '' && is_numeric( $sale_raw ) ) {
                        $orig_sale = (float)$sale_raw;
                        $orig_reg = is_numeric( $reg_raw ) ? (float)$reg_raw : null;
                        if ( $orig_reg !== null ) {
                            $diff = max(0, $orig_reg - $orig_sale);
                            $new_sale_unrounded = max(0, $new_reg - $diff);
                            $new_sale = $apply_rounding( $new_sale_unrounded );
                            if ( $new_sale === null || $new_sale >= $new_reg ) {
                                $new_sale = $floor_below_regular( $new_reg );
                            }
                            if ( (string)$variation->get_sale_price() !== (string)$new_sale ) {
                                $variation->set_sale_price( $new_sale );
                                $variation_changed = true;
                            }
                        }
                    }
                } else {
                    if ( $sale_raw !== '' && is_numeric( $sale_raw ) && (float)$sale_raw >= (float)$new_reg ) {
                        $new_sale = $floor_below_regular( $new_reg );
                        if ( (string)$variation->get_sale_price() !== (string)$new_sale ) {
                            $variation->set_sale_price( $new_sale );
                            $variation_changed = true;
                        }
                    }
                }

                if ( $variation_changed ) {
                    $variation->save();
                    $changed++;
                }
            }
        }
    } // end foreach products

    // Update lookup tables (once)
    if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
        wc_update_product_lookup_tables();
    }

    // Calculate remaining/next
    $next_offset = $offset + $limit;
    $remaining   = max( 0, $total_count - $next_offset );

    wp_send_json([
        'status'      => 'success',
        'action'      => $action,
        'percent'     => $percent,
        'round'       => $round ?: 'none',
        'roundbase'   => $roundbase,
        'saleprice'   => $saleprice ? 'true' : 'false',
        'product'     => $productType,
        'changed'     => $changed,
        'processed'   => $offset . ' - ' . ($offset + $processed),
        'total'       => $total_count,
        'remaining'   => $remaining,
        'next_offset' => $remaining > 0 ? $next_offset : null,
    ]);
});
