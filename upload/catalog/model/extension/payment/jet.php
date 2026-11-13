<?php
class ModelExtensionPaymentJet extends Model {

	public function getMethod($address, $total) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('jet_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
		if ($this->config->get('jet_total') > 0 && $this->config->get('jet_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('jet_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$jet_status         = $this->config->get('mt_jet_credit_status');
		$jet_payment_status = $this->config->get('jet_status');
		$jet_currency_code  = $this->session->data['currency'];
		if ($jet_status == 1 & $jet_payment_status == 1 & ($jet_currency_code == 'EUR' || $jet_currency_code == 'BGN')) {
			$jet_minstojnost = (float)$this->config->get('mt_jet_credit_price');
			if ($total < $jet_minstojnost) {
				$status = false;
			}
		}else{
			$status = false;
		}

		$method_data = array();
		if ($status) {
			$method_data = array(
				'code'       => 'jet',
				'title'      => 'ПБ Лични Финанси',
				'terms'      => '',
				'sort_order' => $this->config->get('jet_sort_order')
			);
		}

		return $method_data;
	}
}
