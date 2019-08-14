<?php
define('DEBUG', 0);

class Meanbee_Shipwire_Model_Api_Inventorysynch extends Meanbee_Shipwire_Model_Api_Abstract {
	public function cron() {
		$this->submitRequest();
	}

	protected function _buildXml() {
		$account_email = $this->_getEmail();
		$account_password = $this->_getPassword();

		$xml  = "<InventoryUpdate>";
		$xml .=     "<Server>" . $this->_getEnvironment() . "</Server>";
		$xml .= 	"<EmailAddress>" . $this->_getEmail() . "</EmailAddress>";
		$xml .= 	"<Password>" . $this->_getPassword() . "</Password>";
		$xml .= 	"<Warehouse/>";
		$xml .= 	"<ProductCode/>";
		$xml .= "</InventoryUpdate>";

		return $xml;
	}

	protected function _parseResponse($response) {
		$values = array();
		$index = array();

		$p = xml_parser_create();
		xml_parse_into_struct($p, $response->getBody(), $values, $index);
		xml_parser_free($p);

		//echo "Values<br /><pre>";
		//var_dump($values);
		//echo "</pre>Index<br /><pre>";
		//var_dump($index);
		//echo "</pre>";

		if (array_key_exists('STATUS', $index)) {
			$status = $values[$index['STATUS'][0]]['value'];
		}

		if ($status != 'Error') {
			if (array_key_exists('PRODUCT', $index)) {
				foreach ($index['PRODUCT'] as $idx) {
					$sku = $values[$idx]['attributes']['CODE'];
					$qty = $values[$idx]['attributes']['QUANTITY'];

					$this->_updateProduct($sku, $qty);
				}
			}
		} else {
			echo "There was a problem connecting to Shipwire: '" . $values[$index['ERRORMESSAGE'][0]]['value'] . "'";
		}
	}

	protected function _updateProduct($sku, $quantity) {
		$product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
		echo "Updating Product #$sku with new quantity: $quantity<br />";

		if ($product !== null) {
			echo "Item #$sku updated<br />";
			$inventory = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
			$inventory->setQty($quantity);

			try {
				$inventory->save();
			} catch (Zend_Db_Statement_Exception $e) {} // SQLSTATE[23000]: Integrity constraint violation
		} else {
			echo "Item #$sku not found<br />";
		}
	}

	public function submitRequest() {
		$client = new Varien_Http_Client('https://www.shipwire.com/exec/InventoryServices.php');
		$client->setMethod(Zend_Http_Client::POST);
		$client->setParameterPost('InventoryUpdateXML', $this->_buildXml());

		$response =  $client->request();

		if ($response->isSuccessful()) {
			if (DEBUG) {
				echo '<h1>Request</h1>';
				echo '<pre>'.htmlentities($xml).'</pre>';
				echo '<h1>Response</h1>';
				echo '<pre>'.htmlentities($response->getBody()).'</pre>';
			}
		} else {
			$this->_pushMessage($response->getStatus() . ": " . $response->getMessage(), 'e');
		}
	}
}
