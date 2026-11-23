<?php

/**
 * Rates-only eligibility for a normalized, server-derived Cart or Checkout snapshot.
 *
 * The snapshot contains currency, cart_total (before display rounding), principal
 * (at currency precision), lines (product_id, cart_id, option, quantity), and maps
 * enabled_channels, minimum_totals and standard_rates keyed by jet / jet_card.
 * Keep rates as their original decimal strings. Line metadata stays with the caller;
 * only base product IDs are used in the promotion lookup.
 *
 * The injected promotion context is a read-only lookup for this store/request.
 * It receives (product ID, term, aggregate principal) and returns the applicable
 * stored decimal rate or null for standard fallback. See getCartPromotionRate().
 * Callers apply the separate monthly installment floor using the existing calculator.
 */
class JetCartEligibility {
	private $promotionLookup;
	private $terms = array(3, 6, 9, 10, 12, 15, 18, 24, 30, 36);

	public function __construct(callable $promotionLookup) {
		$this->promotionLookup = $promotionLookup;
	}

	public function initial(array $snapshot) {
		$results = array();
		$promotions = array();
		$total = isset($snapshot['cart_total']) ? $this->amount($snapshot['cart_total']) : null;
		$principal = isset($snapshot['principal']) ? $this->amount($snapshot['principal']) : null;

		foreach (array('jet', 'jet_card') as $channel) {
			$reason = $this->contextReason($snapshot, $channel, $total);
			if ($reason === null && ($principal === null || $principal <= 0
				|| $principal !== round($principal, 2) || $principal > self::quoteTotal($total))) {
				$reason = 'amount_out_of_range';
			}

			$eligible = array();
			if ($reason === null) {
				foreach ($this->terms as $term) {
					if ($this->isAllowedTerm($term, $total)) {
						$result = $this->resolveTerm($snapshot, $channel, $term, $principal, $promotions);
						if ($result['eligible']) {
							$eligible[] = $result;
						}
					}
				}
				if (!$eligible) {
					$reason = 'no_eligible_term';
				}
			}

			$results[$channel] = array(
				'eligible_terms' => $eligible,
				'selected' => $eligible ? $eligible[0] : null,
				'reason' => $reason
			);
		}

		return $results;
	}

	public function explicit(array $snapshot, $channel, $term, $downPayment) {
		$total = isset($snapshot['cart_total']) ? $this->amount($snapshot['cart_total']) : null;
		$reason = $this->contextReason($snapshot, $channel, $total);
		if ($reason !== null) {
			return $this->result($term, $reason);
		}

		if ((!is_int($term) && (!is_string($term) || !preg_match('/^[1-9][0-9]?$/D', $term)))
			|| !$this->isAllowedTerm((int)$term, $total)) {
			return $this->result($term, 'term_not_allowed');
		}

		$down = is_string($downPayment) && !preg_match('/^[-+]?(?:[0-9]+(?:\.[0-9]{1,2}0*)?|\.[0-9]{1,2}0*)$/D', $downPayment)
			? null : $this->amount($downPayment);
		$quoteTotal = self::quoteTotal($total);
		if ($down === null || $down < 0 || $down >= $total || $down >= $quoteTotal || $down !== round($down, 2)) {
			return $this->result($term, 'invalid_down_payment');
		}

		// A fresh explicit selection supersedes the principal of the initial snapshot.
		$principal = round($quoteTotal - $down, 2);
		$promotions = array();
		return $this->resolveTerm($snapshot, $channel, $term, $principal, $promotions);
	}

	private function contextReason(array $snapshot, $channel, $total) {
		if ($channel !== 'jet' && $channel !== 'jet_card') {
			return 'invalid_channel';
		}
		if (!isset($snapshot['currency']) || $snapshot['currency'] !== 'EUR') {
			return 'invalid_currency';
		}
		if (empty($snapshot['lines'])) {
			return 'empty_cart';
		}
		if (empty($snapshot['enabled_channels'][$channel])) {
			return 'channel_disabled';
		}

		$minimum = isset($snapshot['minimum_totals'][$channel])
			? $this->amount($snapshot['minimum_totals'][$channel]) : null;
		if ($minimum === null || $minimum < 0 || $total === null || $total <= 0 || $total < $minimum) {
			return 'amount_out_of_range';
		}
		return null;
	}

	private function isAllowedTerm($term, $total) {
		if (in_array($term, array(15, 18, 24), true)) {
			return $total >= 401;
		}
		if (in_array($term, array(30, 36), true)) {
			return $total >= 601;
		}
		return in_array($term, $this->terms, true);
	}

	public static function quoteTotal($total) {
		// Match the legacy client's toFixed(2) on positive totals. printf rounds
		// exact binary half-cent ties to even, whereas toFixed rounds them up.
		return fmod($total, 0.25) === 0.125 ? round($total, 2) : (float)sprintf('%.2F', $total);
	}

	private function resolveTerm(array $snapshot, $channel, $term, $principal, array &$promotions) {
		$standard = isset($snapshot['standard_rates'][$channel])
			? $this->decimalRate($snapshot['standard_rates'][$channel]) : null;
		$shared = null;
		$reason = null;

		foreach ($snapshot['lines'] as $line) {
			$productId = $line['product_id'];
			if (!isset($promotions[$term][$productId])) {
				$raw = call_user_func($this->promotionLookup, $productId, (int)$term, $principal);
				$promotions[$term][$productId] = array(
					'matched' => $raw !== null,
					'rate' => $this->decimalRate($raw)
				);
			}
			$promotion = $promotions[$term][$productId];
			if ($promotion['rate'] === '-1') {
				return $this->result($term, 'term_prohibited');
			}
			$rate = $promotion['matched'] ? $promotion['rate'] : $standard;
			if ($rate === null) {
				$reason = 'invalid_rate';
			} elseif ($shared === null) {
				$shared = $rate;
			} elseif ($rate !== $shared && $reason === null) {
				$reason = 'rate_mismatch';
			}
		}

		return $this->result($term, $reason, $shared, $principal);
	}

	private function amount($value) {
		if (!(is_int($value) || is_float($value) || is_string($value)) || !is_numeric($value)) {
			return null;
		}
		$number = (float)$value;
		return is_finite($number) ? $number : null;
	}

	// Strip presentation zeros without rounding DB DECIMAL or configured rate strings.
	private function decimalRate($value) {
		if ((!is_string($value) && !is_int($value))
			|| !preg_match('/^([+-]?)([0-9]*)(?:\.([0-9]+))?$/D', (string)$value, $parts)
			|| ($parts[2] === '' && !isset($parts[3])) || !is_finite((float)$value)) {
			return null;
		}
		$whole = ltrim($parts[2], '0');
		$fraction = isset($parts[3]) ? rtrim($parts[3], '0') : '';
		$sign = ($whole !== '' || $fraction !== '') && $parts[1] === '-' ? '-' : '';
		return $sign . ($whole === '' ? '0' : $whole) . ($fraction === '' ? '' : '.' . $fraction);
	}

	private function result($term, $reason, $rate = null, $principal = null) {
		return array(
			'eligible' => $reason === null,
			'term' => $term,
			'reason' => $reason,
			'rate' => $reason === null ? $rate : null,
			'principal' => $reason === null ? $principal : null
		);
	}
}
