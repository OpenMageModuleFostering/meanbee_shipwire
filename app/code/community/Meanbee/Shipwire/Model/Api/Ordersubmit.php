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

define('DEBUG', 0);

class Meanbee_Shipwire_Model_Api_Ordersubmit extends Meanbee_Shipwire_Model_Api_Abstract {
	protected $_order; // Mage_Sales_Model_Order

	public function submitRequest() {
		$xml = $this->_buildXml();

		$client = new Varien_Http_Client('https://www.shipwire.com/exec/FulfillmentServices.php');
		$client->setMethod(Zend_Http_Client::POST);
		$client->setParameterPost('OrderListXML', $xml);
		$response =  $client->request();

		if ($response->isSuccessful()) {
			if (DEBUG) {
				echo '<h1>Request</h1>';
				echo '<pre>'.htmlentities($xml).'</pre>';
				echo '<h1>Response</h1>';
				echo '<pre>'.htmlentities($response->getBody()).'</pre>';
			}

			$this->_parseResponse($response);
		} else {
			$this->_pushMessage($response->getStatus() . ": " . $response->getMessage(), 'e');
		}

		return $this->_messageStack;
	}

	protected function _parseResponse($response) {
		if ($response->isSuccessful()) {
			$values = array();
			$index = array();

			$p = xml_parser_create();
			xml_parse_into_struct($p, $response->getBody(), $values, $index);
			xml_parser_free($p);

			//print_r($values);
			//print_r($index);
			$exceptions = array();
			$warnings = array();

			// Let's look for exceptions
			if (array_key_exists('EXCEPTION', $index)) {
				foreach($index['EXCEPTION'] as $id) {
					$exceptions[] = $values[$id]['value'];
				}
			}

			//..and warnings
			if (array_key_exists('WARNING', $index)) {
				foreach($index['WARNING'] as $id) {
					$warnings[] = $values[$id]['value'];
				}
			}

			if (array_key_exists('ORDER', $index)) {
				$order = $values[$index['ORDER'][0]]['attributes'];
			}

			if (array_key_exists('STATUS', $index)) {
				$status = $values[$index['STATUS'][0]]['value'];
			}


			if (DEBUG) {
				echo "<h1>What we can establish</h1>";
				if (isset($order)) {
					echo "Order Status: " . $order['STATUS'] . "<br />";
					echo "Our Order Id: " . $order['NUMBER'] . "<br />";
					echo "Shipwire's Id: " . $order['ID'] . "<br />";
				} else {
					echo "We can't establish anything";
				}
				echo "<br />";
				if (count($exceptions) > 0) {
					echo "There were the following exceptions:<br />";
					echo "<ul>";
					foreach ($exceptions as $exception) {
						echo "<li>$exception</li>";
					}
					echo "</ul>";
				} else {
					echo "There were no exceptions";
				}
				echo "<br />";
				if (count($warnings) > 0) {
					echo "There were the following warnings:<br />";
					echo "<ul>";
					foreach ($warnings as $warning) {
						echo "<li>$warning</li>";
					}
					echo "</ul>";
				} else {
					echo "There were no warnings";
				}
			}

			// Put all of the warnings and exceptions into a string
			$exception_string = '';
			if (count($exceptions) > 0) {
				$exception_string .= "<br />The following exceptions were returned from Shipwire:<br />";
				foreach ($exceptions as $exception) {
					$exception_string .= "  - $exception<br />";
				}
			}

			$warning_string = '';
			if (count($warnings) > 0) {
				if (count($exceptions) > 0) {
					$warning_string .= "<br />";
				}
				$warning_string .= "<br />The following warnings were returned from Shipwire:<br />";
				foreach ($warnings as $warning) {
					$warning_string .= "  -  $warning<br />";
				}
			}

			if (isset($order) && $order['STATUS'] == 'accepted') {
				// Get the price of the delivery
				$cost = 'unknown';
				if (array_key_exists('COST', $index)) {
					$cost = $values[$index['COST'][0]]['value'];
				}

				// Warehouse
				$warehouse = 'unknown';
				if (array_key_exists('WAREHOUSE', $index)) {
					$warehouse = $values[$index['WAREHOUSE'][0]]['value'];
				}

				// Service
				$service = 'unknown';
				if (array_key_exists('SERVICE', $index)) {
					$service = $values[$index['SERVICE'][0]]['value'];
				}

				$this->_addComment(
					'Shipwire Status: <b>ACCEPTED</b><br />' .
					'Shipwire ID: #' . $order['ID'] . '<br />' .
					'Shipping from <i>' . $warehouse . '</i> via <i>' . $service . '</i>, costing $' . number_format($cost, 2) . '<br />' .
					$exception_string .
					$warning_string
				);

				// Create the shipment
				$this->_createShipment();

				$this->_pushMessage('Order successfully sent to Shipwire.  Check the order comments for more details.', 's');
			} elseif (isset($order)) {
				$this->_addComment(
					'Shipwire Status: <b>' . ucfirst($order['STATUS']) . '</b><br />' .
					'There was a problem.  Please check your Shipwire account for more details.<br />' .
					$exception_string .
					$warning_string,
					'pending'
				);
				$this->_pushMessage('Order not accepted by Shipwire.  See the comments attached to this order.', 'e');
			} else {
				echo "Unable to read Shipwire's response";
			}
		} else {
			// We didn't get a 2xx success code
			echo "Error communicating with the Shipwire Servers.  Please try again later.";
		}
	}

