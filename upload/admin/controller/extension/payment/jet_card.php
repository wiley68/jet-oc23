<?php
class ControllerExtensionPaymentJetCard extends Controller {

	private $error = array();
	
	public function index() {
		$this->load->language('extension/payment/jet_card');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('jet_card', $this->request->post);
			$this->session->data['success'] = 'Успех: Успешно направихте промените!';
			$this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		$data['heading_title']  = $this->language->get('heading_title');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_enabled']   = $this->language->get('text_enabled');
		$data['text_disabled']  = $this->language->get('text_disabled');

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => 'Начало',
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
		);
		$data['breadcrumbs'][] = array(
			'text' => 'Разплащане',
			'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/jet_card', 'token=' . $this->session->data['token'], 'SSL')
		);

		$data['action'] = $this->url->link('extension/payment/jet_card', 'token=' . $this->session->data['token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);

		if (isset($this->request->post['jet_card_order_status_id'])) {
			$data['jet_card_order_status_id'] = $this->request->post['jet_card_order_status_id'];
		} else {
			$data['jet_card_order_status_id'] = $this->config->get('jet_card_order_status_id');
		}
		if (isset($this->request->post['jet_card_total'])) {
			$data['jet_card_total'] = $this->request->post['jet_card_total'];
		} else {
			$data['jet_card_total'] = $this->config->get('jet_card_total');
		}
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		if (isset($this->request->post['jet_card_geo_zone_id'])) {
			$data['jet_card_geo_zone_id'] = $this->request->post['jet_card_geo_zone_id'];
		} else {
			$data['jet_card_geo_zone_id'] = $this->config->get('jet_card_geo_zone_id');
		}
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		if (isset($this->request->post['jet_card_status'])) {
			$data['jet_card_status'] = $this->request->post['jet_card_status'];
		} else {
			$data['jet_card_status'] = $this->config->get('jet_card_status');
		}
		if (isset($this->request->post['jet_card_sort_order'])) {
			$data['jet_card_sort_order'] = $this->request->post['jet_card_sort_order'];
		} else {
			$data['jet_card_sort_order'] = $this->config->get('jet_card_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/jet_card.tpl', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/jet_card')) {
			$this->error['warning'] = 'Нямате права за тази операция!';
		}
		return !$this->error;
	}
}
