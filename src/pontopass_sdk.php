<?php
/**
 * Copyright 2013 Pontosec Desenvolvimento e Solucoes em Tecnologia LTDA.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */


/*! @class PontopassAuth
    @abstract Integracao com WebService do Pontopass para confirmar a autenticacao de um usuario
    @version 1.0
*/
class PontopassAuth {

/**
* User-agent do navegador do usuario
*
* @var string
*/
public $user_agent;

/**
* Endereco IP do usuario
*
* @var string
*/
public $user_ip;

/**
* Sessao Pontopass do Usuario
*
* @var string
*/
public $user_session;


/**
* Servidor API Pontopass do Cliente
*
* @var string
*/
private static $api_server = "api.pontopass.com";

/**
* URL do Widget (Frame) Pontopass
*
* @var string
*/
private static $frameurl = "http://pontopass.com/demo/js/frame.html";




public $status;
private $return_url;
private $return_method = "POST";
private $token_type = Array(1 => "telefone", 2 => "sms", 3 => "app", 4 => "token");

private $statustxt = Array(0 => "Sucesso",
20 => "Erro ao iniciar",
30 => "Chave de API inv&Atilde;&iexcl;lida",
100 => "Sess&Atilde;&pound;o criada",
110 => "Novo dispositivo permitido para o usu&Atilde;&iexcl;rio",
150 => "Erro ao gravar session",
151 => "Erro ao gravar usu&Atilde;&iexcl;rio - Usu&Atilde;&iexcl;rio j&Atilde;&iexcl; existe",
152 => "Erro ao gravar usu&Atilde;&iexcl;rio",
153 => "Erro ao deletar usu&Atilde;&iexcl;rio",
155 => "IP e/ou User Agent incorreto(s)",
210 => "Erro de grava&Atilde;&sect;&Atilde;&pound;o no cache",
220 => "Erro Interno",
310 => "Erro Interno",
320 => "Erro Interno",
400 => "Usu&Atilde;&iexcl;rio n&Atilde;&pound;o encontrado",
405 => "Erro Interno",
410 => "Sess&Atilde;&pound;o n&Atilde;&pound;o encontrada",
411 => "Aplica&Atilde;&sect;&Atilde;&pound;o n&Atilde;&pound;o corresponde a session",
413 => "Session inv&Atilde;&iexcl;lida",
415 => "Dispositivo n&Atilde;&pound;o encontrado",
420 => "M&Atilde;&copy;todo n&Atilde;&pound;o encontrado",
422 => "Aplica&Atilde;&sect;&Atilde;&pound;o n&Atilde;&pound;o encontrada",
425 => "M&Atilde;&copy;todo n&Atilde;&pound;o encontrado",
440 => "Erro Interno",
450 => "Erro Interno",
490 => "Sem permiss&Atilde;&pound;o para gravar novo dispositivo",
492 => "Telefone invalido",
495 => "Novo m&Atilde;&copy;todo inserido",
510 => "Erro na Liga&Atilde;&sect;&Atilde;&pound;o",
520 => "Erro no SMS",
530 => "Erro no mobile Token",
540 => "Erro no Aplicativo Mobile",
600 => "Sem cr&Atilde;&copy;ditos dispon&Atilde;&shy;veis",
710 => "Erro no Login",
720 => "IP Inv&Atilde;&iexcl;lido",
800 => "Aguardando resposta",
810 => "Login bloqueado - SMS",
820 => "Login bloqueado - Mobile Token",
830 => "Login bloqueado - Chamada n&Atilde;&pound;o atendida",
840 => "Login bloqueado - Push / Telefone",
999 => "Erro");


/**
* Inicializacao;
*
* @param string $api_id ID de Integracao
* @param string $api_key Chave de Integracao 
* @access public
*/
   function __construct($api_id,$api_key,$api_server)
    {
	$this->api_id = $api_id;
	$this->api_key = $api_key;
	$this->api_server = empty($api_server)?"api.pontopass.com":$api_server;
	$this->user_agent = $_SERVER['HTTP_USER_AGENT'];
	$this->user_ip = $_SERVER['REMOTE_ADDR'];
	$this->user_remember = 0;
	$this->integration_type=1;
	}
   
/**
* Envia solicitacao ao WebService
*
* @param string $path Caminho a ser acessado
* @return object Resposta do servidor (json decoded) 
* @access private
*/
   protected function send($path) {
		$ch = curl_init();
		$timeout = 50;
		$url = "https://".self::$api_server.'/'.$path.'/json';
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_USERPWD, $this->api_id.":".$this->api_key);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		
		$data = curl_exec($ch);
		curl_close($ch);
	
		
		if ($data === FALSE) {
		trigger_error('Pontopass: Curl error: ' . curl_error($ch));
		return (object) array('status' => 999);
		}

		$decode = json_decode($data);
		if(isset($decode->status)) $this->status = $decode->status;
		return $decode;
	
	}
  
