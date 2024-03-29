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

class Meanbee_Shipwire_Block_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{

    public function __construct()
    {
			parent::__construct();
      if (Mage::getStoreConfig('shipwire/services/order_submission') && $this->_isAllowedAction('ship') && $this->getOrder()->canShip()) {
				$this->_addButton('', array(
					'label'     => 'Fulfill via Shipwire',
					'onclick'   => 'setLocation(\'' . $this->getFulfillUrl() . '\')',
				));
			}
    }

	public function getFulfillUrl() {
		return $this->getUrl('shipwire/fulfill/submit');
	}
}
