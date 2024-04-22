<?php 
function loadDatabaseSettings($path){
    $string = file_get_contents($path);
    $json_a = json_decode($string, true);
    return $json_a;
}

// Utilizando la nueva ruta del archivo db.json
$dbcnf = loadDatabaseSettings('../info/db.json');
function getToken(){
	//creamos el objeto fecha y obtuvimos la cantidad de segundos desde el 1ª enero 1970
	$fecha = date_create();
	$tiempo = date_timestamp_get($fecha);
	//vamos a generar un numero aleatorio
	$numero = mt_rand();
	//vamos a generar ua cadena compuesta
	$cadena = ''.$numero.$tiempo;
	// generar una segunda variable aleatoria
	$numero2 = mt_rand();
	// generar una segunda cadena compuesta
	$cadena2 = ''.$numero.$tiempo.$numero2;
	// generar primer hash en este caso de tipo sha1
	$hash_sha1 = sha1($cadena);
	// generar segundo hash de tipo MD5 
	$hash_md5 = md5($cadena2);
	return substr($hash_sha1,0,20).$hash_md5.substr($hash_sha1,20);
}

require 'vendor/autoload.php';
$f3 = \Base::instance();
/*
$f3->route('GET /',
	function() {
		echo 'Hello, world!';
	}
);
$f3->route('GET /saludo/@nombre',
	function($f3) {
		echo 'Hola '.$f3->get('PARAMS.nombre');
	}
);
*/ 
// Registro
/*
 * Este Registro recibe un JSON con el siguiente formato
 * 
 * { 
 *		"uname": "XXX",
 *		"email": "XXX",
 * 		"password": "XXX"
 * }
 * */

$f3->route('POST /Registro',
	function($f3) {
		$dbcnf = loadDatabaseSettings('../info/db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
		);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		/////// obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('uname',$jsB) && array_key_exists('email',$jsB) && array_key_exists('password',$jsB);
		// TODO checar si estan vacio los elementos del json
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		// TODO validar correo en json
		// TODO Control de error de la $DB
		try {
			$R = $db->prepare('INSERT INTO Usuario (uname, email, password) VALUES (?, ?, ?)');
			$R->bindParam(1, $jsB['uname']);
			$R->bindParam(2, $jsB['email']);
			$hashedPassword = password_hash($jsB['password'], PASSWORD_DEFAULT);
			$R->bindParam(3, $hashedPassword);
			$R->execute();
		} catch (Exception $e) {
			error_log("Error: " . $e->getMessage()); 
			echo '{"R":-2}';
			return;
		}
		echo "{\"R\":0,\"D\":".var_export($R,TRUE)."}";
	}
);





/*
 * Este Registro recibe un JSON con el siguiente formato
 * 
 * { 
 *		"uname": "XXX",
 * 		"password": "XXX"
 * }
 * 
 * Debe retornar un Token 
 * */


$f3->route('POST /Login',
	function($f3) {
		$dbcnf = loadDatabaseSettings('../info/db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
		);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		/////// obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('uname',$jsB) && array_key_exists('password',$jsB);
		// TODO checar si estan vacio los elementos del json
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		// TODO validar correo en json
		// TODO Control de error de la $DB
		try {
			$R = $db->prepare('SELECT id, password FROM Usuario WHERE uname = :uname');
			$R->bindValue(':uname', $jsB['uname']);
			$R->execute();
		} catch (Exception $e) {
			error_log("Error: " . $e->getMessage()); 
			echo '{"R":-2}';
			return;
		}

		if ($R->rowCount() == 0) {
			echo '{"R":-3}';
			return;
		}
		$userData = $R->fetch(PDO::FETCH_ASSOC);
		$storedHash = $userData['password'];
		if (password_verify($jsB['password'], $storedHash)) {
			// La contraseña es correcta
			// Generar un nuevo token de acceso y almacenarlo en la base de datos
			$T = getToken();
			$db->exec('Delete from AccesoToken where id_Usuario = "'.$userData['id'].'";');
			$query = 'INSERT INTO AccesoToken (id_Usuario, token, fecha) VALUES (:id, :token, NOW())';
			$R = $db->prepare($query);
			$R->bindValue(':id', $userData['id']);
			$R->bindValue(':token', $T);
			$R->execute();
			echo "{\"R\":0,\"D\":\"".$T."\"}";
		} else {
			// La contraseña es incorrecta
			
			echo '{"R":-4}';
			return;
		}
	});


/*
 * Este subirimagen recibe un JSON con el siguiente formato
 * 
 * { 
 * 		"token: "XXX"
 *		"name": "XXX",
 * 		"data": "XXX",
 * 		"ext": "PNG"
 * }
 * 
 * Debe retornar codigo de estado
 * */

