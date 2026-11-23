<?php
class ControllerExtensionPaymentJet extends Controller {

	const JETCREDIT_FINANCIAL_MAX_ITERATIONS = 128;
	const JETCREDIT_FINANCIAL_PRECISION = 1.0e-08;

	public function index() {
		if (!isset($this->session->data['currency']) || $this->session->data['currency'] !== 'EUR') {
			return '';
		}
		$this->load->language('extension/payment/jet');
		$data['JET_VERSION'] = '2.0.4';

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['text_loading'] = $this->language->get('text_loading');

		$data['jet_sign'] = 'евро';
		$data['jet_order'] = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;

		return $this->load->view('extension/payment/jet', $data);
	}

	public function confirm() {
		$validated = $this->load->controller('extension/mt_jet_credit/jetcredit/prepareSubmission',
			array('scope' => 'checkout', 'channel' => 'jet'));
		$json = isset($validated['lease']) ? $validated['lease']->execute(function () use ($validated) {
			return $this->confirmVerified($validated);
		}) : array('success' => 'unsuccess', 'reason' => isset($validated['reason']) ? $validated['reason'] : 'submission_unavailable');
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function confirmVerified(array $validated) {
		$lease = $validated['lease'];
		$jet_priceall = $validated['fields']['jet_priceall'];
		$jet_vnoski = $validated['fields']['jet_vnoski'];
		$jet_vnoska = $validated['fields']['jet_vnoska'];
		$jet_parva = $validated['fields']['jet_parva'];
		$jet_products = $validated['fields']['jet_products'];
		$jet_products_qt = $validated['fields']['jet_products_qt'];
		$jet_products_pr = $validated['fields']['jet_products_pr'];
		$jet_products_vr = $validated['fields']['jet_products_vr'];
		if (isset($this->request->post['jet_egn'])) {
			$jet_egn = filter_var($this->request->post['jet_egn'], FILTER_SANITIZE_STRING);
		} else {
			$jet_egn = " - ";
		}
		$this->load->model('checkout/order');
		$order = $validated['order'];
		$jet_name = isset($order['firstname']) ? $order['firstname'] : '';
		$jet_lastname = isset($order['lastname']) ? $order['lastname'] : '';
		$jet_phone = isset($order['telephone']) ? $order['telephone'] : '';
		$jet_email = isset($order['email']) ? $order['email'] : '';
		$toEmail_admin = (string) $this->config->get('config_email');
		$toEmail_other = (string) $this->config->get('mt_jet_credit_mail');
		$jet_id = (string) $this->config->get('mt_jet_credit_id');

		$jet_sign = 'евро';

		$body = "Данни за потребителя:\r\n";
		$body .= "Собствено име: $jet_name;\r\n";
		$body .= "Фамилия: $jet_lastname;\r\n";
		$body .= "ЕГН: $jet_egn;\r\n";
		$body .= "Телефон за връзка: $jet_phone;\r\n";
		$body .= "Имейл адрес: $jet_email;\r\n\r\n";
		$body .= "Данни за стоката:\r\n";
		$_product = explode('_', $jet_products);
		$product_q = explode('_', $jet_products_qt);
		$product_p = explode('_', $jet_products_pr);
		$product_v = explode('_', $jet_products_vr);

		for ($index = 0; $index < sizeof($_product); $index++) {
			$jet_product_id = $_product[$index];
			$this->load->model('catalog/product');
			$this->load->model('catalog/category');
			$categories = $this->model_catalog_product->getCategories($jet_product_id);
			$product_c_txt = " - ";
			foreach ($categories as $category) {
				$category_info = $this->model_catalog_category->getCategory($category['category_id']);
				if ($category_info) {
					$product_c_txt = $category_info['name'];
				}
			}
			$product = $this->model_catalog_product->getProduct( (int) $jet_product_id );
			$jet_variations = $product_v[$index];
			$product_m_txt = html_entity_decode($product['name']);
			if ($jet_variations != '') {
				$product_m_txt .= ' - ';
				$product_m_txt .= html_entity_decode($jet_variations);
			}
			if ($product_m_txt == "") {
				$product_m_txt = " - ";
			}
			$product_p_txt = (float)$product_p[$index];
			if (isset($product_q[$index]) && (int)$product_q[$index] != 0) {
				$product_q_txt = (int)$product_q[$index];
			}else{
				$product_q_txt = 1;
			}

			$body .= "Тип стока: " . $product_c_txt . ";\r\n";
			$body .= "Марка: " . "(" . $_product[$index] . ") " . $product_m_txt . ";\r\n";
			$body .= "Единична цена с ДДС: " . number_format($product_p_txt, 2, ".", "") . ";\r\n";
			$body .= "Брой стоки: " . $product_q_txt . ";\r\n";
			$body .= "Обща сума с ДДС: " . number_format((float)$product_q_txt * (float)$product_p_txt, 2, ".", "") . ";\r\n\r\n";
		}

		$body .= "Данни за кредита:\r\n";
		$body .= "Размер на кредита: " . number_format($jet_priceall - $jet_parva, 2, '.', '') . ";\r\n";
		$body .= "Срок на изплащане в месеца: $jet_vnoski;\r\n";
		$body .= "Месечна вноска: $jet_vnoska;\r\n";
		$body .= "Първоначална вноска: " . number_format(floatval($jet_parva), 2, ".", "") . ";\r\n";

		$jet_count = $lease->reserveReference();
		$subject_raw = $jet_id . ", онлайн заявка по поръчка $jet_count";
            $subject = $this->jetEncodeSubjectUtf8($subject_raw);
		$headers = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/plain; charset=utf-8' . "\r\n";
		$headers .= 'From: ' . mb_encode_mimeheader($jet_id,"UTF-8") . ' <' . $toEmail_admin . '>' . "\r\n";
		$headers .= "CC: " . $toEmail_other . ", " . $jet_email . "\r\n";

		if (mail($toEmail_admin, $subject, $body, $headers)) {
			$lease->mark('accepted');
			$this->load->model('checkout/order');
			$this->model_checkout_order->addOrderHistory($validated['order_id'], $this->config->get('jet_order_status_id'));
			$updated = $this->model_checkout_order->getOrder($validated['order_id']);
			if (!$updated || (int)$updated['order_status_id'] <= 0) {
				throw new \RuntimeException('JET order was not finalized.');
			}
			$lease->mark('complete');
			return array('success' => 'success', 'redirect' => $this->url->link('checkout/success'));
		}
		$lease->mark('failed');
		return array('success' => 'unsuccess', 'reason' => 'transport_failed');
	}

	public function editSettingValue($code = '', $key = '', $value = '', $store_id = 0) {
		if (!is_array($value)) {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "', serialized = '0'  WHERE `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '" . (int)$store_id . "'");
		} else {
			$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape(json_encode($value)) . "', serialized = '1' WHERE `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '" . (int)$store_id . "'");
		}
	}
    
    public function jetEncodeSubjectUtf8($subject) {
        if (preg_match('/=\?UTF-8\?[BQ]\?.*\?=/i', $subject)) {
            return $subject;
        }
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
        }
        return $subject;
    }
}