/**
* Retorna texto referente ao ultimo status respondido pelo WebService
*
* @return string Mensagem do ultimo status respondido pelo WebService
* @access public
*/
   public function lastStatus() {
	return $this->statustxt[$this->status];
	
	}    

/**
* Inica sessao de autenticacao do Usuario
*
* @param string $username Nome de usuario a ser autenticado
* @param bool|null $use_widget TRUE para utilizar widget (frame) para os proximos passos da autenticacao,  FALSE para nao utilizar widget (frame) 
* @param bool|null $user_remember TRUE para gravar a sessao do usuario em cookie, FALSE para nao gravar a sessao.
* @param string|null $user_remember IP do Usuario (quando nao preenchido, obtido diretamente do request http)
* @param string|null $user_agent User Agent do navegador do usuario (quando nao preenchido, obtido diretamente do request http)
* @return int Status Code
* @access public
*/
   public function init($username,$use_widget=TRUE,$user_remember=FALSE,$user_ip=null,$user_agent=null) {
		if(isset($use_widget)) $this->integration_type = ($use_widget)?1:0;
		if(isset($user_remember))$this->user_remember = ($user_remember)?1:0;
		if(isset($user_ip)) $this->user_ip = $user_ip;
		if(isset($user_agent)) $this->user_agent = $user_agent;
		$this->user = $username;
   

		$ret = $this->send("init/$username/$this->integration_type/$this->user_remember/$this->user_ip/".urlencode($this->user_agent));
		
		if(isset($ret->session)) { $this->user_session = $ret->session; }
		return ($ret->status)?$ret->status:999;
		

			
   
   }
   
/**
* Obtem todas as formas de confirmacao de login disponiveis para o usuario
*
* @return object Resposta do servidor (json decoded); FALSE em caso de erros. 
* @access public
*/	
 public function listMethods() {
	   if(isset($this->user_session)) {
		$ret = $this->send("list/$this->user_session/$this->user_ip/".urlencode($this->user_agent));
		
		
		return $ret;
		
		
		
		} else {
		trigger_error("Sessao não iniciada ou não definida", E_USER_ERROR); 
		return false;
		}
			
   
   }
   
/**
* Solicita ao Pontopass que confirme a autenticacao do usuario utilizando determinado metodo
*
* @param int $device_id ID da forma de autenticacao a ser utilizada (obtida, por exemplo, pelo listMethods)
* @return int Status Code retornado pelo WebService
* @access public
*/	   
   public function ask($device_id) {
	   if(isset($this->user_session)) {
		$ret = $this->send("ask/$this->user_session/$device_id/$this->user_ip/".urlencode($this->user_agent));
				
		
		
		return ($ret->status)?$ret->status:999;


		} else {
		trigger_error("Sessao não iniciada ou não definida", E_WARNING); 
		return 999;
		}
			
   
   }
   

/**
* Verifica codigo de confirmacao fornecido pelo usuario
*
* @param int $answer Codigo de Confirmacao
* @param int $token_type Codigo do tipo de confirmacao utilizado:  2 para SMS; 4 para Mobile Token
* @return int Status Code retornado pelo WebService
* @access public
*/	   
     public function validate($answer,$token_type) {


	   if(isset($this->user_session)) {
		
		$url = "validate/".$this->token_type[$token_type]."/$this->user_session/$answer/$this->user_ip/".urlencode($this->user_agent);
		$ret = $this->send($url);
		

		
		return ($ret->status)?$ret->status:999;

		} else {
		trigger_error("Sessao não iniciada ou não definida", E_WARNING); 
		return 999;
		}
			
   
   }

/**
* Gera codigo HTML do iframe do widget de confirmacao.
*
* @param int|null $h Altura do Iframe (default: 500)
* @param int|null $w Largura do Iframe (default: 500)
* @param string $return_url URL que recebe o retorno do Iframe
* @param string|null $return_method Metodo HTTP para envio a URL de Retorno. "POST" ou "GET" (Default: POST)
* @param array|null|null $post Array contendo outros parametros que devem ser passados via POST para a URL de Retorno
* @param array|null $get Array contendo outros parametros que devem ser passados via GET para a URL de Retorno
* @return string Codigo HTML do iframe do widget de confirmacao
* @access public
*/	   
   
   public function widget($h=500,$w=500,$return_url,$return_method="POST",$post=array(),$get=array()) {
	$postparams = $getparams = "";
	if(!empty($post)) { 
	foreach ($post as $key => $value) $postparams .= "post_$key=".urlencode($value)."&";
	}

	if(!empty($get)) { 
	foreach ($get as $key => $value) $getparams .= "get_$key=".urlencode($value)."&";
	}

		$url = self::$frameurl."?#pontopass_save=$this->user_remember&pontopass_url=".urlencode($return_url)."&pontopass_sess=".$this->user_session."&".$postparams.$getparams;
		return "<iframe src='$url' width='$w' height='$h' frameBorder='0'></iframe>";
		
	}


