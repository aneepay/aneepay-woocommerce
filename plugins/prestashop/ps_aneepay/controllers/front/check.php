<?php
/**
 * AJAX: check the status of an AneePay order ("I already paid" / polling).
 */
class Ps_AneepayCheckModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	public function initContent() {
		parent::initContent();

		$module = Module::getInstanceByName('ps_aneepay');
		$api    = $module->getApi();

		$order_id = (int) Tools::getValue('id_order');

		if (!$order_id) {
			$this->respond(400, array('status' => 'invalid'));
		}

		$order_model = new AneePayOrder();
		$row = $order_model->getByOrder((int) $order_id);

		if (!$row) {
			$this->respond(404, array('status' => 'not_found'));
		}

		if (!empty($row['payment_status']) && !in_array((string) $row['payment_status'], array('', 'pending'), true)) {
			$this->respond(200, array('status' => (string) $row['payment_status']));
		}

		$data = $api->get_payment((string) $row['payment_id']);

		if (null === $data || empty($data['status'])) {
			$this->respond(200, array('status' => (string) $row['payment_status']));
		}

		$webhook = new AneePayWebhook($api, $order_model, $module->getStateMap());
		$webhook->apply((int) $order_id, (string) $data['status']);

		$this->respond(200, array('status' => (string) $data['status']));
	}

	protected function respond($code, $data) {
		http_response_code((int) $code);
		header('Content-Type: application/json');
		echo json_encode($data);

		exit;
	}
}
