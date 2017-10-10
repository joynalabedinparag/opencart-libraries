<?php
class EPOSNow {
	private $logger;
	private static $instance;
	private $epos_key, $epos_secret, $token;
	private $customer_url = 'https://api.eposnowhq.com/api/V2/Customer/';
	private $transaction_url = 'https://api.eposnowhq.com/api/V2/Transaction/';
	private $transaction_item_url = 'https://api.eposnowhq.com/api/V2/TransactionItem/';
	private $product_stock_url = 'https://api.eposnowhq.com/api/V2/ProductStock/';

	/**
	 * @param  object  $registry  Registry Object
	*/
	protected function __construct ( $registry ) {
		$this->logger = $registry->get('log');
		$this->epos_key = 'your-epos-key';
		$this->epos_secret = 'your-epos-secret';
		$this->token = 'your-epos-token';
	}

	/**
	 * @param  object  $registry  Registry Object
	 */
	public static function get_instance( $registry ) {
		if (is_null(static::$instance)) {
			static::$instance = new static($registry);
		}
		return static::$instance;
	}

	public function putToEposNow ( $url, $data ) {
		$response = array();
		$data_json = json_encode($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data_json)
			)
		);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout after 60 seconds
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_USERPWD, "$this->epos_key:$this->epos_secret");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
		$response['result'] = curl_exec ($ch);
		if(curl_error($ch)) {
			$response['result'] = curl_error($ch);
		}
		$response['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
		curl_close($ch);
		return $response;
	}

	public function postToEPOSNow ( $url, $data ) {
		$response = array();
		$data_json = json_encode($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data_json)
			)
		);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout after 60 seconds
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_USERPWD, "$this->epos_key:$this->epos_secret");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
		$response['result'] = curl_exec ($ch);
		if(curl_error($ch)) {
			$response['result'] = curl_error($ch);
		}
		$response['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
		curl_close($ch);
		return $response;
	}

	public function AddNewCustomer ( $customer_data ) {
		$data = array();
		$data['Forename'] = $customer_data['firstname']; // Required
		$data['Surname'] = $customer_data['lastname'];
		$data['BusinessName'] = null;
		$data['ContactNumber'] = $customer_data['telephone'];
		$data['EmailAddress'] = $customer_data['email'];
		$data['MaxCredit'] = 0; // Required
		$data['SignUpDate'] = $customer_data['date_added']; // Required
		$api_response = $this->postToEPOSNow($this->customer_url, $data);
		$log_content = $api_response;
		$log_content['parameters'] = $data;
		$this->LogApiResponse($log_content);
		return $api_response;
	}

	public function AddNewTransaction ( $order_data ) {
		$data = array();
		$data['CustomerID'] = $order_data['epos_customer_id'];
		 $data['DateTime'] = date('Y-m-d\TH:i:s'); // Required
		$data['EatOut'] = "1"; // Required
		$api_response = $this->postToEPOSNow($this->transaction_url, $data);
		$log_content = $api_response;
		$log_content['parameters'] = $data;
		$this->LogApiResponse($log_content);
		return $api_response;
	}

	public function AddNewTransactionItem ( $transaction_data ) {
		$data = array();
		$data['TransactionID'] = $transaction_data['epos_transaction_id'];
		$data['ProductID'] = $transaction_data['ean'];
		$data['Quantity'] = $transaction_data['order_quantity'];
		$data['Price'] = $transaction_data['order_price'];
		$api_response = $this->postToEPOSNow($this->transaction_item_url, $data);
		$log_content = $api_response;
		$log_content['parameters'] = $data;
		$this->LogApiResponse($log_content);
		return $api_response;
	}

	public function UpdateProductStock ( $transaction_data ) {
		$data['CurrentStock'] = $transaction_data['quantity'];
		$product_stock_update_url = $this->product_stock_url . '/' .$transaction_data['isbn'];
		$api_response = $this->putToEposNow($product_stock_update_url, $data);
		$log_content = $api_response;
		$log_content['parameters'] = $data;
		$this->LogApiResponse($log_content);
		return $api_response;
	}

	public function LogApiResponse( $api_response ) {
		$log_file = "EposNowApiLog.log";
		$log = new Log($log_file);
		$log->write($api_response);
	}

	public function LogStockUpdate( $log_content ) {
		$log_file = "EposNowToFcStockUpdateLog.log";
		$log = new Log( $log_file );
		$log->write( $log_content );
	}

	/**
	 * Returns API Response for methods: GET, POST, PUT, DELETE
	 *
	 * @param  array    $data  	 contains all curl configuarations.
	 *  	$data can contain following configuarations as its indices.
	 * 		'url'           string     API URL
	 * 		'method'        string     HTTP Methods limited to GET, POST, PUT, DELETE
	 * 		'content_type'  string     CURL SET_OPT 'content_type'
	 * 		'params'        array      API Data
	 * 		'api_key'       string 	   API key
	 * 		'api_secret'    string 	   API Secret
	 * 		'send_json'     boolean    true for sending json to api.
	 *
	 * @author Md. Joynal Abedin Parag <parag.cste@gmail.com>
	 * @return array Response
	 */
	public function sendRequest ( $data )
	{
		$url = (array_key_exists('url', $data)) ? $data['url'] : null;
		$send_json = (array_key_exists('send_json', $data)) ? $data['send_json'] : true;
		$params = (array_key_exists('params', $data)) ? $data['params'] : null;
		$method = (array_key_exists('method', $data)) ? $data['method'] : null;
		$content_type = (array_key_exists('content_type', $data)) ? $data['content_type'] : 'application/json';
		$api_key = (array_key_exists('api_key', $data)) ? $data['api_key'] : null;
		$api_secret = (array_key_exists('api_secret', $data)) ? $data['api_secret'] : null;

		$response = array();
		if(is_null($url) || is_null($method)) {
			$response['error'] = "Undefined API URL or Method";
			return $response;
		}

		if( !is_null($params) )
		{
			if( $send_json == true )
			{
				$params = (is_array($params)) ? json_encode($params) : '';
			}
			else
			{
				$params = (is_array($params)) ? http_build_query($params) : '';
			}
		}

		$ch = curl_init();
		if ($method == 'DELETE')
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}

		if ($method == 'GET')
		{
			$url = (!empty($params)) ? $url . '?' . $params : $url;
		}

		if ($method == 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}

		if ($method == 'PUT')
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: ' . $content_type,
				'Content-Length: ' . strlen($params))
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20); //timeout after 60 seconds
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		if( !is_null($api_key) )
		{
			curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$api_secret");
		}
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		$api_response = curl_exec($ch);
		$curl_error = (curl_error($ch)) ? curl_error($ch) : null;
		if(!is_null($curl_error)) {
			$response['error'] = $curl_error;
			return $response;
		}
		$api_response_info = curl_getinfo($ch);
		curl_close($ch);
		$api_response_header = trim(substr($api_response, 0, $api_response_info['header_size']));
		$api_response_body = substr($api_response, $api_response_info['header_size']);
		$response['http_code'] = $api_response_info['http_code'];
		$response['header'] = $api_response_header;
		$response['body'] = $api_response_body;

		return $response;
	}

	public function getProductStockByPageNo($page_no) {
		$data = array('url' => $this->product_stock_url . '?page=' .$page_no,
		              'method' => 'GET',
		              'api_key' => $this->epos_key,
		              'api_secret' => $this->epos_secret,
		              'send_json' => true,
					);
		$response = $this->sendRequest( $data );
		return $response;
	}
}
?>