$f3->route('POST /Imagen',
	function($f3) {
		//Directorio
		if (!file_exists('tmp')) {
			mkdir('tmp');
		}
		if (!file_exists('img')) {
			mkdir('img');
		}
		/////// obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('name',$jsB) && array_key_exists('data',$jsB) && array_key_exists('ext',$jsB) && array_key_exists('token',$jsB);
		// TODO checar si estan vacio los elementos del json
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		
		$dbcnf = loadDatabaseSettings('../info/db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
		);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		// Validar si el usuario esta en la base de datos
		$TKN = $jsB['token'];
		
		try {
			$query = 'SELECT id_Usuario FROM AccesoToken WHERE token = :token';
			$R = $db->prepare($query);
			$R->bindValue(':token', $TKN);
			$R->execute();
		} catch (Exception $e) {
			error_log("Error: " . $e->getMessage()); 
			echo '{"R":-2}';
			return;
		}
		$id_Usuario = $R[0]['id_Usuario'];
		file_put_contents('tmp/'.$id_Usuario,base64_decode($jsB['data']));
		$jsB['data'] = '';
		////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////
		// Guardar info del archivo en la base de datos
		$query = 'INSERT INTO Imagen (name, ruta, id_Usuario) VALUES (:name, "img/", :idUsuario)';
		$R = $db->prepare($query);
		$R->bindValue(':name', $jsB['name']);
		$R->bindValue(':idUsuario', $id_Usuario);
		$R->execute();
		////////////////////////////////////////////////////////////////////////////////////////////////////
		$query = 'SELECT MAX(id) AS idImagen FROM Imagen WHERE id_Usuario = :idUsuario';
		$R = $db->prepare($query);
		$R->bindValue(':idUsuario', $id_Usuario);
		$R->execute();
		/////////////////////////////////////////////////////////////////////////////////////////////////////
		$idImagen = $R[0]['idImagen'];
		// Preparar la consulta
		$query = 'UPDATE Imagen SET ruta = :ruta WHERE id = :idImagen';
		$R = $db->prepare($query);
		$rutaCompleta = 'img/' . $idImagen . '.' . $jsB['ext'];
		$R->bindValue(':ruta', $rutaCompleta);
		$R->bindValue(':idImagen', $idImagen);
		$R->execute();

		// Mover archivo a su nueva locacion
		rename('tmp/'.$id_Usuario,'img/'.$idImagen.'.'.$jsB['ext']);
		echo "{\"R\":0,\"D\":".$idImagen."}";
	}
);
/*
 * Este Registro recibe un JSON con el siguiente formato
 * 
 * { 
 * 		"token: "XXX",
 * 		"id": "XXX"
 * }
 * 
 * Debe retornar un Token 
 * */

$f3->route('POST /Descargar',
 function($f3) {
	 $dbcnf = loadDatabaseSettings('db.json');
	 $db = new DB\SQL(
		 'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
		 $dbcnf['user'],
		 $dbcnf['password']
	 );
	 $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	 
	 // Obtener el cuerpo de la petición
	 $body = $f3->get('BODY');
	 $requestData = json_decode($body, true);
	 
	 // Verificar que el token y el ID de la imagen estén presentes en la solicitud
	 if (!isset($requestData['token']) || !isset($requestData['id'])) {
		 echo '{"R":-1}';
		 return;
	 }
	 
	 // Obtener el token y el ID de la imagen desde la solicitud
	 $token = $requestData['token'];
	 $idImagen = $requestData['id'];
	 
	 // Verificar la validez del token y obtener el ID de usuario asociado
	 try {
		 $query = 'SELECT id_Usuario FROM AccesoToken WHERE token = :token';
		 $stmt = $db->prepare($query);
		 $stmt->bindParam(':token', $token);
		 $stmt->execute();
		 $result = $stmt->fetch(PDO::FETCH_ASSOC);
		 
		 if (!$result) {
			 echo '{"R":-2}';
			 return;
		 }
		 
		 $idUsuario = $result['id_Usuario'];
		 
	 } catch (Exception $e) {
		 error_log("Error: " . $e->getMessage()); 
		 echo '{"R":-2}';
		 return;
	 }
	 
	 // Verificar si el usuario tiene permiso para descargar la imagen
	 try {
		 $query = 'SELECT id_Usuario FROM Imagen WHERE id = :idImagen';
		 $stmt = $db->prepare($query);
		 $stmt->bindParam(':idImagen', $idImagen);
		 $stmt->execute();
		 $result = $stmt->fetch(PDO::FETCH_ASSOC);
		 
		 if (!$result || $result['id_Usuario'] != $idUsuario) {
			 echo '{"R":-3}';
			 return;
		 }
		 
	 } catch (Exception $e) {
		 error_log("Error: " . $e->getMessage()); 
		 echo '{"R":-3}';
		 return;
	 }
	 
	 // Obtener la ruta de la imagen desde la base de datos
	 try {
		 $query = 'SELECT name, ruta FROM Imagen WHERE id = :idImagen';
		 $stmt = $db->prepare($query);
		 $stmt->bindParam(':idImagen', $idImagen);
		 $stmt->execute();
		 $result = $stmt->fetch(PDO::FETCH_ASSOC);
		 
		 if (!$result) {
			 echo '{"R":-4}';
			 return;
		 }
		 
		 $name = $result['name'];
		 $ruta = $result['ruta'];
		 
	 } catch (Exception $e) {
		 error_log("Error: " . $e->getMessage()); 
		 echo '{"R":-4}';
		 return;
	 }
	 
	 // Descargar el archivo
	 $web = \Web::instance();
	 $info = pathinfo($ruta);
	 $web->send($ruta, NULL, 0, TRUE, $name.'.'.$info['extension']);
 }
);


$f3->run();


?>
