<?php

class ModelExtensionMtJetCreditCheckoutFinancing extends Model {
	public function getContext($channel) {
		$session = $this->session->data;
		if (!isset($session['currency']) || $session['currency'] !== 'EUR') {
			return array('reason' => 'invalid_currency');
		}
		if (($channel !== 'jet' && $channel !== 'jet_card') || empty($session['payment_method']['code'])
			|| $session['payment_method']['code'] !== $channel) {
			return array('reason' => 'payment_method_changed');
		}
		if (empty($session['order_id']) || empty($session['payment_address'])) {
			return array('reason' => 'stale_order');
		}

		$orderId = (int)$session['order_id'];
		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($orderId);
		if (!$order || (int)$order['order_status_id'] !== 0 || $order['payment_code'] !== $channel
			|| $order['currency_code'] !== 'EUR'
			|| number_format((float)$order['currency_value'], 8, '.', '') !== number_format($this->currency->getValue('EUR'), 8, '.', '')
			|| (int)$order['store_id'] !== (int)$this->config->get('config_store_id')
			|| (int)$order['customer_id'] !== (int)$this->customer->getId()
			|| !$this->matchesAddress($order, 'payment', $session['payment_address'])) {
			return array('reason' => 'stale_order');
		}

		$shipping = $this->cart->hasShipping();
		if ($shipping && (empty($session['shipping_method']['code']) || empty($session['shipping_address'])
			|| $order['shipping_code'] !== $session['shipping_method']['code']
			|| !$this->matchesAddress($order, 'shipping', $session['shipping_address']))) {
			return array('reason' => 'stale_order');
		}
		if (!$shipping && $order['shipping_code'] !== '') {
			return array('reason' => 'stale_order');
		}

		$this->load->model('extension/mt_jet_credit/cart_financing');
		$snapshot = $this->model_extension_mt_jet_credit_cart_financing->getSnapshot(true);
		if (!$snapshot['lines'] || $this->decimal($order['total']) !== $this->decimal($snapshot['base_total'])) {
			return array('reason' => 'stale_order');
		}

		$this->load->model('extension/payment/' . $channel);
		$method = $this->{'model_extension_payment_' . $channel};
		if (!$method->isAvailable($session['payment_address'], $snapshot['base_total'])) {
			return array('reason' => 'channel_disabled');
		}

		// OpenCart 2.3 checkout/order exposes getOrder() but no readers for these child rows.
		$products = $this->db->query("SELECT order_product_id, product_id, quantity, price, total, tax FROM " .
			DB_PREFIX . "order_product WHERE order_id = " . $orderId)->rows;
		$options = $this->db->query("SELECT order_product_id, product_option_id, product_option_value_id, name, value, type FROM " .
			DB_PREFIX . "order_option WHERE order_id = " . $orderId)->rows;
		$totals = $this->db->query("SELECT code, title, value, sort_order FROM " .
			DB_PREFIX . "order_total WHERE order_id = " . $orderId)->rows;
		$optionsByProduct = array();
		foreach ($options as $option) {
			$optionsByProduct[$option['order_product_id']][] = $option;
		}
		$storedLines = $liveLines = array();
		foreach ($products as $product) {
			$product['option'] = isset($optionsByProduct[$product['order_product_id']])
				? $optionsByProduct[$product['order_product_id']] : array();
			$storedLines[] = $this->line($product, $product['price'], $product['total'], $product['tax']);
		}
		foreach ($snapshot['lines'] as $product) {
			$liveLines[] = $this->line($product, $product['base_price'], $product['base_line_total'], $product['base_tax']);
		}
		sort($storedLines, SORT_STRING);
		sort($liveLines, SORT_STRING);
		if ($liveLines !== $storedLines || $this->totals($totals) !== $this->totals($snapshot['total_components'])) {
			return array('reason' => 'stale_order');
		}

		return array(
			'reason' => null,
			'snapshot' => $snapshot,
			'order' => $order,
			'context' => hash('sha256', serialize(array($orderId, $channel, $snapshot, $order)))
		);
	}

	public function serializeProducts(array $lines) {
		$ids = $quantities = $prices = $variants = array();
		foreach ($lines as $line) {
			$ids[] = $line['product_id'];
			$quantities[] = $line['quantity'];
			$prices[] = number_format($line['price'], 2, '.', '');
			$values = array();
			foreach ($line['option'] as $option) {
				$values[] = $option['value'];
			}
			$variants[] = implode(', ', $values);
		}
		return array('products' => implode('_', $ids), 'products_qt' => implode('_', $quantities),
			'products_pr' => implode('_', $prices), 'products_vr' => implode('_', $variants));
	}

	private function matchesAddress(array $order, $prefix, array $address) {
		foreach (array('firstname', 'lastname', 'company', 'address_1', 'address_2', 'city', 'postcode', 'zone_id', 'country_id') as $key) {
			if ((string)$order[$prefix . '_' . $key] !== (string)(isset($address[$key]) ? $address[$key] : '')) {
				return false;
			}
		}
		return true;
	}

	private function line(array $product, $price, $total, $tax) {
		$options = array();
		foreach ($product['option'] as $option) {
			$options[] = serialize(array((int)$option['product_option_id'], (int)$option['product_option_value_id'],
				(string)$option['name'], (string)$option['value'], (string)$option['type']));
		}
		sort($options, SORT_STRING);
		return serialize(array((int)$product['product_id'], (int)$product['quantity'],
			$this->decimal($price), $this->decimal($total), $this->decimal($tax), $options));
	}

	private function totals(array $rows) {
		$values = array();
		foreach ($rows as $row) {
			$values[] = serialize(array($row['code'], $row['title'], $this->decimal($row['value']), (int)$row['sort_order']));
		}
		sort($values, SORT_STRING);
		return $values;
	}

	private function decimal($value) {
		return number_format((float)$value, 4, '.', '');
	}
}
