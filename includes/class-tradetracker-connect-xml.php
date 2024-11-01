<?php

/**
 * SimpleXMLElement wrapper class that supports CDATA.
 *
 * @link       https://tradetracker.com/
 * @since      2.0.0
 * @package    Tradetracker_Connect
 * @subpackage Tradetracker_Connect/includes
 * @author     Ferhat Yildirim <fyildirim@tradetracker.com>
 */
class Tradetracker_Connect_XML extends SimpleXMLElement
{
	public function addChildWithCDATA($name, $value = null) : Tradetracker_Connect_XML
	{
		$new_child = $this->addChild($name);

		if ($new_child !== null) {
			$node = dom_import_simplexml($new_child);
			$no = $node->ownerDocument;
			$node->appendChild($no->createCDATASection($value));
		}

		return $new_child;
	}

	public function saveXML(?string $filename = null)
	{
		$this->_set_product_count();

		parent::saveXML($filename);
	}

	protected function _set_product_count(): void
	{
		if (!isset($this->products, $this->products->product) || !method_exists($this->products->product, 'count')) {
			return;
		}

		$count = $this->products->product->count();
		if (isset($this->products['count'])) {
			$this->products['count'] = $count;
		} elseif (method_exists($this->products, 'addAttribute')) {
			$this->products->addAttribute('count', $count);
		}
	}
}