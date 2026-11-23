<?php

namespace JetFinancialBaseline;

require __DIR__ . '/financial-baseline.php';
require dirname(__DIR__) . '/upload/system/library/jet_cart_eligibility.php';

function snapshot(array $productIds = array(101, 102)) {
    $lines = array();
    foreach ($productIds as $index => $id) {
        $lines[] = array('product_id' => $id, 'cart_id' => $index + 1, 'quantity' => $index + 1,
            'option' => array(array('product_option_id' => 9, 'value' => $index ? 'Blue' : 'Red')));
    }
    return array('currency' => 'EUR', 'cart_total' => '450.00', 'principal' => '400.00',
        'lines' => $lines, 'enabled_channels' => array('jet' => true, 'jet_card' => true),
        'minimum_totals' => array('jet' => '100', 'jet_card' => '100'),
        'standard_rates' => array('jet' => '0.80', 'jet_card' => '0.90'));
}

function resolver(array $rows, &$db = null) {
    $db = new PromotionDatabase($rows);
    $model = new \ModelExtensionMtJetCreditJetcredit(array('db' => $db));
    return new \JetCartEligibility(array($model, 'getCartPromotionRate'));
}

function verify($name, $expected, $actual) {
    if ($expected !== $actual) {
        throw new \RuntimeException($name . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
    $GLOBALS['jet_resolver_checks']++;
}

$GLOBALS['jet_resolver_checks'] = 0;
$standard = resolver(array());
$single = snapshot(array(101));
verify('Single standard JET', '0.8', $standard->explicit($single, 'jet', 12, '50.00')['rate']);
verify('Single standard Card', '0.9', $standard->explicit($single, 'jet_card', 12, '50.00')['rate']);
$cart = snapshot();
$before = serialize($cart);
$initial = $standard->initial($cart);
verify('Equal standard rates, ascending initial results', array(3, 6, 9, 10, 12, 15, 18, 24),
    array_column($initial['jet']['eligible_terms'], 'term'));
verify('Initial selection', 3, $initial['jet']['selected']['term']);
verify('Initial principal comes from snapshot', 400.0, $initial['jet']['selected']['principal']);

$mixed = resolver(array(promotion(1, 101, '0.80', '3_12')));
$result = $mixed->initial($cart);
verify('Equal mixed JET rate', '0.8', $result['jet']['selected']['rate']);
verify('First JET term', 3, $result['jet']['selected']['term']);
verify('Different first Card term', 6, $result['jet_card']['selected']['term']);
verify('Unequal mixed Card rate', 'rate_mismatch', $mixed->explicit($cart, 'jet_card', 12, 50)['reason']);
verify('Explicit user term retained',
    array('eligible' => false, 'term' => '12', 'reason' => 'rate_mismatch', 'rate' => null, 'principal' => null),
    $mixed->explicit($cart, 'jet_card', '12', '50'));
verify('Explicit valid exact term',
    array('eligible' => true, 'term' => '12', 'reason' => null, 'rate' => '0.8', 'principal' => 400.0),
    $mixed->explicit($cart, 'jet', '12', '50'));
verify('Snapshot untouched by initial and explicit modes', $before, serialize($cart));

$equal = resolver(array(promotion(1, 101, '1.20'), promotion(2, 102, '1.20')));
verify('Equal exact promotions', '1.2', $equal->explicit($cart, 'jet_card', 12, 50)['rate']);
$different = resolver(array(promotion(1, 101, '1.20'), promotion(2, 102, '1.21')));
verify('Unequal exact promotions', 'rate_mismatch', $different->explicit($cart, 'jet', 12, 50)['reason']);
$precise = $cart;
$precise['standard_rates']['jet'] = '0.80000000000000000001';
verify('Additional configured precision survives float collision', 'rate_mismatch',
    $mixed->explicit($precise, 'jet', 12, 50)['reason']);
$precise['standard_rates']['jet'] = '+00.8000';
verify('Numerically equal formatted rate', '0.8', $mixed->explicit($precise, 'jet', 12, 50)['rate']);
$precise['standard_rates']['jet'] = '0.0000';
verify('Zero is a valid standard rate', '0', $standard->explicit($precise, 'jet', 12, 50)['rate']);
$precise['standard_rates']['jet'] = 'NaN';
verify('Malformed standard rate', 'invalid_rate', $standard->explicit($precise, 'jet', 12, 50)['reason']);

$threshold = resolver(array(
    promotion(1, '*', '1.10'),
    promotion(8, 101, '0.50', '12', '400.00'),
    promotion(4, 101, '0.00', '12', '400.00', '2026-11-23', '2026-11-23'),
    promotion(2, 102, '0.00', '12', '0.00', '2026-01-01', '2026-11-22'),
    promotion(3, 103, '0.00', '12', '0.00', '2026-11-24')
), $db);
verify('Exact over global, first ID, inclusive date and amount', '0',
    $threshold->explicit($single, 'jet', 12, '50.00')['rate']);
verify('Changed down payment crosses principal threshold', '1.1',
    $threshold->explicit($single, 'jet', 12, '50.01')['rate']);
verify('Expired exact falls back globally', '1.1',
    $threshold->explicit(snapshot(array(102)), 'jet_card', 12, 50)['rate']);
verify('Future exact falls back globally', '1.1',
    $threshold->explicit(snapshot(array(103)), 'jet_card', 12, 50)['rate']);
verify('Unlisted term falls back to standard', '0.9', $threshold->explicit($single, 'jet_card', 10, 50)['rate']);
$repeated = snapshot(array(101, 101));
$db->lookups = array();
verify('Different quantity and options retain base ID', '0', $threshold->explicit($repeated, 'jet', 12, 50)['rate']);
verify('Repeated ID reuses lookup against aggregate principal', array(array('101', 12, 400.0)), $db->lookups);
$db->lookups = array();
$threshold->explicit($cart, 'jet', 12, 50);
verify('Distinct base IDs and same principal', array(array('101', 12, 400.0), array('102', 12, 400.0)), $db->lookups);

$prohibited = resolver(array(promotion(1, 101, '0.80'), promotion(2, 102, '-1.00')));
foreach (array('jet', 'jet_card') as $channel) {
    verify('Exact prohibition ' . $channel, 'term_prohibited', $prohibited->explicit($cart, $channel, 12, 50)['reason']);
}
$lateProhibition = resolver(array(promotion(1, 101, '0.80'), promotion(2, 102, '0.90'), promotion(3, 103, '-1.00')));
verify('Prohibition takes precedence over a rate mismatch', 'term_prohibited',
    $lateProhibition->explicit(snapshot(array(101, 102, 103)), 'jet', 12, 50)['reason']);
$noTerms = resolver(array(promotion(1, '*', '-1.00', '3_6_9_10_12_15_18_24_30_36')));
verify('Global prohibition produces no initial scheme',
    array('eligible_terms' => array(), 'selected' => null, 'reason' => 'no_eligible_term'),
    $noTerms->initial($cart)['jet']);

foreach (array(15 => 401, 18 => 401, 24 => 401, 30 => 601, 36 => 601) as $term => $boundary) {
    $amount = $cart;
    foreach (array('-0.01' => false, '-0.001' => false, '0' => true, '0.01' => true) as $delta => $eligible) {
        $amount['cart_total'] = $boundary + $delta;
        verify('Unrounded amount boundary ' . $term . ' / ' . $delta, $eligible,
            $standard->explicit($amount, 'jet', $term, 50)['eligible']);
        verify('Initial amount boundary ' . $term . ' / ' . $delta, $eligible,
            in_array($term, array_column($standard->initial($amount)['jet']['eligible_terms'], 'term'), true));
    }
}

$small = $cart;
$small['cart_total'] = '110';
$small['principal'] = '40';
verify('Historical 125 EUR default does not override first rate-eligible term', 3,
    $standard->initial($small)['jet']['selected']['term']);
verify('Monthly installment floor is separate from rate eligibility', true,
    $standard->explicit($small, 'jet', 3, '100')['eligible']);

foreach (array(
    array('currency', 'BGN', 'invalid_currency'),
    array('lines', array(), 'empty_cart'),
    array('enabled_channels', array('jet' => false, 'jet_card' => true), 'channel_disabled'),
    array('cart_total', '99.99', 'amount_out_of_range'),
    array('cart_total', INF, 'amount_out_of_range'),
    array('cart_total', NAN, 'amount_out_of_range'),
    array('cart_total', '1e309', 'amount_out_of_range'),
    array('cart_total', 'EUR 450', 'amount_out_of_range')
) as $case) {
    list($key, $value, $reason) = $case;
    $invalid = $cart;
    $invalid[$key] = $value;
    verify($key . ' explicit ' . var_export($value, true), $reason, $standard->explicit($invalid, 'jet', 12, 0)['reason']);
    verify($key . ' initial ' . var_export($value, true), $reason, $standard->initial($invalid)['jet']['reason']);
}
verify('Unknown channel', 'invalid_channel', $standard->explicit($cart, 'foo', 12, 0)['reason']);
$disabled = $cart;
$disabled['enabled_channels']['jet'] = false;
verify('Disabled JET does not disable Card', true, $standard->explicit($disabled, 'jet_card', 12, 50)['eligible']);
foreach (array(5, 12.5, '12x', '12.0', array(12), true) as $term) {
    verify('Unsupported or malformed term', 'term_not_allowed', $standard->explicit($cart, 'jet', $term, 0)['reason']);
}
foreach (array(-1, 450, 451, '0.001', '50.00000000000000001', '1,00', '1e1', '1e309', INF, NAN, array(), false) as $down) {
    verify('Invalid down payment ' . var_export($down, true), 'invalid_down_payment',
        $standard->explicit($cart, 'jet', 12, $down)['reason']);
}
foreach (array(0, INF, '450.01', '399.999') as $principal) {
    $invalid = $cart;
    $invalid['principal'] = $principal;
    verify('Invalid initial principal ' . var_export($principal, true), 'amount_out_of_range',
        $standard->initial($invalid)['jet']['reason']);
}
verify('Valid quote principal', 399.99, $threshold->explicit($single, 'jet', 12, '50.01')['principal']);
foreach (array('401.005' => 401.00, '401.125' => 401.13, '400.995' => 401.00) as $total => $principal) {
    $subCent = $cart;
    $subCent['cart_total'] = $total;
    verify('Legacy toFixed(2) quote total ' . $total, $principal,
        $standard->explicit($subCent, 'jet', 12, '0.00')['principal']);
}

// Exercise the unchanged calculator with rates selected by the new resolver.
$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/financial-baseline.json'), true);
foreach (array(
    array('cart_standard', $standard, 10),
    array('cart_exact_zero_at_threshold', $threshold, 12)
) as $case) {
    list($name, $lookup, $term) = $case;
    $jet = $lookup->explicit($single, 'jet', $term, '50.00');
    $card = $lookup->explicit($single, 'jet_card', $term, '50.00');
    foreach (array($jet, $card) as $result) {
        verify('Finite shared rate ' . $name, true, is_finite((float)$result['rate']));
        verify('Finite principal ' . $name, true, is_finite($result['principal']));
    }
    $ctrl = controller(__NAMESPACE__ . '\CartController', config($jet['rate'], $card['rate']),
        new PromotionDatabase(array()), array('jet_products' => '101', 'jet_priceall' => '450.00',
            'jet_parva' => '50.00', 'jet_vnoski' => (string)$term));
    $ctrl->jet_calculate_cart();
    verify('Unchanged calculator output ' . $name, $fixture['finance'][$name], $ctrl->response->output);
    foreach ($ctrl->response->output as $field => $value) {
        if ($field !== 'success') {
            verify('Finite calculator field ' . $name . ' / ' . $field, true, is_numeric($value) && is_finite((float)$value));
        }
    }
}
printf("%d Cart eligibility and financial compatibility checks passed.\n", $GLOBALS['jet_resolver_checks']);
