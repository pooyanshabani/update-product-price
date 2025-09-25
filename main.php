

add_action( 'template_redirect', function() {

// If Admin is login
if ( ! is_user_logged_in() || ! current_user_can( 'administrator' ) ) {
    return;
}

$percent     = 0;
$action      = '';
$round       = isset($_GET['round']) ? sanitize_text_field($_GET['round']) : '';
$roundbase   = isset($_GET['roundbase']) ? intval($_GET['roundbase']) : 1000; // پیش‌فرض هزار تومانی
$saleprice   = ( isset($_GET['saleprice']) && $_GET['saleprice'] === 'true' );
$productType = isset($_GET['product']) ? sanitize_text_field($_GET['product']) : 'all'; // پیش‌فرض all

$offset      = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit       = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

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

// Round & Calculate New Price
$calculate_new_price = function( $price ) use ( $action, $percent, $round, $roundbase ) {
    if ($price === '' || !is_numeric($price)) {
        return null;
    }
    $price = (float) $price;

    $new_price = $action === 'increase'
        ? $price * ( 1 + $percent / 100 )
        : $price * ( 1 - $percent / 100 );

    $new_price = max( 0, $new_price );

    // Roundbase
    if ( $round === 'up' ) {
        $new_price = ceil( $new_price / $roundbase ) * $roundbase;
    } elseif ( $round === 'down' ) {
        $new_price = floor( $new_price / $roundbase ) * $roundbase;
    }

    return $new_price;
};

// Count all products
$total_products = wc_get_products([
    'status' => 'publish',
    'limit'  => -1,
    'return' => 'ids',
]);
$total_count = count($total_products);

// Get product by offset & limit
$args = [
    'status' => 'publish',
    'limit'  => $limit,
    'offset' => $offset,
    'return' => 'ids',
];
$products = wc_get_products( $args );

$changed = 0;

foreach ( $products as $product_id ) {
    $product = wc_get_product( $product_id );

    // Filter product type
    if ( $productType === 'simple' && ! $product->is_type( 'simple' ) ) {
        continue;
    }
    if ( $productType === 'variable' && ! $product->is_type( 'variable' ) ) {
        continue;
    }

    // --- simple product ---
    if ( $product->is_type( 'simple' ) ) {
        $reg_raw  = $product->get_regular_price();
        $sale_raw = $product->get_sale_price();

        $new_reg = $calculate_new_price( $reg_raw );
        if ( $new_reg !== null ) {
            $product->set_regular_price( $new_reg );

            if ( $saleprice ) {
                if ( $sale_raw !== '' ) {
                    $new_sale = $calculate_new_price( $sale_raw );
                    if ( $new_sale !== null ) {
                        $product->set_sale_price( $new_sale );
                    }
                }
            } else {
                if ( $sale_raw !== '' && (float)$sale_raw > (float)$new_reg ) {
                    $product->set_sale_price( $new_reg );
                }
            }

            $product->save();
            $changed++;
        }
    }

    // --- variable product ---
    if ( $product->is_type( 'variable' ) ) {
        foreach ( $product->get_children() as $child_id ) {
            $variation = wc_get_product( $child_id );

            $reg_raw  = $variation->get_regular_price();
            $sale_raw = $variation->get_sale_price();

            $new_reg = $calculate_new_price( $reg_raw );
            if ( $new_reg === null ) {
                continue;
            }

            $variation->set_regular_price( $new_reg );

            if ( $saleprice ) {
                if ( $sale_raw !== '' ) {
                    $new_sale = $calculate_new_price( $sale_raw );
                    if ( $new_sale !== null ) {
                        $variation->set_sale_price( $new_sale );
                    }
                }
            } else {
                if ( $sale_raw !== '' && (float)$sale_raw > (float)$new_reg ) {
                    $variation->set_sale_price( $new_reg );
                }
            }

            $variation->save();
            $changed++;
        }
    }
}

// Update table
if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
    wc_update_product_lookup_tables();
}

// Calculate Remainder
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
    'processed'   => $offset . ' - ' . ($offset + count($products)),
    'total'       => $total_count,
    'remaining'   => $remaining,
    'next_offset' => $remaining > 0 ? $next_offset : null,
]);
});
