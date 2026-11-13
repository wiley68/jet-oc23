<?php

class ControllerExtensionPaymentJetCard extends Controller {

	const JETCREDIT_FINANCIAL_MAX_ITERATIONS = 128;
	const JETCREDIT_FINANCIAL_PRECISION = 1.0e-08;

	public function index() {
		if (!isset($this->session->data['currency']) || $this->session->data['currency'] !== 'EUR') {
			return '';
		}
		$this->load->language('extension/payment/jet_card');
		$data['JET_VERSION'] = '2.0.2';

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['text_loading'] = $this->language->get('text_loading');

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$products = $this->cart->getProducts();
		$jet_card_price = (float)$order_info['total'];
		$jet_card_minstojnost = (float)$this->config->get('mt_jet_credit_price');

		$data['jet_card_status'] = true;
		$data['jet_card_price'] = $jet_card_price;
		$data['jet_card_minstojnost'] = $jet_card_minstojnost;
		$data['jet_card_vnoski'] = (int)$this->config->get('mt_jet_credit_vnoski');


		$jet_card_sign = 'евро';
		$jet_card_min_250 = 125.00;

		$jet_card_vnoski_default = (int)$this->config->get('mt_jet_credit_vnoski');
		if ($jet_card_price < $jet_card_min_250) {
			$jet_card_vnoski = '9';
		} else {
			$jet_card_vnoski = $jet_card_vnoski_default;
		}

		$jet_card_products = '';
		$jet_card_products_qt = '';
		$jet_card_products_pr = '';
		$jet_card_products_vr = '';
		foreach ($products as $cart_item) {
			$jet_card_products .= $cart_item['product_id'] . '_';
			$jet_card_products_qt .= $cart_item['quantity'] . '_';
			$jet_card_product_vr_current = '';
			foreach ($cart_item['option'] as $option) {
				$jet_card_product_vr_current .= $option['value'] . ', ';
			}
			$jet_card_product_vr_current = substr($jet_card_product_vr_current, 0, -2);
			$jet_card_products_pr_current = $this->tax->calculate($cart_item['price'], $cart_item['tax_class_id'], $this->config->get('config_tax'));

			$jet_card_products_pr .= number_format($jet_card_products_pr_current, 2, ".", "") . '_';
			$jet_card_products_vr .= $jet_card_product_vr_current . '_';
		}
		$data['jet_card_products'] = trim($jet_card_products, "_");
		$data['jet_card_products_qt'] = substr($jet_card_products_qt, 0, -1);
		$data['jet_card_products_pr'] = substr($jet_card_products_pr, 0, -1);
		$data['jet_card_products_vr'] = substr($jet_card_products_vr, 0, -1);
		$data['jet_card_sign'] = $jet_card_sign;

		return $this->load->view('extension/payment/jet_card', $data);
	}

