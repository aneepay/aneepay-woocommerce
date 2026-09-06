<?php
/**
 * Poll pending AneePay orders (cron entry point).
 */
class Ps_AneepayCronModuleFrontController extends ModuleFrontController {
	public function init() {
		$module = Module::getInstanceByName('ps_aneepay');

		if (!Validate::isLoadedObject($module)) {
			$this->respond(500, array('success' => false, 'message' => 'module not loaded'));
		}

		// No-overlap guard.
		$lock = 'aneepay_cron_lock';

		if (file_exists(_PS_CACHE_DIR_ . $lock)) {
			$this->respond(200, array('success' => false, 'message' => 'locked'));
		}

		@file_put_contents(_PS_CACHE_DIR_ . $lock, (string) time());

		try {
			$api       = $module->getApi();
			$webhook   = new AneePayWebhook($api, new AneePayOrder(), $module->getStateMap());
			$processed = 0;

			foreach ((new AneePayOrder())->getPendingOrderIds() as $id_order) {
				$row = (new AneePayOrder())->getByOrder((int) $id_order);

				if (!$row || empty($row['payment_id'])) {
					continue;
				}

				$data = $api->get_payment((string) $row['payment_id']);

				if (null === $data || empty($data['status'])) {
					continue;
				}

				if ($webhook->apply((int) $id_order, (string) $data['status'])) {
					$processed++;
				}
			}

			$this->respond(200, array('success' => true, 'processed' => $processed));
		} catch (Exception $e) {
			$this->respond(200, array('success' => false, 'message' => $e->getMessage()));
		} finally {
			@unlink(_PS_CACHE_DIR_ . $lock);
		}
	}

	protected function respond($code, $data) {
		http_response_code((int) $code);
		header('Content-Type: application/json');
		echo json_encode($data);

		exit;
	}
}
