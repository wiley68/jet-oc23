<?php
class ControllerExtensionPaymentJet extends Controller {

	const JETCREDIT_FINANCIAL_MAX_ITERATIONS = 128;
	const JETCREDIT_FINANCIAL_PRECISION = 1.0e-08;

	public function index() {
		if (!isset($this->session->data['currency']) || $this->session->data['currency'] !== 'EUR') {
			return '';
		}
		$this->load->language('extension/payment/jet');
		$data['JET_VERSION'] = '2.0.2';

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['text_loading'] = $this->language->get('text_loading');

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$products = $this->cart->getProducts();
		$jet_price = (float) $order_info['total'];
		$jet_minstojnost = (float) $this->config->get('mt_jet_credit_price');
		$data['jet_status'] = false;
		$data['jet_price'] = $jet_price;
		$data['jet_minstojnost'] = $jet_minstojnost;
		$data['jet_vnoski'] = (int) $this->config->get('mt_jet_credit_vnoski');

		$jet_sign = 'евро';
		$jet_min_250 = 125.00;
		$jet_vnoski_default = (int)$this->config->get('mt_jet_credit_vnoski');
		if ($jet_price < $jet_min_250) {
			$jet_vnoski = '9';
		} else {
			$jet_vnoski = $jet_vnoski_default;
		}
		$jet_products = '';
		$jet_products_qt = '';
		$jet_products_pr = '';
		$jet_products_vr = '';
		foreach ($products as $cart_item) {
			$jet_products .= $cart_item['product_id'] . '_';
			$jet_products_qt .= $cart_item['quantity'] . '_';
			$jet_product_vr_current = '';
			foreach ($cart_item['option'] as $option) {
				$jet_product_vr_current .= $option['value'] . ', ';
			}
			$jet_product_vr_current = substr($jet_product_vr_current, 0, -2);
			$jet_products_pr_current = $this->tax->calculate($cart_item['price'], $cart_item['tax_class_id'], $this->config->get('config_tax'));

			$jet_products_pr .= number_format($jet_products_pr_current, 2, ".", "") . '_';
			$jet_products_vr .= $jet_product_vr_current . '_';
		}
		$data['jet_products'] = trim($jet_products, "_");
		$data['jet_products_qt'] = substr($jet_products_qt, 0, -1);
		$data['jet_products_pr'] = substr($jet_products_pr, 0, -1);
		$data['jet_products_vr'] = substr($jet_products_vr, 0, -1);
		$data['jet_sign'] = $jet_sign;

		return $this->load->view('extension/payment/jet', $data);
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

		if ($this->session->data['payment_method']['code'] == 'jet') {
			if (isset($this->request->post['jet_priceall'])) {
				$jet_priceall = filter_var($this->request->post['jet_priceall'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_priceall = 0.00;
			}
			if (isset($this->request->post['jet_vnoski'])) {
				$jet_vnoski = filter_var($this->request->post['jet_vnoski'], FILTER_SANITIZE_NUMBER_INT);
			} else {
				$jet_vnoski = (int) $this->config->get('mt_jet_credit_vnoski');
			}
			if (isset($this->request->post['jet_vnoska'])) {
				$jet_vnoska = filter_var($this->request->post['jet_vnoska'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_vnoska = 0.00;
			}
			if (isset($this->request->post['jet_parva'])) {
				$jet_parva = filter_var($this->request->post['jet_parva'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_parva = 0.00;
			}
			if (isset($this->request->post['jet_total_credit_price'])) {
				$jet_total_credit_price = filter_var($this->request->post['jet_total_credit_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_total_credit_price = 0.00;
			}
			if (isset($this->request->post['jet_obshto'])) {
				$jet_obshto = filter_var($this->request->post['jet_obshto'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_obshto = 0.00;
			}
			if (isset($this->request->post['jet_gpr'])) {
				$jet_gpr = filter_var($this->request->post['jet_gpr'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_gpr = 0.00;
			}
			if (isset($this->request->post['jet_glp'])) {
				$jet_glp = filter_var($this->request->post['jet_glp'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			} else {
				$jet_glp = 0.00;
			}
			if (isset($this->request->post['jet_egn'])) {
				$jet_egn = filter_var($this->request->post['jet_egn'], FILTER_SANITIZE_STRING);
			} else {
				$jet_egn = " - ";
			}
			if (isset($this->request->post['jet_products'])) {
				$jet_products = filter_var($this->request->post['jet_products'], FILTER_SANITIZE_STRING);
			} else {
				$jet_products = '';
			}
			if (isset($this->request->post['jet_products_qt'])) {
				$jet_products_qt = filter_var($this->request->post['jet_products_qt'], FILTER_SANITIZE_STRING);
			} else {
				$jet_products_qt = '';
			}
			if (isset($this->request->post['jet_products_pr'])) {
				$jet_products_pr = filter_var($this->request->post['jet_products_pr'], FILTER_SANITIZE_STRING);
			} else {
				$jet_products_pr = '';
			}
			if (isset($this->request->post['jet_products_vr'])) {
				$jet_products_vr = filter_var($this->request->post['jet_products_vr'], FILTER_SANITIZE_STRING);
			} else {
				$jet_products_vr = '';
			}

			$this->load->model('checkout/order');
			$order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
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

			$body .= "Данни за кредита:\r\n";
			$body .= "Размер на кредита: " . number_format($jet_priceall - $jet_parva, 2, '.', '') . ";\r\n";
			$body .= "Срок на изплащане в месеца: $jet_vnoski;\r\n";
			$body .= "Месечна вноска: $jet_vnoska;\r\n";
			$body .= "Първоначална вноска: " . number_format(floatval($jet_parva), 2, ".", "") . ";\r\n";

			$jet_count = (int) $this->config->get('mt_jet_credit_count');
			$this->editSettingValue('mt_jet_credit', 'mt_jet_credit_count', $jet_count + 1);
			$subject_raw = $jet_id . ", онлайн заявка по поръчка $jet_count";
            $subject = $this->jetEncodeSubjectUtf8($subject_raw);
			$headers = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/plain; charset=utf-8' . "\r\n";
			$headers .= 'From: ' . mb_encode_mimeheader($jet_id,"UTF-8") . ' <' . $toEmail_admin . '>' . "\r\n";
			$headers .= "CC: " . $toEmail_other . ", " . $jet_email . "\r\n";

			if (mail($toEmail_admin, $subject, $body, $headers)) {
				$this->load->model('checkout/order');
				$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('jet_order_status_id'));
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
