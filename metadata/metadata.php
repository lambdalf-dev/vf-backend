<?php
require_once( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'ENV'    . DIRECTORY_SEPARATOR . 'CREDENTIALS.php' );
require     ( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' );

class ConfigurableHRM extends Web3\RequestManagers\HttpRequestManager {
	public function __construct( $host_, $timeout_ = 1, $config_ = [] ) {
		parent::__construct( $host_, $timeout_ );
		$this->client = new GuzzleHttp\Client( $config_ );
	}
}

class MetadataService {
	public function __construct() {
		$this->_startTransaction();

		if( empty( $_GET['tokenId'] ) ) {
			$this->_404();
		}

		$_tokenId_ = ( int ) $_GET[ 'tokenId' ];

		$_data_ = $this->_getTokenById( $_tokenId_ );

		if ( ! empty( $_data_ ) ) {
			$this->emitToken( $_data_ );
		}

		if ( ! $this->_tokenExists( $_tokenId_ ) ) {
			$this->_404();
		}

		if ( ! $this->_assignMetadata( $_tokenId_ ) ) {
			$this->_404();
		}

		$_data_ = $this->_getTokenById( $_tokenId_ );

		if ( ! empty( $_data_ ) ) {
			$this->emitToken( $_data_ );
		}
		else {
			$this->_404();
		}
	}

	private function _pdo() {
		static $pdo;

		if ( empty( $pdo ) ) {
			try {
				$pdo = new PDO( 'mysql:host=localhost;charset=utf8;dbname=' . DATABASE, USERNAME, PASSWORD );
			}
			catch ( PDOException $e ) {
				print "Error!: " . $e->getMessage() . "<br/>";
				die();
			}
		}

		return $pdo;
	}

	private function _startTransaction() {
		return $this->_pdo()->beginTransaction();
	}

	private function _rollbackTransaction() {
		return $this->_pdo()->rollBack();
	}

	private function _commitTransaction() {
		return $this->_pdo()->commit();
	}

	private function _getTokenByID( $tokenId_ ) {
		$_query_ = "SELECT
				 	t.tokenId
				,	m.Image
				,	m.Adjective
				,	m.Personnage
				,	m.Background
				,	m.Special
			FROM `Token` t
			INNER JOIN `Metadata` m ON t.metadataId = m.metadataId
			WHERE t.tokenId = :tokenId LIMIT 1";

		$_stmt_ = $this->_pdo()->prepare( $_query_ );
		$_stmt_->bindParam( ':tokenId', $tokenId_, PDO::PARAM_INT );
		$_stmt_->execute();

		if ( $_stmt_->rowCount() <= 0 ) {
			return null;
		}

		$_result_ = $_stmt_->fetch( PDO::FETCH_ASSOC );
		return $_result_;
	}

	private function _assignRandomMetadata( $tokenId_ ) {
		$_query_ = "UPDATE `Token`
			SET `metadataId` = (
				SELECT `metadataId`
					FROM `Metadata`
					WHERE `Quantity` > 0
					ORDER BY RAND()
					LIMIT 1
			)
			WHERE `tokenId` = :tokenId
			AND `metadataId` IS NULL";

		$_stmt_ = $this->_pdo()->prepare( $_query_ );
		$_stmt_->bindParam( ':tokenId', $tokenId_, PDO::PARAM_INT );
		$_stmt_->execute();

		if ( $_stmt_->rowCount() <= 0 ) {
			return false;
		}
		return true;
	}

	private function _tokenExists( $tokenId_ ) {
		$ABI = array (
			0 => array (
				'inputs' => array (
					0 => array (
						"internalType" => "uint256",
						"name" => "tokenId_",
						"type" => "uint256"
					)
				),
				"name" => "ownerOf",
				"outputs" => array (
					0 => array (
						"internalType" => "address",
						"name" => "owner",
						"type" => "address"
					)
				),
				"stateMutability" => "view",
				"type" => "function"
			)
		);

		$_tokenOwner_     = '';
		$_requestManager_ = new ConfigurableHRM( INFURA, 10, [ 'verify' => false ] );
		$_provider_       = new Web3\Providers\HttpProvider( $_requestManager_ );
		$_contract_       = new Web3\Contract( $_provider_, $_ABI_ );
		$_contract_->at( CONTRACT_ADDRESS )->call( 'ownerOf', $tokenId_, function( $err, $res ) use( &$_tokenOwner_ ) {
			if ( $err ) {
				return error_log( var_export( $err, true ) );
			}
			if ( isset( $res ) ) {
				$_tokenOwner_ = $res[0];
			}
		});

		return ! empty( $_tokenOwner_ );
	}

	private function _formatToken( $data_ ) {
		$_token_ = array(
			"name"         => $data_[ 'Adjective' ] + $data_[ 'Personnage' ],
			"description"  => "VeeFiends was created in honor of Gary Vaynerchuk. The creator of VeeFriends. VeeFiends are 268 hand-drawn, parody artworks stored on the Ethereum blockchain. Holders will gain access to exclusive giveaways, mini-games, global leaderboards and be a part of a fun, lighthearted community. And the biggest benefit of all, VeeFiends holders will participate in a lottery to win up to 15 grand prizes - Return flight, hotel accommodation and entry to VeeCon 2022 and beyond!",
			"external_url" => "https://veefiends.xyz",
			"image"        => "https://gateway.pinata.cloud/ipfs/" + $data_[ 'Image' ],
			"attributes"   => array(
				array(
					"trait_type" => "Background",
					"value"      => $data_[ 'Background' ]
				),
				array(
					"trait_type" => "Adjective",
					"value"      => $data_[ 'Adjective' ]
				),
				array(
					"trait_type" => "Special",
					"value"      => $data_[ 'Special' ]
				)
			)
		);
		return $_token_;
	}

	private function _emitToken( $data_ ) {
		$_token_ = $this->_formatToken( $data_ );
		$this->_commitTransaction();
		header( 'Content-Type: application/json' );
		echo json_encode( $_token_, JSON_UNESCAPED_SLASHES );
		exit;
	}

	private function _404() {
		$this->_rollbackTransaction();
		header( 'Content-Type: application/json', true, 404 );
		echo '{"code":404,"error":"Not Found"}';
		exit;		
	}
}

try {
	$metadataSvc = new MetadataService();
}
catch ( Exception $error ) {
	error_log( var_export( $error, true ) );
	$response = array(
		 	'requestPostData' => $_POST
		,	'requestGetData'  => $_GET
		,	'error'           => $error
	);
	returnJsonHttpResponse( false, $response );
}

