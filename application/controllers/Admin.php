<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends MY_Controller {
	
	// Productos
	const C_TUPASE = 1; // TuPase
	
	const DIAS_MES = 30;
	
	// Nombre de usuario y contraseña
	const USERNAME = "admin";
	const PASSWORD = "bbfacb9fc5ab1f72311d739b416345a7"; // md5("3g2016motion")
	
	public function __construct() {
		parent::__construct();
		$this->load->model('core_model', '', TRUE);
		$this->load->library('encryption');
	}

	public function index() {
		$this->load->view("admin/login");
	}
	
	public function loginAction() {
		
		try {
			
			$userName = trim($this->input->post("txtUsername"));
			$pass = trim($this->input->post("txtPass"));
			
			if(empty($userName) || empty($pass)) throw new Exception("Debes completar todos los campos.", 1000);
			if($userName != self::USERNAME || md5($pass) != self::PASSWORD) throw new Exception("Nombre de usuario y/o contraseña incorrecto.", 1000);
			
			// Crea session
			$session = array("token" => $this->encryption->encrypt(self::USERNAME));
			$this->session->set_userdata($session);
			
			redirect("admin/listTrxs");
			
		} catch(Exception $e) {
			echo $e->getMessage();
		}
		
	}
	
	private function _isLogged() {
		
		try {
			
			$session = $this->session->userdata("token");
			if(empty($session)) throw new Exception("Usuario no se encuentra autenticado", 1000);
			
			$decrypt = $this->encryption->decrypt($session);
			if($decrypt != self::USERNAME) throw new Exception("Usuario no se encuentra autenticado", 1000);
			
			return TRUE;
			
		} catch(Exception $e) {
			return FALSE;
		}
		
	}
	
	// Lista las transacciones
	public function listTrxs() {
		
		try {
		
			if(!$this->_isLogged()) throw new Exception("Estás intentando acceder a contenido restringido", 1000);

			$table = '<table cellpadding="5" class="tablee">
						<tr>
							<th>ID</th>
							<th>Etapa</th>
							<th>Producto (Comercio)</th>
							<th>Tipo Pago</th>
							<th>ID Servicio</th>
							<th>Monto</th>
							<th>País</th>
							<th>Flujo Nuevo</th>
							<th>RUT</th>
							<th>Nombre</th>
							<th>E-Mail</th>
							<th>Fecha Creación</th>
							<th class="destacar">Generar Boleta</th>
							<th class="destacar">Ver Boletas</th>
						</tr>';
			
			
			$trxs = $this->core_model->getAllTrxPatPass();
					
			if(!is_null($trxs)) {
				foreach($trxs as $trx) {
					
					$flow = $trx->oldFlow == 1 ? "SI" : "NO";
					
					// Verifica el estado de la trx y lo pinta para mejor gráfica
					$ready = $trx->idStage == parent::OK_ALL ? " class = 'ready'" : "";
					$ready = ""; // quitar
					$tieneBoletas = FALSE;
					
					$table .= "<tr$ready>
								<td>".$trx->idTrx."</td>
								<td>".$trx->name."</td>
								<td>".$trx->name_commerce."</td>
								<td>".$trx->name_pt."</td>
								<td>".$trx->serviceId."</td>
								<td>".$trx->amount."</td>
								<td>".$trx->idCountry."</td>
								<td>".$flow."</td>
								<td>".$trx->cardHolderId."</td>
								<td>".$trx->cardHolderName." ".$trx->cardHolderLastName1." ".$trx->cardHolderLastName2."</td>
								<td>".$trx->cardHolderMail."</td>
								<td>".$trx->creationDate."</td>";
					
					// Verifica si está OK, solo así permite la generación de la boleta
					if($trx->idStage == parent::OK_ALL || $trx->idStage == parent::OK_NO_RESP_NOTIFY) {
					
						// Si se encuentra en este estado, se podrá generar la primera boleta para la transacción
						$table .= "<td align='center'>
										<form action='".base_url()."bsale/makeSaleTicket' method='post'>
											<input name='generating_trx' type='hidden' value='".$trx->idTrx."' />
											<input type='submit' value='Generar' />
										</form>
									</td>";
					
					} else if($trx->idStage == parent::BOLETA_GENERADA_PATPASS) {
						
						// Revisa si hay alguna boleta previamente generada en el sistema
						// Si hay una boleta generada, evalúa 1 mes desde el día que fue generada para ver si aplica solicitar nueva generación
						// Considera la última boleta
						$arrTrxBsale = $this->core_model->getTrxBSale($trx->idTrx);
						
						if(!is_null($arrTrxBsale)) {
							
							$tieneBoletas = TRUE;
							
							$oTrxBsale = $arrTrxBsale[0];
						
							$fechaCreacion = strtotime($oTrxBsale->creationDate);
							$fechaHoy = strtotime("now");
							$diff = $fechaHoy - $fechaCreacion; // segundos
							
							$mes = self::DIAS_MES * 24 * 60 * 60;
							
							if($diff >= $mes) {
								$table .= "<td align='center'>
												<form action='".base_url()."bsale/makeSaleTicket' method='post'>
													<input name='generating_trx' type='hidden' value='".$trx->idTrx."' />
													<input type='submit' value='Generar' />
												</form>
											</td>";
							} else {
								$table .= "<td></td>";
							}
				
						} else {
							// Nunca debería llegar acá
							$table .= "<td></td>";
						}
						
					} else {
						
						// Cualquier otro estado no permite la creación de boletas
						$table .= "<td></td>";
					}
					
					// Verifica si hay boleta generada y fue enviada o no
					if($tieneBoletas) {
						$table .= "<td align='center'>
										<form action='".base_url()."admin/listSaleTickets' method='post'>
											<input name='sending_trx' type='hidden' value='".$trx->idTrx."' />
											<input type='submit' value='Ver Boletas' />
										</form>
								</td>";
					} else {
						$table .= "<td></td>";
					}
					
					$table .= "</tr>";		
				}
			} else {
				$table .= "<tr><td colspan='14'>No existen registros para TuPase.cl</td></tr>";
			} 
			
			$table .= "</table>";
			
			$data["trxs"] = $table;
			
			$this->load->view("admin/trxs", $data);
			
		} catch(Exception $e) {
			echo $e->getMessage();
		}

	}
	
	/**
	 * Lista las boletas emitidas y en el estado en que se encuentran
	 */
	public function listSaleTickets() {
		
		try {
			
			if(!$this->_isLogged()) throw new Exception("Estás intentando acceder a contenido restringido", 1000);
			
			// Recibe el idTrx
			$idTrx = $this->input->post("sending_trx");
			if(empty($idTrx)) throw new Exception("No se podido identificar la transacción", 1000);
			
			// Busca todas las boletas de la transacción
			$arrTrxBsale = $this->core_model->getTrxBSale($idTrx);
			if(is_null($arrTrxBsale)) throw new Exception("No existen boletas para la transacción seleccionada", 1000);
			
			// Arma listado de boletas
			$table = '<table cellpadding="5" class="tablee">
					<tr>
						<th>ID</th>
						<th>Número</th>
						<th>Neto</th>
						<th>Impuesto</th>
						<th>Total</th>
						<th>Fecha Emisión</th>
						<th>Fecha Expiración</th>
						<th>Fecha/Hora Generación</th>
						<th>Token</th>
						<th>Enviado</th>
						<th class="destacar">Enviar Boleta</th>
					</tr>';
			
			foreach($arrTrxBsale as $trx) {
				
				$sent = $trx->sent == 1 ? "SI" : "NO";

				$table .= "<tr>
							<td>".$trx->idTrxBsale."</td>
							<td>".$trx->number."</td>
							<td>".$trx->netAmount."</td>
							<td>".$trx->taxAmount."</td>
							<td>".$trx->totalAmount."</td>
							<td>".date("Y-m-d", $trx->emissionDate)."</td>
							<td>".date("Y-m-d", $trx->expirationDate)."</td>
							<td>".date("Y-m-d H:i:s", $trx->generationDate)."</td>
							<td>".$trx->token."</td>
							<td><b>".$sent."</b></td>";
				
				// Evalúa si dejar enviar el correo o no
				if($trx->sent == 0) {
					$table .= "<td align='center'>
								<form action='".base_url()."bsale/sendSaleTicket' method='post'>
									<input name='sending_trx' type='hidden' value='".$trx->idTrxBsale."' />
									<input type='submit' value='Enviar Correo' />
								</form>
						</td>";
				} else {
					$table .= "<td></td>";
				}
				
				$table .= "</tr>";	
				
			}
			
			$table .= "</table>";
			
			$data["trx"] = $idTrx;
			$data["boletas"] = $table;
			
			$this->load->view("admin/saletickets", $data);
			
		} catch(Exception $e) {
			echo "No se pudo procesar la solicitud: " . $e->getMessage();
		}
		
	}
	
	
	/**
	 * Realiza las peticiones por cURL
	 */
	private function _doAction($service, $type = "GET", $params = NULL) {

		try {
			
			$access_token = $this->config->item("BSaleAccessToken");
		
			// Inicia cURL
			$session = curl_init($service);
			
			// Indica a cURL que retorne data
			curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);

			if($type == "POST") {
				
				// No viene parámetros
				if(is_null($params)) throw new Exception("No se ha definido ningún objeto para el POST", 1000);
				
				curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 2);
				curl_setopt($session, CURLOPT_POST, TRUE);
				curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($params));
			}
			
			// Configura cabeceras
			$headers = array(
				'access_token: ' . $access_token,
				'Accept: application/json',
				'Content-Type: application/json'
			);
			curl_setopt($session, CURLOPT_HTTPHEADER, $headers);

			// Ejecuta cURL
			$response = curl_exec($session);
			if(empty($response)) throw new Exception("ERROR: " . curl_error($session));
			
			//print_r($response); exit;
			$info = curl_getinfo($session);
			$errCode = $info["http_code"];
			
			if($errCode != 200 && $errCode != 201) throw new Exception("ERROR: " . print_r($info, TRUE));
			
			// Cierra la sesión cURL
			curl_close($session);
		
			return json_decode($response);

		} catch(Exception $e) {
			log_message("error", $e->getMessage());
			return NULL;
		}

	}
	

}