/**
* Verifica se a sessao de determinado usuario esta autenticada
* 
* @param string $username Nome do Usuario a ser verificado
* @return bool TRUE para caso sessao esta autorizada, FALSE para caso nao esteja autorizada
* @access public
*/	   	
 public function checkAnswer($username) {
		 if(!isset($this->user_session)) {
		 if(isset($_POST['pontopass_session']))  $this->user_session =  $_POST['pontopass_session'];
		 elseif(isset($_GET['pontopass_session'])) $this->user_session = $_GET['pontopass_session'];
		 }
		 

		
	   if(isset($this->user_session)) {
	   
		$ret = $this->send("auth/$this->user_session/$this->user_ip/".urlencode($this->user_agent));
		return (($ret->user == $username) && ($ret->status == 0));

	   } else {
	   
		trigger_error("Sessao não iniciada ou não definida", E_WARNING); 
		return false;

		}
		
	}
	
/**
* Obtem ultimo Status Code da sessao no WebService
* 
* @return int Status Code da sessao
* @access public
*/	
 public function status() {
		$ret = $this->send("status/$this->user_session/$this->user_ip/".urlencode($this->user_agent));
		return ($ret->status)?$ret->status:999;

	}

}


/*! @class PontopassUser
    @abstract Auxilia o gerenciamento de usuarios e dispositivos cadastrados na base do Pontopass
    @version 1.0
*/
class PontopassUser extends PontopassAuth {

/**
* Login do Usuario a ser manipulado
*
* @var string
*/
public $login;

/**
* Cadastra novo usuario
* 
* @param string|null $name Nome do Usuario (ou outra informacao para identifica-lo) - Opcional
* @return int Status Code da operacao retornado pelo WebService
* @access public
*/	 

 public function create($name="") {
 	 	if(empty($this->login)) { trigger_error("Login do usuario a ser manipulado nao definido. ", E_WARNING); return FALSE; }
		$ret = $this->send("manage/user/insert/$this->login/".urlencode($name));
		return $ret->status;
	}

/**
* Deleta Usuario
* 
* @return int Status Code da operacao retornado pelo WebService
* @access public
*/	 

 public function delete() {
 	 	if(empty($this->login)) { trigger_error("Login do usuario a ser manipulado nao definido. ", E_WARNING); return FALSE; }
		$ret = $this->send("manage/user/delete/$this->login");
		return $ret->status;
	}

/**
* Cadastra dispositivo do usuario
* 
* @param int $type Forma de Autenticacao a ser utilizada (1 para ligacao telefonica, 2 para codigo por sms, 3 para confirmacao por app mobile, 4 por codigo de mobile token)
* @param string $phone Telefone do Usuario, incluindo codigo do pais e da cidade, sem 0 de prefixo, sem + de prefixo.
* @param string|null $desc Descricao do Dispositivo (ex: Celular do Roberto) - Opcional
* @return int Status Code da operacao retornado pelo WebService
* @access public
*/	 

 public function insertDevice($type,$phone,$desc=null) {
 	 	if(empty($this->login)) { trigger_error("Login do usuario a ser manipulado nao definido. ", E_WARNING); return FALSE; }
		$ret = $this->send("manage/method/insert/$this->login/$type/$phone/".urlencode($desc));
		print_r($ret);
		return $ret->status;
	}


/**
* Cadastra dispositivo do usuario
* 
* @param int $methodid ID do dispositivo a ser removido
* @return int Status Code da operacao retornado pelo WebService
* @access public
*/	 

 public function deleteDevice($methodid) {
 	 	if(empty($this->login)) { trigger_error("Login do usuario a ser manipulado nao definido. ", E_WARNING); return FALSE; }
		$ret = $this->send("manage/method/delete/$this->login/$methodid");
		return $ret->status;
	}


/**
* Verifica se determinado dispositivo (methodid) pertence a um usuario
* 
* @param int $methodid ID do dispositivo a ser verificado
* @return int Status Code da operacao retornado pelo WebService
* @access public
*/	 

 public function checkMethod($methodid) {
 	if(empty($this->login)) { trigger_error("Login do usuario a ser manipulado nao definido. ", E_WARNING); return FALSE; }
		$ret = $this->send("manage/method/check/$this->login/$methodid");
		return ($ret->status == 0)?true:$ret->status;
	}


/**
* Verifica se determinado login esta cadastrado na base de usuarios do Pontopass
* 
* @param int $methodid ID do dispositivo a ser verificado
* @return int Status Code da operacao retornado pelo WebService
* @access public
*/	 

 public function exists() {
 	if(empty($this->login)) { trigger_error("Login do usuario a ser manipulado nao definido. ", E_WARNING); return FALSE; }
		$ret = $this->send("manage/user/check/$this->login");
		return ($ret->status == 0)?true:false;
	}



/**
* Lista dispositivos de um usuario
* 
* @return object Relacao de Dispositivos do Usuario
* @access public
*/	 

 public function listMethods() {
 	 	if(empty($this->login)) { trigger_error("Login do usuario a ser manipulado nao definido. ", E_WARNING); return FALSE; }
		$ret = $this->send("manage/method/list/$this->login");
		return $ret;
	}	


}



 // ====================================================================================



?>
