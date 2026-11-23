<?php

require_once DIR_SYSTEM . 'library/jet_cart_eligibility.php';

class ModelExtensionMtJetCreditCartFinancing extends Model {
	private $checkoutOffers = array();

	public function getCheckoutOffers($total, $evaluate) {
		$key = serialize($total);
		if (!isset($this->checkoutOffers[$key])) {
			$this->checkoutOffers[$key] = call_user_func($evaluate, $this->getSnapshot(true, $total));
		}
		return $this->checkoutOffers[$key];
	}

	public function getSnapshot($checkout = false, $checkoutTotal = null) {
		$currency = isset($this->session->data['currency']) ? $this->session->data['currency'] : null;
		$enabled = $this->config->get('mt_jet_credit_status') && $this->config->get('mt_jet_credit_cart_show');
		$minimum = (float)$this->config->get('mt_jet_credit_price');
		$snapshot = array(
			'currency' => $currency,
			'lines' => array(),
			'cart_total' => 0.0,
			'principal' => 0.0,
			'total_components' => array(),
			'enabled_channels' => array('jet' => $enabled, 'jet_card' => $enabled && $this->config->get('mt_jet_credit_status_card')),
			'minimum_totals' => array('jet' => $minimum, 'jet_card' => $minimum),
			'standard_rates' => array(
				'jet' => $this->standardRate('mt_jet_credit_purcent'),
				'jet_card' => $this->standardRate('mt_jet_credit_purcent_card')
			)
		);

		if ($checkout) {
			$enabled = $this->config->get('mt_jet_credit_status') == 1;
			$snapshot['enabled_channels'] = array(
				'jet' => $enabled && $this->config->get('jet_status') == 1,
				'jet_card' => $enabled && $this->config->get('jet_card_status') == 1
			);
			// The payment models apply their existing minimums in store currency.
			$snapshot['minimum_totals'] = array('jet' => 0, 'jet_card' => 0);
			$snapshot['base_total'] = 0;
		}

		if ($currency !== 'EUR') {
			return $snapshot;
		}

		$showPrices = $enabled && ($checkout || $this->customer->isLogged() || !$this->config->get('config_customer_price'));
		foreach ($this->cart->getProducts() as $product) {
			$snapshot['lines'][] = array(
				'product_id' => $product['product_id'],
				'cart_id' => $product['cart_id'],
				'quantity' => $product['quantity'],
				'option' => $product['option'],
				'price' => $showPrices ? $this->toEuros($this->tax->calculate(
					$product['price'], $product['tax_class_id'], $this->config->get('config_tax')
				)) : 0.0
			);
			if ($checkout && $checkoutTotal === null) {
				$index = count($snapshot['lines']) - 1;
				$snapshot['lines'][$index]['base_price'] = $product['price'];
				$snapshot['lines'][$index]['base_line_total'] = $product['total'];
				$snapshot['lines'][$index]['base_tax'] = $this->tax->getTax($product['price'], $product['tax_class_id']);
			}
		}

		if ($snapshot['lines'] && $showPrices) {
			$totals = $checkoutTotal === null ? $this->calculateTotals()
				: array('amount' => $checkoutTotal, 'components' => array());
			if ($checkout) {
				$snapshot['base_total'] = $totals['amount'];
			}
			$snapshot['cart_total'] = $this->toEuros($totals['amount']);
			$snapshot['total_components'] = $totals['components'];
			if (is_finite($snapshot['cart_total']) && $snapshot['cart_total'] > 0) {
				$snapshot['principal'] = JetCartEligibility::quoteTotal($snapshot['cart_total']);
			}
		}

		return $snapshot;
	}

	public function serializeProducts(array $lines) {
		$ids = $quantities = $prices = $options = array();
		foreach ($lines as $index => $line) {
			$ids[] = $line['product_id'];
			$quantities[] = $line['quantity'];
			$prices[] = (string)$line['price'];
			foreach ($line['option'] as $key => $option) {
				$options[$index][$key] = array_intersect_key($option, array_flip(array(
					'product_option_id', 'product_option_value_id', 'option_id',
					'option_value_id', 'name', 'value', 'type'
				)));
			}
		}

		return array(
			'jet_products' => implode('_', $ids),
			'jet_products_qt' => implode('_', $quantities),
			'jet_products_pr' => implode('_', $prices),
			'jet_products_vr' => json_encode($options)
		);
	}

	private function standardRate($key) {
		$rate = (string)$this->config->get($key);
		return $rate === '' ? '0' : $rate;
	}

	private function toEuros($amount) {
		$base = $this->config->get('config_currency');
		return $base && $base !== 'EUR' ? (float)$this->currency->convert($amount, $base, 'EUR') : (float)$amount;
	}

	private function calculateTotals() {
		$this->load->model('extension/extension');
		$extensions = $this->model_extension_extension->getExtensions('total');
		$sortOrder = array();
		foreach ($extensions as $key => $extension) {
			$sortOrder[$key] = $this->config->get($extension['code'] . '_sort_order');
		}
		array_multisort($sortOrder, SORT_ASC, $extensions);

		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;
		// OpenCart 2.3 passes references inside the array through model proxies.
		$totalData = array('totals' => &$totals, 'taxes' => &$taxes, 'total' => &$total);
		foreach ($extensions as $extension) {
			$code = $extension['code'];
			if ($this->config->get($code . '_status')) {
				$this->load->model('extension/total/' . $code);
				$this->{'model_extension_total_' . $code}->getTotal($totalData);
			}
		}

		return array('amount' => $total, 'components' => $totals);
	}
}
