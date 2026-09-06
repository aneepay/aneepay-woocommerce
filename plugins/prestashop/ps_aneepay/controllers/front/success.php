<?php
/**
 * Success page (SUCCESS_URL) — shows a waiting/polling block until the on-chain
 * transaction is confirmed.
 */
class Ps_AneepaySuccessModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	public function initContent() {
		parent::initContent();

		$this->context->smarty->assign($this->module->resultData(true));

		$this->setTemplate('module:ps_aneepay/views/templates/front/result.tpl');
	}
}