	protected function _buildXml() {
		$account_email = $this->_getEmail();
		$account_password = $this->_getPassword();

		$order = $this->_getOrderObject();
		$name = $order->getCustomerName();
		$email = $order->getCustomerEmail();

		$name = $order->getShippingAddress()->getFirstname() . ' ' . $order->getShippingAddress()->getLastname();

		$address = $order->getShippingAddress()->explodeStreetAddress();

		$address_street1 = $address->getData('street1');
		$address_street1   = (!empty($address_street1)) ? $address_street1 : '';

		$address_street2 = $address->getData('street2');
		$address_street2   = (!empty($address_street2)) ? $address_street2 : '';

		$address_city = $address->getCity();
		$address_city      = (!empty($address_city)) ? $address_city : '';

		$address_region = $address->getRegion();
		$address_region    = (!empty($address_region)) ? $address_region : '';

		$address_country = $address->getCountry();
		$address_country   = (!empty($address_country)) ? $address_country : '';

		$address_postcode = $address->getPostcode();
		$address_postcode  = (!empty($address_postcode)) ? $address_postcode : '';

		$address_telephone = $address->getTelephone();
		$address_telephone = (!empty($address_telephone)) ? $address_telephone : '';

		switch (Mage::getStoreConfig('shipwire/order_submission/default_shipping_method')) {
			case 'oned':
				$shipping = '1D';
					break;
			case 'twod':
				$shipping = '2D';
					break;
			case 'gd':
				$shipping = 'GD';
					break;
			case 'ft':
				$shipping = 'FT';
					break;
			case 'intl':
				$shipping = 'INTL';
					break;
		}

		$items = $order->getItemsCollection();
		$item_xml = '';
		$num = 1;
		if (count($items) > 0) {
			foreach ($items as $item) {
				$item_xml .= '<Item num="' . $num++ . '">';
					$item_xml .= '<Code>' . htmlentities($item->getSku()) . '</Code>';
					$item_xml .= '<Quantity>' . htmlentities($item->getQtyOrdered()) . '</Quantity>';
				$item_xml .= '</Item>';
			}
		}

		$xml = '
		<OrderList StoreAccountName="magento">
			<EmailAddress>' . $account_email . '</EmailAddress>
			<Password>' . $account_password . '</Password>
			<Server>' . $this->_getEnvironment() . '</Server>
			<Referer>punkstar</Referer>
			<Order id="' . $order->getIncrementId() . '">
				<Warehouse>00</Warehouse>
				<AddressInfo type="ship">
					<Name>
						<Full>' . htmlentities($name) . '</Full>
					</Name>
					<Address1>' . htmlentities($address_street1) . '</Address1>
					<Address2>' . htmlentities($address_street2) . '</Address2>
					<City>' . htmlentities($address_city) . '</City>
					<State>' . htmlentities($address_region) . '</State>
					<Country>' . htmlentities($address_country) . '</Country>
					<Zip>' . htmlentities($address_postcode) . '</Zip>
					<Phone>' . htmlentities($address_telephone) . '</Phone>
					<Email>' . htmlentities($email) . '</Email>
				</AddressInfo>
				<Shipping>' . $shipping . '</Shipping>
				' . $item_xml . '
			 </Order>
		 </OrderList>';

		 return $xml;
	}

	protected function _createShipment() {
		try {
			$shipment = $this->_initShipment();
			if ($shipment) {
				$shipment->register();

				$shipment->addComment('<i>This shipment was automatically created by Meanbee\'s Shipwire Module.</i>', false);

				$shipment->sendEmail(true, '');

		        // Save the shipment
		        $transactionSave = Mage::getModel('core/resource_transaction')
		            ->addObject($shipment)
		            ->addObject($shipment->getOrder())
		            ->save();
			} else {
				$this->_pushMessage('Unable to create shipment', 'e');
			}
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}

	protected function _addComment($comment, $state = Mage_Sales_Model_Order::STATE_PROCESSING) {
		$this->_getOrderObject()->addStatusToHistory(
			$state,
			$comment . '<br /><i>This comment was automatically generated by Meanbee\'s Shipwire Module.</i>',
			false
		);
		$this->_getOrderObject()->save();
	}

	public function setOrderObject($obj) {
		$this->_order = $obj;
	}

	protected function _getOrderObject() {
		return $this->_order;
	}

    protected function _initShipment() {
        $shipment = false;
		$order = $this->_getOrderObject();

		/**
		 * Check order existing
		 */
		if (!$order->getId()) {
			$this->_pushMessage('Order no longer exists', 'e');
		}
		/**
		 * Check shipment create availability
		 */
		if (!$order->canShip()) {
			$this->_pushMessage('Can not do shipment for order.', 'e');
		}

		$convertor  = Mage::getModel('sales/convert_order');
		$shipment   = $convertor->toShipment($order);

		foreach ($order->getAllItems() as $orderItem) {
			if (!$orderItem->isDummy(true) && !$orderItem->getQtyToShip()) {
				continue;
			}
			if ($orderItem->isDummy(true) && !$this->_needToAddDummy($orderItem, $savedQtys)) {
				continue;
			}
			if ($orderItem->getIsVirtual()) {
				continue;
			}
			$item = $convertor->itemToShipmentItem($orderItem);
			if (isset($savedQtys[$orderItem->getId()])) {
				if ($savedQtys[$orderItem->getId()] > 0) {
					$qty = $savedQtys[$orderItem->getId()];
				} else {
					continue;
				}
			}
			else {
				if ($orderItem->isDummy(true)) {
					$qty = 1;
				} else {
					$qty = $orderItem->getQtyToShip();
				}
			}
			$item->setQty($qty);
			$shipment->addItem($item);
		}

        Mage::register('current_shipment', $shipment);
        return $shipment;
    }
}
