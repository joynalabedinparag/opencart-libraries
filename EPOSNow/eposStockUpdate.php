<?php
class ControllerEposStockUpdate extends Controller
{
	private $log_content = array();
	private $epos;
	private $access_token = '';
	public function index()
	{
		$this->epos = EPOSNow::get_instance( $this->registry );
		$page_no    = 1;
		$this->updateProductQuantity( $page_no );
		$this->epos->LogStockUpdate( json_encode( $this->log_content ) );
	}

	private function updateProductQuantity( $page_no )
	{
		$response      = $this->epos->getProductStockByPageNo( $page_no );
		$productStocks = json_decode( $response['body'], true );
		$update_details = array();
		if( !empty($productStocks) )
		{
			foreach( $productStocks as $productStock )
			{
				$productId    = $productStock['ProductID'];
				$currentStock = $productStock['CurrentStock'];
				$result       = $this->db->query( "UPDATE " . DB_PREFIX . "product SET quantity = '" . $this->db->escape( $currentStock ) . "' WHERE `ean` = '" . (int) $productId . "'" );
				$update_details[] = array('dateTime'=>date('Y-m-d H:i:s'), 'productId' => $productId, 'eposStock' => $currentStock);
			}
			$this->log_content[] = $update_details;
			$page_no++;
			self::updateProductQuantity( $page_no );
		}
	}
}
?>