<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@meanbee.com so we can send you a copy immediately.
 *
 * @category   Meanbee
 * @package    Meanbee_Shipwire
 * @copyright  Copyright (c) 2008 Meanbee Internet Solutions (http://www.meanbee.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Meanbee_Shipwire_FulfillController extends Mage_Adminhtml_Controller_Action {
	public function submitAction() {
		$order = $this->_initOrder();

		$order_submit = Mage::getModel('shipwire/api_ordersubmit');
		$order_submit->setOrderObject($order);
		$messages = $order_submit->submitRequest();

		if (count($messages) > 0) {
			foreach($messages as $message) {
				switch (substr($message, 0, 1)) {
					case 's':
						$this->_getSession()->addSuccess(substr($message, 1));
							break;
					case 'e':
						$this->_getSession()->addError(substr($message, 1));
							break;
				}
			}
		}

		if (!headers_sent()) {
			//$this->_redirectUrl(Mage::getStoreConfig('web/secure/base_url') . 'index.php/admin/sales_order/view/order_id/' . (int) $this->getRequest()->getParam('order_id'));
			//$this->_redirect('admin/sales_order/order_id/' . (int) $this->getRequest()->getParam('order_id'));
			$this->_redirectUrl($this->getUrl('admin/sales_order/order_id/'));
			$this->_redirect('adminhtml/sales_order/view', array('order_id' => (int) $this->getRequest()->getParam('order_id')));
		}
	}

    protected function _initOrder() {
        $id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($id);

        if (!$order->getId()) {
            $this->_getSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('admin/sales_order');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return false;
        }

        Mage::register('sales_order', $order);
        Mage::register('current_order', $order);

        return $order;
    }
}