	public function confirm() {
		if (!isset($this->session->data['currency']) || $this->session->data['currency'] !== 'EUR') {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('success' => 'unsuccess')));
			return;
		}
		$json = [];
		$json['success'] = 'unsuccess';
		$json['redirect'] = $this->url->link('checkout/success');

		if ($this->session->data['payment_method']['code'] == 'jet_card') {
			if (isset($this->request->post['jet_card_priceall'])) {
				$jet_card_priceall = filter_var($this->request->post['jet_card_priceall'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_card_priceall = 0.00;
			}
			if (isset($this->request->post['jet_card_vnoski'])) {
				$jet_card_vnoski = filter_var($this->request->post['jet_card_vnoski'], FILTER_SANITIZE_NUMBER_INT);
			} else {
				$jet_card_vnoski = (int) $this->config->get('mt_jet_credit_vnoski');
			}
			if (isset($this->request->post['jet_card_vnoska'])) {
				$jet_card_vnoska = filter_var($this->request->post['jet_card_vnoska'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_card_vnoska = 0.00;
			}
			if (isset($this->request->post['jet_card_parva'])) {
				$jet_card_parva = filter_var($this->request->post['jet_card_parva'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_card_parva = 0.00;
			}
			if (isset($this->request->post['jet_card_total_credit_price'])) {
				$jet_card_total_credit_price = filter_var($this->request->post['jet_card_total_credit_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_card_total_credit_price = 0.00;
			}
			if (isset($this->request->post['jet_card_obshto'])) {
				$jet_card_obshto = filter_var($this->request->post['jet_card_obshto'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_card_obshto = 0.00;
			}
			if (isset($this->request->post['jet_card_gpr'])) {
				$jet_card_gpr = filter_var($this->request->post['jet_card_gpr'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_card_gpr = 0.00;
			}
			if (isset($this->request->post['jet_card_glp'])) {
				$jet_card_glp = filter_var($this->request->post['jet_card_glp'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_card_glp = 0.00;
			}
			if (isset($this->request->post['jet_card_egn'])) {
				$jet_card_egn = filter_var($this->request->post['jet_card_egn'], FILTER_SANITIZE_STRING);
			} else {
				$jet_card_egn = " - ";
			}
			if (isset($this->request->post['jet_card_products'])) {
				$jet_card_products = filter_var($this->request->post['jet_card_products'], FILTER_SANITIZE_STRING);
			} else {
				$jet_card_products = '';
			}
			if (isset($this->request->post['jet_card_products_qt'])) {
				$jet_card_products_qt = filter_var($this->request->post['jet_card_products_qt'], FILTER_SANITIZE_STRING);
			} else {
				$jet_card_products_qt = '';
			}
			if (isset($this->request->post['jet_card_products_pr'])) {
				$jet_card_products_pr = filter_var($this->request->post['jet_card_products_pr'], FILTER_SANITIZE_STRING);
			} else {
				$jet_card_products_pr = '';
			}
			if (isset($this->request->post['jet_card_products_vr'])) {
				$jet_card_products_vr = filter_var($this->request->post['jet_card_products_vr'], FILTER_SANITIZE_STRING);
			} else {
				$jet_card_products_vr = '';
			}

			$this->load->model('checkout/order');
			$order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
			$jet_card_name = isset($order['firstname']) ? $order['firstname'] : '';
			$jet_card_lastname = isset($order['lastname']) ? $order['lastname'] : '';
			$jet_card_phone = isset($order['telephone']) ? $order['telephone'] : '';
			$jet_card_email = isset($order['email']) ? $order['email'] : '';

			$toEmail_admin = (string)$this->config->get('config_email');
			$toEmail_other = (string)$this->config->get('mt_jet_credit_mail');
			$jet_id = (string)$this->config->get('mt_jet_credit_id');

			$jet_card_sign = 'евро';

			$body = "Данни за потребителя:\r\n";

			$body .= "Собствено име: $jet_card_name;\r\n";
			$body .= "Фамилия: $jet_card_lastname;\r\n";
			$body .= "ЕГН: $jet_card_egn;\r\n";
			$body .= "Телефон за връзка: $jet_card_phone;\r\n";
			$body .= "Имейл адрес: $jet_card_email;\r\n\r\n";

			$body .= "Данни за стоката:\r\n";

			$_product = explode('_', $jet_card_products);
			$product_q = explode('_', $jet_card_products_qt);
			$product_p = explode('_', $jet_card_products_pr);
			$product_v = explode('_', $jet_card_products_vr);

			for ($index = 0; $index < sizeof($_product); $index++) {
				$jet_card_product_id = $_product[$index];
				$this->load->model('catalog/product');
				$this->load->model('catalog/category');
				$categories = $this->model_catalog_product->getCategories($jet_card_product_id);
				$product_c_txt = " - ";
				foreach ($categories as $category) {
					$category_info = $this->model_catalog_category->getCategory($category['category_id']);
					if ($category_info) {
						$product_c_txt = $category_info['name'];
					}
				}
				$product = $this->model_catalog_product->getProduct((int)$jet_card_product_id);
				$jet_variations = $product_v[$index];
				$product_m_txt = html_entity_decode($product['name']);
				if ($jet_variations != '') {
					$product_m_txt .= ' - ';
					$product_m_txt .= html_entity_decode($jet_variations);
				}
				if ($product_m_txt == "") {
					$product_m_txt = " - ";
				}

				if (isset($product_p[$index]) && (float)$product_p[$index] != 0) {
					$product_p_txt = (float)$product_p[$index];
				}else{
					$product_p_txt = (float)$this->tax->calculate($product['price'], $product['tax_class_id'], true);
				}
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

			$body .= "Тип стока: Кредитна Карта;\r\n";
			$body .= "Марка: -;\r\n";
			$body .= "Единична цена с ДДС: 0.00;\r\n";
			$body .= "Брой стоки: 1;\r\n";
			$body .= "Обща сума с ДДС: 0.00;\r\n\r\n";

			$body .= "Данни за кредита:\r\n";

			$body .= "Размер на кредита: " . number_format($jet_card_priceall - $jet_card_parva, 2, '.', '') . ";\r\n";
			$body .= "Срок на изплащане в месеца: $jet_card_vnoski;\r\n";
			$body .= "Месечна вноска: $jet_card_vnoska;\r\n";
			$body .= "Първоначална вноска: " . number_format(floatval($jet_card_parva), 2, ".", "") . ";\r\n";

			$jet_count = (int)$this->config->get('mt_jet_credit_count');
			$this->editSettingValue('mt_jet_credit', 'mt_jet_credit_count', $jet_count + 1);
			$subject_raw = $jet_id . ", онлайн заявка по поръчка $jet_count";
            $subject = $this->jetEncodeSubjectUtf8($subject_raw);
			
			$headers = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/plain; charset=utf-8' . "\r\n";
			$headers .= 'From: ' . mb_encode_mimeheader($jet_id,"UTF-8") . ' <' . $toEmail_admin . '>' . "\r\n";
			$headers .= "CC: " . $toEmail_other . ", " . $jet_card_email . "\r\n";

			if (mail($toEmail_admin, $subject, $body, $headers)) {
				$this->load->model('checkout/order');
				$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('jet_card_order_status_id'));
				$json['success'] = 'success';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
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
