<?php
abstract class Meanbee_Shipwire_Model_Api_Abstract extends Mage_Core_Model_Abstract {
	protected $_environment = 'Test'; // Production
	protected $_messageStack = array();

	abstract public function submitRequest();
	
	public function _construct() {
		if (Mage::getStoreConfig('shipwire/auth/environment') == 'Production') {
			$this->_environment = 'Production';
		} else {
			$this->_environment = 'Test';
		}
		$this->_init('shipwire/api_ordersubmit');
	}
	
	protected function _getEnvironment() {
		return $this->_environment;
	}
	
	protected function _getEmail() {
		return Mage::getStoreConfig('shipwire/auth/email');
	}
	
	protected function _getPassword() {
		return Mage::getStoreConfig('shipwire/auth/password');
	}
	
	protected function _pushMessage($message, $type='s') {
		$this->_messageStack[] = $type . $message;
	}
	
	protected function getResultTag($needle, $haystack) {
		$tag = array();
		
		foreach ($haystack as $index => $array) {
			if ($array['tag'] == strtoupper($needle)) {
				$entry = array();
			
				if (isset($array['value'])) {
					$entry['value'] = $array['value'];
				}
				
				if (isset($array['attributes'])) {
					$entry['attributes'] = $array['attributes'];
				}
				
				$tag[] = $entry;
			}
		}
		
		return $tag; 
	}
}