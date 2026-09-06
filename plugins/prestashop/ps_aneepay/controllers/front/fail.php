<?php
/**
 * Failure / cancelled page (FAIL_URL) — offers a "try again" link.
 */
class Ps_AneepayFailModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	public function initContent() {
		parent::initContent();

		$this->context->smarty->assign($this->module->resultData(false));

		$this->setTemplate('module:ps_aneepay/views/templates/front/result.tpl');
	}
}
