<?php
/**
 * This script 'calls home' every day with information that help us to
 * guage the popularity of our module.
 *
 * The information we collect is listed below:
 * 
 * 		- Shipwire Username
 * 		- Store URL
 * 		- Store locale (e.g. en_GB)
 */
class Meanbee_Shipwire_Model_Call {
	const ENABLE = true;

	public function home() {
		if (self::ENABLE) {			
			$post = new Zend_Http_Client();
			$post->setMethod(Zend_Http_Client::POST);
			$post->setUri('http://tools.meanbee.com/module_stats/tracker.php');
			$post->setParameterPost(array(
				'module' => 'Meanbee_Shipwire',
				'module_version' => '0.3.2',
				'store_url' => Mage::getStoreConfig('web/secure/base_url'),
				'store_locale' => Mage::getStoreConfig('general/locale/code'),
				'shipwire_email' => Mage::getStoreConfig('shipwire/auth/email')
			));
			
			$response = $post->request();
			
			echo 'Response: ' . $response->getStatus() . ' ' . $response->getMessage();
			echo $response->getBody();
		}
	}
}