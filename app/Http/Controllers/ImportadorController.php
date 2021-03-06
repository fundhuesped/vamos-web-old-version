<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Excel;
use Input;
use Storage;
use DB;
use Config;
use App\Pais;
use App\Provincia;
use App\Partido;
use App\Places;
use App\Ciudad;
use App\Evaluation;
use League\Csv\Writer;
use League\Csv\Reader;
use Session;
use Image;
use ImageServiceProvider;
use Validator;
use Redirect;
use Exception;
use App\Exceptions\CustomException;
use App\Exceptions\ImporterException;
use App\Exceptions\CsvException;


use App\PlaceLog;
use PHPExcel_Cell;

use SplTempFileObject;
use SplFileObject;
use SplFileInfo;
use Auth;

class ImportadorController extends Controller {

	public $csvColumns = 'id,establecimiento,tipo,calle,altura,piso_dpto,cruce,barrio_localidad,ciudad,partido_comuna,provincia_region,pais,aprobado,observacion,formattedaddress,latitude,longitude,habilitado,confidence,condones,prueba,mac,ile,dc,ssr,es_rapido,tel_distrib,mail_distrib,horario_distrib,responsable_distrib,web_distrib,ubicacion_distrib,comentarios_distrib,tel_testeo,mail_testeo,horario_testeo,responsable_testeo,web_testeo,ubicacion_testeo,observaciones_testeo,tel_mac,mail_mac,horario_mac,responsable_mac,web_mac,ubicacion_mac,comentarios_mac,tel_ile,mail_ile,horario_ile,responsable_ile,web_ile,ubicacion_ile,comentarios_ile,tel_dc,mail_dc,horario_dc,responsable_dc,web_dc,ubicacion_dc,comentarios_dc,tel_ssr,mail_ssr,horario_ssr,responsable_ssr,web_ssr,ubicacion_ssr,comentarios_ssr,servicetype_condones,servicetype_prueba,servicetype_mac,servicetype_ile,servicetype_dc,servicetype_ssr,friendly_condones,friendly_prueba,friendly_mac,friendly_ile,friendly_dc,friendly_ssr';
	public $csvColumns_arrayFormat = array('id','establecimiento','tipo','calle','altura','piso_dpto','cruce','barrio_localidad','ciudad','partido_comuna','provincia_region','pais','aprobado','observacion','formattedaddress','latitude','longitude','habilitado','confidence','condones','prueba','mac','ile','dc','ssr','es_rapido','tel_distrib','mail_distrib','horario_distrib','responsable_distrib','web_distrib','ubicacion_distrib','comentarios_distrib','tel_testeo','mail_testeo','horario_testeo','responsable_testeo','web_testeo','ubicacion_testeo','observaciones_testeo','tel_mac','mail_mac','horario_mac','responsable_mac','web_mac','ubicacion_mac','comentarios_mac','tel_ile','mail_ile','horario_ile','responsable_ile','web_ile','ubicacion_ile','comentarios_ile','tel_dc','mail_dc','horario_dc','responsable_dc','web_dc','ubicacion_dc','comentarios_dc','tel_ssr','mail_ssr','horario_ssr','responsable_ssr','web_ssr','ubicacion_ssr','comentarios_ssr','servicetype_condones','servicetype_prueba','servicetype_mac','servicetype_ile','servicetype_dc','servicetype_ssr','friendly_condones','friendly_prueba','friendly_mac','friendly_ile','friendly_dc','friendly_ssr');

	public $placeMainServices = array('condones','prueba','mac','ile','dc','ssr');
	public $placeOptServices = array('es_rapido' => 'prueba');
	// public $placeBDMainServices = array('distrib','testeo','vac','ile','infectologia','ssr');
	// public $placeServiceDetails = array('tel','mail','horario','responsable','web','ubicacion','comentarios');
	public $placeFriendlys = array('friendly_condones','friendly_prueba','friendly_mac','friendly_ile','friendly_ssr','friendly_dc');
	public $placeServicetypes = array('servicetype_condones','servicetype_prueba','servicetype_mac','servicetype_ile','servicetype_dc','servicetype_ssr');

	public function debug_to_console( $data ) {
		$output = $data;
		if ( is_array( $output ) )
			$output = implode( ',', $output);

		echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
	}

	public function preparePlaceToImport($book,$status){
		$id = $book['id'];									//save 'id' data
		array_shift($book); 								//pop the 'id' column
		$book = array_merge(['placeId' => $id], $book);		//push the 'placeId' column
		$book = array_merge(['status' => $status], $book);	//push the 'status' value
		return $book;
	}

	public function insertDataIntoCsv_places($data){

		$csv = Writer::createFromFileObject(new SplTempFileObject());
		//header
		$csv->insertOne($this->csvColumns);
	    //body
		foreach ($data as $key => $p) {
			$p = (array)$p;
			$p['condones']= $this->parseToExport($p['condones']);
			$p['prueba']= $this->parseToExport($p['prueba']);
			$p['vacunatorio']= $this->parseToExport($p['vacunatorio']);
			$p['ile']= $this->parseToExport($p['ile']);
			$p['ssr']= $this->parseToExport($p['ssr']);
			$p['infectologia']= $this->parseToExport($p['infectologia']);
			$p['es_rapido']= $this->parseToExport($p['es_rapido']);
			$p['friendly_ile']= $this->parseToExport($p['friendly_ile']);
			$p['friendly_mac']= $this->parseToExport($p['friendly_mac']);
			$p['friendly_condones']= $this->parseToExport($p['friendly_condones']);
			$p['friendly_prueba']= $this->parseToExport($p['friendly_prueba']);
			$p['friendly_ssr']= $this->parseToExport($p['friendly_ssr']);
			$p['friendly_dc']= $this->parseToExport($p['friendly_dc']);


			if (!isset($p['nombre_ciudad'])) { $p['nombre_ciudad'] = $p['ciudad']; }
			if (!isset($p['nombre_partido'])) { $p['nombre_partido'] = $p['partido_comuna']; }
			if (!isset($p['nombre_provincia'])) { $p['nombre_provincia'] = $p['provincia_region']; }
			if (!isset($p['nombre_pais'])) { $p['nombre_pais'] = $p['pais']; }
			if (!isset($p['formattedaddress'])) { $p['formattedaddress'] = ''; }
			if (!isset($p['uploader_name'])) { $p['uploader_name'] = ''; }
			if (!isset($p['uploader_tel'])) { $p['uploader_tel'] = ''; }
			if (!isset($p['uploader_email'])) { $p['uploader_email'] = ''; }

			$csv->insertOne([
				$p['placeId'],
				$p['establecimiento'],
				$p['tipo'],
				$p['calle'],
				$p['altura'],
				$p['piso_dpto'],
				$p['cruce'],
				$p['barrio_localidad'],
				$p['nombre_ciudad'],
				$p['nombre_partido'],
				$p['nombre_provincia'],
				$p['nombre_pais'],
				$p['aprobado'],
				$p['observacion'],
				$p['formattedaddress'],
				$p['latitude'],
				$p['longitude'],
				$p['habilitado'],
				$p['confidence'],
				$p['condones'],
				$p['prueba'],
				$p['vacunatorio'],
				$p['ile'],
				$p['infectologia'],
				$p['ssr'],
				$p['es_rapido'],
				$p['tel_distrib'],
				$p['mail_distrib'],
				$p['horario_distrib'],
				$p['responsable_distrib'],
				$p['web_distrib'],
				$p['ubicacion_distrib'],
				$p['comentarios_distrib'],
				$p['tel_testeo'],
				$p['mail_testeo'],
				$p['horario_testeo'],
				$p['responsable_testeo'],
				$p['web_testeo'],
				$p['ubicacion_testeo'],
				$p['observaciones_testeo'],
				$p['tel_vac'],
				$p['mail_vac'],
				$p['horario_vac'],
				$p['responsable_vac'],
				$p['web_vac'],
				$p['ubicacion_vac'],
				$p['comentarios_vac'],
				$p['tel_ile'],
				$p['mail_ile'],
				$p['horario_ile'],
				$p['responsable_ile'],
				$p['web_ile'],
				$p['ubicacion_ile'],
				$p['comentarios_ile'],
				$p['tel_infectologia'],
				$p['mail_infectologia'],
				$p['horario_infectologia'],
				$p['responsable_infectologia'],
				$p['web_infectologia'],
				$p['ubicacion_infectologia'],
				$p['comentarios_infectologia'],
				$p['tel_ssr'],
				$p['mail_ssr'],
				$p['horario_ssr'],
				$p['responsable_ssr'],
				$p['web_ssr'],
				$p['ubicacion_ssr'],
				$p['comentarios_ssr'],
				strtolower($p['servicetype_condones']),
				strtolower($p['servicetype_prueba']),
				strtolower($p['servicetype_mac']),
				strtolower($p['servicetype_ile']),
				strtolower($p['servicetype_dc']),
				strtolower($p['servicetype_ssr']),
				$p['friendly_condones'],
				$p['friendly_prueba'],
				$p['friendly_mac'],
				$p['friendly_ile'],
				$p['friendly_dc'],
				$p['friendly_ssr'],
				$p['uploader_name'],
				$p['uploader_email'],
				$p['uploader_tel'],
			]);
		}
		return $csv;
	}

	public function insertArraObejectsDataIntoCsv_places($data){

		$csv = Writer::createFromFileObject(new SplTempFileObject());
		//header
		$csv->insertOne($this->csvColumns);
	    //body
		foreach ($data as $key => $p) {
			$p->condones = $this->parseToExport($p->condones);
			$p->prueba= $this->parseToExport($p->prueba);
			$p->vacunatorio= $this->parseToExport($p->vacunatorio);
			$p->ile = $this->parseToExport($p->ile);
			$p->ssr = $this->parseToExport($p->ssr);
			$p->infectologia= $this->parseToExport($p->infectologia);
			$p->es_rapido= $this->parseToExport($p->es_rapido);
			$p->friendly_ile= $this->parseToExport($p->friendly_ile);
			$p->friendly_mac= $this->parseToExport($p->friendly_mac);
			$p->friendly_condones= $this->parseToExport($p->friendly_condones);
			$p->friendly_prueba= $this->parseToExport($p->friendly_prueba);
			$p->friendly_ssr= $this->parseToExport($p->friendly_ssr);
			$p->friendly_dc= $this->parseToExport($p->friendly_dc);

			$csv->insertOne([
				$p->placeId,
				$p->establecimiento,
				$p->tipo,
				$p->calle,
				$p->altura,
				$p->piso_dpto,
				$p->cruce,
				$p->barrio_localidad,
				$p->nombre_ciudad,
				$p->nombre_partido,
				$p->nombre_provincia,
				$p->nombre_pais,
				$p->aprobado,
				$p->observacion,
				$p->formattedAddress,
				$p->latitude,
				$p->longitude,
				$p->habilitado,
				$p->confidence,
				$p->condones,
				$p->prueba,
				$p->vacunatorio,
				$p->ile,
				$p->infectologia,
				$p->ssr,
				$p->es_rapido,
				$p->tel_distrib,
				$p->mail_distrib,
				$p->horario_distrib,
				$p->responsable_distrib,
				$p->web_distrib,
				$p->ubicacion_distrib,
				$p->comentarios_distrib,
				$p->tel_testeo,
				$p->mail_testeo,
				$p->horario_testeo,
				$p->responsable_testeo,
				$p->web_testeo,
				$p->ubicacion_testeo,
				$p->observaciones_testeo,
				$p->tel_vac,
				$p->mail_vac,
				$p->horario_vac,
				$p->responsable_vac,
				$p->web_vac,
				$p->ubicacion_vac,
				$p->comentarios_vac,
				$p->tel_ile,
				$p->mail_ile,
				$p->horario_ile,
				$p->responsable_ile,
				$p->web_ile,
				$p->ubicacion_ile,
				$p->comentarios_ile,
				$p->tel_infectologia,
				$p->mail_infectologia,
				$p->horario_infectologia,
				$p->responsable_infectologia,
				$p->web_infectologia,
				$p->ubicacion_infectologia,
				$p->comentarios_infectologia,
				$p->tel_ssr,
				$p->mail_ssr,
				$p->horario_ssr,
				$p->responsable_ssr,
				$p->web_ssr,
				$p->ubicacion_ssr,
				$p->comentarios_ssr,
				strtolower($p->servicetype_condones),
				strtolower($p->servicetype_prueba),
				strtolower($p->servicetype_mac),
				strtolower($p->servicetype_ile),
				strtolower($p->servicetype_dc),
				strtolower($p->servicetype_ssr),
				$p->friendly_condones,
				$p->friendly_prueba,
				$p->friendly_mac,
				$p->friendly_ile,
				$p->friendly_dc,
				$p->friendly_ssr,
				$p->uploader_name,
				$p->uploader_email,
				$p->uploader_tel
			]);
		}
	        //descarga
		return $csv;
	}

	function exportDataByKey(string $key){
		$data = 0;
		if (session($key) != null)
			$data = session($key);

		$csvname = 'DONDE - '.$key;
		if (session('csvname') != null)
			$csvname = $csvname." - ".session('csvname');

		$csv = $this->insertDataIntoCsv_places($data);
		$csv->output($csvname);		
	}

	public function exportNuevos(Request $request){
		$this->exportDataByKey('datosNuevos');
	}

	public function exportRepetidos(Request $request){
		$this->exportDataByKey('datosRepetidos');
	}

	public function exportIncompletos(Request $request){
		$this->exportDataByKey('datosIncompletos');
	}

	public function exportActualizar(Request $request){
		$this->exportDataByKey('datosActualizar');
	}

	public function exportUnificar(Request $request){
		$this->exportDataByKey('datosUnificar');
	}

	public function exportBC(Request $request){
		$this->exportDataByKey('datosDescartados');
	}

	public function index()
	{
		return view('panel.importer.index');
	}

	public function picker()
	{
		return view('panel.importer.picker');
	}

	function convertToISOCharset($string)
	{
		$val = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
		return $string;
	}

	function convertfromISOCharset($string)
	{
		$val = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
		return $string;
	}

	public function parseToExport($string){
		if ($string == 1)  {
			$string = "SI";
		}
		else{
			$string = "NO";
		}
		return $this->convertToISOCharset($string);
	}

    /**
     * Retrive an object with arrays of id's service evalautons.
     *
     * @param  string  $id
     * @return void
     */
    public function exportarEvaluacionesPorServicios($id){
    	$evaluations = new EvaluationRESTController;
    	$evals = $evaluations->showPanelServiceEvaluations($id);

    	$copyCSV = ucwords($id).".csv";

    	$csv = Writer::createFromFileObject(new SplTempFileObject());

		//header
    	$csv->insertOne('Id Evaluaci??n,??Que busc???,??Se lo dieron?,Informaci??n clara,Privacidad,Edad,G??nero,Puntuaci??n,Comentario,??Aprobado?,id,Fecha');

        //body
    	for ($i=0; $i < sizeof($evals); $i++) {

    		$evals[$i]->info_ok = $this->parseToExport($evals[$i]->info_ok);
    		$evals[$i]->privacidad_ok = $this->parseToExport($evals[$i]->privacidad_ok);
    		$evals[$i]->aprobado = $this->parseToExport($evals[$i]->aprobado);

    		$csv->insertOne([
    			$evals[$i]->id,
    			$evals[$i]->que_busca,
    			$evals[$i]->le_dieron,
    			$evals[$i]->info_ok,
    			$evals[$i]->privacidad_ok,
    			$evals[$i]->edad,
    			$evals[$i]->genero,
    			$evals[$i]->voto,
    			$evals[$i]->comentario,
    			$evals[$i]->aprobado,
    			$evals[$i]->idPlace,
    			$evals[$i]->created_at ]);
    	}

    	$csv->output($copyCSV);
    }

    public function exportarEvaluaciones($id){
    	$evaluations = new EvaluationRESTController;
    	$evals = $evaluations->showPanelEvaluations($id);

    	$csv = Writer::createFromFileObject(new SplTempFileObject());
    	
		//header
    	$csv->insertOne('Id Evaluaci??n,??Que busc???,??Se lo dieron?,Informaci??n clara,Privacidad,Edad,G??nero,Puntuaci??n,Comentario,??Aprobado?,id,Fecha');


        //body
    	for ($i=0; $i < sizeof($evals); $i++) {

    		$evals[$i]->info_ok = $this->parseToExport($evals[$i]->info_ok);
    		$evals[$i]->privacidad_ok = $this->parseToExport($evals[$i]->privacidad_ok);
    		$evals[$i]->aprobado = $this->parseToExport($evals[$i]->aprobado);

    		$csv->insertOne([
    			$evals[$i]->id,
    			$evals[$i]->que_busca,
    			$evals[$i]->le_dieron,
    			$evals[$i]->info_ok,
    			$evals[$i]->privacidad_ok,
    			$evals[$i]->edad,
    			$evals[$i]->genero,
    			$evals[$i]->voto,
    			$evals[$i]->comentario,
    			$evals[$i]->aprobado,
    			$evals[$i]->idPlace,
    			$evals[$i]->created_at ]);
    	}
    	
    	$csv->output('Evaluaciones.csv');
    }


    public function parseService($service){
    	$resu = "Sin especificar";
    	$serviceCtrl = new ServiceController();
    	$services = $serviceCtrl->getAllServices();
    	foreach ($services as $s) {
    		if ($s->shortname == $service) $resu = $s->name;
    	}
    	return $resu;

    }

	//recibo un int
	//chequeo si esta entre 10 y 19 para crear la columna auxilar edad_especifica
    public function parseEdadEspecifica($edad){
    	$edadEspecifica="";
    	if ( ($edad > 9) && ($edad < 20) ){
    		$edadEspecifica = "Entre  10 y 19";
    	}
    	else{
    		$edadEspecifica = $edad;
    	}

    	return $edadEspecifica;

    }

    public function exportarEvaluacionesFull($lang){

    	if($lang == null){
    		$lang = 'es';
    	}

    	$evaluations = DB::table('evaluation')
    	->join('places','evaluation.idPlace','=','places.placeId')
    	->join('pais','pais.id','=','places.idPais')
    	->join('provincia','provincia.id','=','places.idProvincia')
    	->join('partido','partido.id','=','places.idPartido')
    	->join('ciudad', 'ciudad.id', '=', 'places.idCiudad')
    	->select('evaluation.*','places.*','ciudad.nombre_ciudad','partido.nombre_partido','provincia.nombre_provincia','pais.nombre_pais','evaluation.created_at as fechaEvaluacion', 'evaluation.aprobado as aprobadoEval')
    	->get();


    	if (sizeof($evaluations) > 0){
    		$copyCSV = "evaluaciones.csv";
    	}
    	else {
    		$copyCSV = "nodata.csv";
    	}

    	$csv = Writer::createFromFileObject(new SplTempFileObject());
    	
		// HEADER

    	$csv->insertOne('id_establecimiento,nombre_establecimiento,direccion,barrio_localidad,ciudad,partido,provincia,pais,condones,prueba,vacunatorio,ile,infectologia,ssr,es_rapido,id_evaluacion,??que_busco?,??se_lo_dieron?,informacion_clara,privacidad,gratuito,comodo,informaci??n_vacunas,edad,genero,puntuacion,comentario,??aprobado?,fecha,servicio,nombre,email,telefono');

        // BODY
    	foreach ($evaluations as $key => $p) {
    		$p = (array)$p;
    		$p['service']= $this->parseService($p['service']);
    		$p['es_gratuito']= $this->parseToExport($p['es_gratuito']);
    		$p['condones']= $this->parseToExport($p['condones']);
    		$p['prueba']= $this->parseToExport($p['prueba']);
    		$p['vacunatorio']= $this->parseToExport($p['vacunatorio']);
    		$p['ile']= $this->parseToExport($p['ile']);
    		$p['ssr']= $this->parseToExport($p['ssr']);
    		$p['infectologia']= $this->parseToExport($p['infectologia']);
    		$p['es_rapido']= $this->parseToExport($p['es_rapido']);
    		$p['info_ok']= $this->parseToExport($p['info_ok']);
    		$p['privacidad_ok']= $this->parseToExport($p['privacidad_ok']);
    		$p['aprobadoEval']= $this->parseToExport($p['aprobadoEval']);
    		$p['comodo']= $this->parseToExport($p['comodo']);
    		$p['informacion_vacunas']= $this->parseToExport($p['informacion_vacunas']);
    		$p['direccion']= $p['calle']." ".$p['altura'];

    		$csv->insertOne([
    			$p['placeId'],
    			$p['establecimiento'],
    			$p['direccion'],
    			$p['barrio_localidad'],
    			$p['nombre_ciudad'],
    			$p['nombre_partido'],
    			$p['nombre_provincia'],
    			$p['nombre_pais'],
    			$p['condones'],
    			$p['prueba'],
    			$p['vacunatorio'],
    			$p['ile'],
    			$p['infectologia'],
    			$p['ssr'],
    			$p['es_rapido'],
    			$p['id'],
    			$p['que_busca'],
    			$p['le_dieron'],
    			$p['info_ok'],
    			$p['privacidad_ok'],
    			$p['es_gratuito'],
    			$p['comodo'],
    			$p['informacion_vacunas'],
    			$p['edad'],
    			$p['genero'],
    			$p['voto'],
    			"\"" . $p['comentario']  . "\"" ,
    			$p['aprobadoEval'],
    			$p['fechaEvaluacion'],
    			$p['service'],
    			$p['name'],
    			$p['email'],
    			$p['tel']

    		]);
    	}

    	$csv->output($copyCSV);
    }

    public function exportarEvaluacionesByEstado($a){

    	$evaluations = DB::table('evaluation')
    	->join('places','evaluation.idPlace','=','places.placeId')
    	->join('pais','pais.id','=','places.idPais')
    	->join('provincia','provincia.id','=','places.idProvincia')
    	->join('partido','partido.id','=','places.idPartido')
    	->join('ciudad', 'ciudad.id', '=', 'places.idCiudad')
    	->where('evaluation.aprobado', $a)
    	->select('evaluation.*','places.*','ciudad.nombre_ciudad','partido.nombre_partido','provincia.nombre_provincia','pais.nombre_pais','evaluation.created_at as fechaEvaluacion', 'evaluation.aprobado as aprobadoEval')
    	->get();


    	if (sizeof($evaluations) > 0){
    		if($a == 1){
    			$copyCSV = "evaluacionesAprobadas.csv";
    		}
    		else{
    			$copyCSV = "evaluacionesRechazadas.csv";
    		}
    		
    	}
    	else {
    		$copyCSV = "nodata.csv";
    	}

    	$csv = Writer::createFromFileObject(new SplTempFileObject());
    	
		// HEADER

    	$csv->insertOne('id_establecimiento,nombre_establecimiento,direccion,barrio_localidad,ciudad,partido,provincia,pais,condones,prueba,vacunatorio,ile,infectologia,ssr,es_rapido,id_evaluacion,??que_busco?,??se_lo_dieron?,informacion_clara,privacidad,gratuito,comodo,informaci??n_vacunas,edad,genero,puntuacion,comentario,??aprobado?,fecha,servicio,nombre,email,telefono');

        // BODY
    	foreach ($evaluations as $key => $p) {
    		$p = (array)$p;
    		$p['service']= $this->parseService($p['service']);
    		$p['es_gratuito']= $this->parseToExport($p['es_gratuito']);
    		$p['condones']= $this->parseToExport($p['condones']);
    		$p['prueba']= $this->parseToExport($p['prueba']);
    		$p['vacunatorio']= $this->parseToExport($p['vacunatorio']);
    		$p['ile']= $this->parseToExport($p['ile']);
    		$p['ssr']= $this->parseToExport($p['ssr']);
    		$p['infectologia']= $this->parseToExport($p['infectologia']);
    		$p['es_rapido']= $this->parseToExport($p['es_rapido']);
    		$p['info_ok']= $this->parseToExport($p['info_ok']);
    		$p['privacidad_ok']= $this->parseToExport($p['privacidad_ok']);
    		$p['aprobadoEval']= $this->parseToExport($p['aprobadoEval']);
    		$p['comodo']= $this->parseToExport($p['comodo']);
    		$p['informacion_vacunas']= $this->parseToExport($p['informacion_vacunas']);
    		$p['direccion']= $p['calle']." ".$p['altura'];

    		$csv->insertOne([
    			$p['placeId'],
    			$p['establecimiento'],
    			$p['direccion'],
    			$p['barrio_localidad'],
    			$p['nombre_ciudad'],
    			$p['nombre_partido'],
    			$p['nombre_provincia'],
    			$p['nombre_pais'],
    			$p['condones'],
    			$p['prueba'],
    			$p['vacunatorio'],
    			$p['ile'],
    			$p['infectologia'],
    			$p['ssr'],
    			$p['es_rapido'],
    			$p['id'],
    			$p['que_busca'],
    			$p['le_dieron'],
    			$p['info_ok'],
    			$p['privacidad_ok'],
    			$p['es_gratuito'],
    			$p['comodo'],
    			$p['informacion_vacunas'],
    			$p['edad'],
    			$p['genero'],
    			$p['voto'],
    			"\"" . $p['comentario']  . "\"" ,
    			$p['aprobadoEval'],
    			$p['fechaEvaluacion'],
    			$p['service'],
    			$p['name'],
    			$p['email'],
    			$p['tel']

    		]);
    	}

    	$csv->output($copyCSV);
    }

    public function show($a){
    	self::exportarEvaluacionesByEstado($a);
    }

//=====================================================================================
//en caso de que escriba (segunda opt)
    public function exportarPanelSearch($search){
    	$placesController = new PlacesRESTController;
    	$places = $placesController->search($search);
    	$csv = $this->insertArraObejectsDataIntoCsv_places($places);

    	$csv->output('Establecimientos con ' . $search . '.csv');
    }

    public function exportarPanelEvalSearch($search){
    	$placesController = new PlacesRESTController;
    	$places = $placesController->search($search);

    	$csv = Writer::createFromFileObject(new SplTempFileObject());
		//header
    	$csv->insertOne('id-establecimiento,nombre-establecimiento,direccion,barrio_localidad,partido,provincia,pais,condones,prueba,vacunatorio,ile,infectologia,ssr,es_rapido,Id Evaluaci??n,??Que busc???,??Se lo dieron?,Informaci??n clara,Privacidad,Edad,G??nero,Puntuaci??n,Comentario,??Aprobado?,Fecha');

    	//body
    	foreach ($places as $key => $value) {

    		$evaluations = DB::table('evaluation')
    		->join('places','evaluation.idPlace','=','places.placeId')
    		->join('pais','pais.id','=','places.idPais')
    		->join('provincia','provincia.id','=','places.idProvincia')
    		->join('partido','partido.id','=','places.idPartido')
    		->where('evaluation.idPlace',$value->placeId)
    		->select('places.placeId','places.establecimiento','places.calle','places.altura','places.barrio_localidad','places.condones','places.prueba','places.vacunatorio','places.ile','places.ssr','places.infectologia','places.es_rapido','evaluation.id','evaluation.que_busca','evaluation.le_dieron','evaluation.info_ok','evaluation.privacidad_ok','evaluation.edad','evaluation.genero','evaluation.voto','evaluation.comentario','evaluation.aprobado','pais.nombre_pais','provincia.nombre_provincia','partido.nombre_partido','evaluation.created_at')
    		->get();

    		foreach ($evaluations as $p) {
    			$p = (array)$p;
    			$p['condones']= $this->parseToExport($p['condones']);
    			$p['prueba']= $this->parseToExport($p['prueba']);
    			$p['vacunatorio']= $this->parseToExport($p['vacunatorio']);
    			$p['ile']= $this->parseToExport($p['ile']);
    			$p['ssr']= $this->parseToExport($p['ssr']);
    			$p['infectologia']= $this->parseToExport($p['infectologia']);
    			$p['es_rapido']= $this->parseToExport($p['es_rapido']);
    			$p['info_ok']= $this->parseToExport($p['info_ok']);
    			$p['privacidad_ok']= $this->parseToExport($p['privacidad_ok']);
    			$p['aprobado']= $this->parseToExport($p['aprobado']);
    			$p['direccion']= $p['calle']." ".$p['altura'];

    			$csv->insertOne([
    				$p['placeId'],
    				$p['establecimiento'],
    				$p['direccion'],
    				$p['barrio_localidad'],
    				$p['nombre_partido'],
    				$p['nombre_provincia'],
    				$p['nombre_pais'],

    				$p['condones'],
    				$p['prueba'],
    				$p['vacunatorio'],
    				$p['ile'],
    				$p['infectologia'],
    				$p['ssr'],
    				$p['es_rapido'],
    				$p['id'],
    				$p['que_busca'],
    				$p['le_dieron'],
    				$p['info_ok'],
    				$p['privacidad_ok'],
    				$p['edad'],
    				$p['genero'],
    				$p['voto'],
    				"\"" . $p['comentario']  . "\"" ,
    				$p['aprobado'],
    				$p['created_at']
    			]);
    		}
    	}
    	$csv->output('Evaluaciones.csv');
    }

    public function activePlacesExport(Request $request){

    	$request_params = Input::all();
    	$idPais = $request_params['idPais'];
    	$idProvincia = $request_params['idProvincia'];
    	$idPartido = $request_params['idPartido'];
    	$idCiudad = $request_params['idCiudad'];
    	$placesController = new PlacesRESTController;
    	$places = $placesController->getAprobedPlaces($idPais, $idProvincia, $idPartido, $idCiudad);

    	if ((isset($places)) && (count($places) > 0)){

    		if($idPais == "null" && $idProvincia == "null" && $idPartido == "null" && $idCiudad == "null"){
    			// Export all active places
    			$copyCSV = "establecimientos_activos.csv";
    		}
    		else{
    			if($idPais != "null" && $idProvincia == "null" && $idPartido == "null" && $idCiudad == "null"){
    				// Export by country
    				$copyCSV = "establecimientos_".$places[0]->nombre_pais.".csv";
    			}
    			else{
    				if($idPais != "null" && $idProvincia != "null" && $idPartido == "null" && $idCiudad == "null"){
    					// Export by province
    					$copyCSV = "establecimientos_".$places[0]->nombre_provincia."_".$places[0]->nombre_pais.".csv";
    				}
    				else{
    					if($idPais != "null" && $idProvincia != "null" && $idPartido != "null" && $idCiudad == "null"){
    						// Export by party
    						$copyCSV = "establecimientos_".$places[0]->nombre_partido."_".$places[0]->nombre_provincia."_".$places[0]->nombre_pais.".csv";
    					}
    					else{
    						// Export by city
    						$copyCSV = "establecimientos_".$places[0]->nombre_ciudad."_".$places[0]->nombre_partido."_".$places[0]->nombre_provincia."_".$places[0]->nombre_pais.".csv";
    					}
    				}
    			}
    		}
    	}

    	else $copyCSV = "nodata.csv";

    	$csv = $this->insertArraObejectsDataIntoCsv_places($places);

    	$csv->output($copyCSV);
    }


    public function activePlacesEvaluationsExport(Request $request){

    	$request_params = Input::all();
    	$idPais = $request_params['idPais'];
    	$idProvincia = $request_params['idProvincia'];
    	$idPartido = $request_params['idPartido'];
    	$serviciosString = $request_params['selectedServiceList'];
    	$servicios = explode(',', $serviciosString);
    	$placesController = new PlacesRESTController;
    	$places = $placesController->showApprovedFilterByService($idPais,$idProvincia,$idPartido, $servicios);
    	if (count($places) > 0){
    		$copyCSV = "evaluaciones_".$places[0]->nombre_partido."_".$places[0]->nombre_provincia."_".$places[0]->nombre_pais.".csv";
    	}
    	else {
    		$copyCSV = "nodata.csv";
    	}
    	$csv = Writer::createFromFileObject(new SplTempFileObject());
			//header
    	$csv->insertOne('id-establecimiento,nombre-establecimiento,direccion,barrio_localidad,partido,provincia,pais,condones,prueba,vacunatorio,ile,infectologia,ssr,es_rapido,Id Evaluaci??n,??Que busc???,??Se lo dieron?,Informaci??n clara,Privacidad, Gratuito, C??modo, Informaci??n Vacunas Edad, Edad, Edad Especifica,G??nero,Puntuaci??n,Comentario,??Aprobado?,Fecha,Servicio');
			//body
    	foreach ($places as $key => $value) {

    		$evaluations = DB::table('evaluation')
    		->join('places','evaluation.idPlace','=','places.placeId')
    		->join('pais','pais.id','=','places.idPais')
    		->join('provincia','provincia.id','=','places.idProvincia')
    		->join('partido','partido.id','=','places.idPartido')
    		->where('evaluation.idPlace',$value->placeId)
    		->select('places.placeId','places.establecimiento','places.calle','places.altura','places.barrio_localidad','places.condones','places.prueba','places.vacunatorio','places.ile','places.ssr','places.infectologia','places.es_rapido','evaluation.id','evaluation.que_busca','evaluation.le_dieron','evaluation.info_ok','evaluation.privacidad_ok','evaluation.es_gratuito','evaluation.comodo','evaluation.informacion_vacunas','evaluation.edad','evaluation.genero','evaluation.voto','evaluation.comentario','evaluation.aprobado','pais.nombre_pais','provincia.nombre_provincia','partido.nombre_partido','evaluation.created_at','evaluation.service')
    		->get();

    		foreach ($evaluations as $p) {
    			$p = (array)$p;
    			$p['edadEspecifica']= $this->parseEdadEspecifica($p['edad']);
    			$p['condones']= $this->parseToExport($p['condones']);
    			$p['prueba']= $this->parseToExport($p['prueba']);
    			$p['vacunatorio']= $this->parseToExport($p['vacunatorio']);
    			$p['ile']= $this->parseToExport($p['ile']);
    			$p['ssr']= $this->parseToExport($p['ssr']);
    			$p['infectologia']= $this->parseToExport($p['infectologia']);
    			$p['es_rapido']= $this->parseToExport($p['es_rapido']);
    			$p['info_ok']= $this->parseToExport($p['info_ok']);
    			$p['privacidad_ok']= $this->parseToExport($p['privacidad_ok']);
    			$p['aprobado']= $this->parseToExport($p['aprobado']);
    			$p['direccion']= $p['calle']." ".$p['altura'];
    			$p['es_gratuito']= $this->parseToExport($p['es_gratuito']);
    			$p['service']= $this->parseService($p['service']);
    			$p['comodo']= $this->parseToExport($p['comodo']);
    			$p['informacion_vacunas']= $this->parseToExport($p['informacion_vacunas']);

    			$csv->insertOne([
    				$p['placeId'],
    				$p['direccion'],
    				$p['establecimiento'],
    				$p['barrio_localidad'],
    				$p['nombre_partido'],
    				$p['nombre_provincia'],
    				$p['nombre_pais'],

    				$p['condones'],
    				$p['prueba'],
    				$p['vacunatorio'],
    				$p['ile'],
    				$p['infectologia'],
    				$p['ssr'],
    				$p['es_rapido'],
    				$p['id'],
    				$p['que_busca'],
    				$p['le_dieron'],
    				$p['info_ok'],
    				$p['privacidad_ok'],
    				$p['es_gratuito'],
    				$p['comodo'],
    				$p['informacion_vacunas'],
    				$p['edadEspecifica'],
    				$p['edad'],
    				$p['genero'],
    				$p['voto'],
    				"\"" . $p['comentario']  . "\"" ,
    				$p['aprobado'],
    				$p['created_at'],
    				$p['service']
    			]);
    		}
    	}
    	$csv->output($copyCSV);
    }

	// Export filtered evaluations
    public function getFilteredEvaluations(Request $request){

    	$request_params = Input::all();
    	$idPais = $request_params['idPais'];
    	$idProvincia = $request_params['idProvincia'];
    	$idPartido = $request_params['idPartido'];
    	$idCiudad = $request_params['idCiudad'];
    	$aprob = $request_params['aprob'];

    	if($aprob == '-1'){
    		$aprob = 'null';
    	}

    	$evalController = new EvaluationRESTController;

    	if($idPais == 'null'){
    		/*if($aprob == '-1'){
    			$evals = $evalController->getAllFileteredEvaluations();
    		}
    		else{*/
    			$evals = $evalController->getAllFileteredEvaluations($aprob);
    		//}
    		}
    		else {
    			if($idProvincia == 'null'){
    				$evals = $evalController->getAllByCity($idPais,null,null,null,$aprob);
    			}
    			else{ 
    				if($idPartido == 'null'){
    					$evals = $evalController->getAllByCity($idPais,$idProvincia,null,null,$aprob);
    				}
    				else {
    					if($idCiudad == 'null'){
    						$evals = $evalController->getAllByCity($idPais,$idProvincia,$idPartido,null,$aprob);
    					}
    					else {
    						$evals = $evalController->getAllByCity($idPais,$idProvincia,$idPartido,$idCiudad,$aprob);
    					}
    				}
    			}
    		}

    		if (sizeof($evals) > 0){
    			$sufix = '';
    			if($aprob == '-1')  { $sufix = 'Todas'; } 
    			else if($aprob == '1') 	{ $sufix =  'Aprobadas';} 
    			else if($aprob == '0') 	{ $sufix =  'Rechazadas';} 
			// $sufix = '';
    			$copyCSV = "Donde - Evaluaciones ". $sufix . ".csv";
    		}
    		else {
    			$copyCSV = "NoData.csv";
    		}	

    		$csv = Writer::createFromFileObject(new SplTempFileObject());

		//header
    		$csv->insertOne('id_establecimiento,nombre_establecimiento,ciudad,partido,provincia,pais,id_evaluacion,??que_busco?,??se_lo_dieron?,informacion_clara,privacidad,gratuito,comodo,informaci??on_vacunas,edad,genero,puntuacion,comentario,??aprobado?,fecha,servicio,nombre,email,telefono');
		//body
    		foreach ($evals as $p) {

    			$p = (array)$p;
    			$p['edad']= $this->parseEdadEspecifica($p['edad']);
    			$p['info_ok']= $this->parseToExport($p['info_ok']);
    			$p['privacidad_ok']= $this->parseToExport($p['privacidad_ok']);
    			$p['aprobado']= $this->parseToExport($p['aprobado']);
    			$p['es_gratuito']= $this->parseToExport($p['es_gratuito']);
    			$p['service']= $this->parseService($p['service']);
    			$p['comodo']= $this->parseToExport($p['comodo']);
    			$p['informacion_vacunas']= $this->parseToExport($p['informacion_vacunas']);

    			$csv->insertOne([
    				$p['placeId'],
    				$p['establecimiento'],
    				$p['nombre_ciudad'],
    				$p['nombre_partido'],
    				$p['nombre_provincia'],
    				$p['nombre_pais'],
    				$p['id'],
    				$p['que_busca'],
    				$p['le_dieron'],
    				$p['info_ok'],
    				$p['privacidad_ok'],
    				$p['es_gratuito'],
    				$p['comodo'],
    				$p['informacion_vacunas'],
    				$p['edad'],
    				$p['genero'],
    				$p['voto'],
    				"\"" . $p['comentario']  . "\"" ,
    				$p['aprobado'],
    				$p['created_at'],
    				$p['service'],
    				$p['name'],
    				$p['email'],
    				$p['tel']
    			]);
    		}
    	//descarga
    		$csv->output($copyCSV);
    	}

	//recibe placeId y selectedServiceList
	//genera un csv, de las evaluaciones del lugar filtradas por los servicios que seleccion?? (selectedServiceList)
    	public function evaluationsExportFilterByService(Request $request){

    		$request_params = Input::all();
    		$placeId = $request_params['placeId'];
    		$serviciosString = $request_params['selectedServiceList'];
    		$services = explode(',', $serviciosString);
    		$placesRESTController = new PlacesRESTController;
    		$evaluations = $placesRESTController->getPlaceEvaluationsFilterByService($placeId, $services);
    		if (count($evaluations) > 0){
    			$copyCSV = "evaluaciones_".$evaluations[0]->establecimiento.".csv";
    		}
    		else {
    			$copyCSV = "nodata.csv";
    		}
    		$csv = Writer::createFromFileObject(new SplTempFileObject());

		//header
    		$csv->insertOne('id-establecimiento,nombre-establecimiento,direccion,barrio_localidad,ciudad,partido,provincia,pais,condones,prueba,vacunatorio,ile,infectologia,ssr,es_rapido,Id Evaluacion,??Que busco?,Edad,G??nero,Puntuaci??n,Comentario,??Aprobado?,Fecha,Servicio,Nombre,Email,Telefono');
		//body

    		foreach ($evaluations as $p) {
    			$p = (array)$p;
    			if (in_array($p['service'], $services)) {
    				$p['service']= $this->parseService($p['service']);
    				$p['condones']= $this->parseToExport($p['condones']);
    				$p['prueba']= $this->parseToExport($p['prueba']);
    				$p['ssr']= $this->parseToExport($p['ssr']);
    				$p['infectologia']= $this->parseToExport($p['infectologia']);
    				$p['vacunatorio']= $this->parseToExport($p['vacunatorio']);
    				$p['ile']= $this->parseToExport($p['ile']);
    				$p['es_rapido']= $this->parseToExport($p['es_rapido']);
    				$p['aprobado']= $this->parseToExport($p['aprobado']);
    				$p['direccion']= $p['calle']." ".$p['altura'];

    				$csv->insertOne([
    					$p['placeId'],
    					$p['establecimiento'],
    					$p['direccion'],
    					$p['barrio_localidad'],
    					$p['nombre_ciudad'],
    					$p['nombre_partido'],
    					$p['nombre_provincia'],
    					$p['nombre_pais'],
    					$p['condones'],
    					$p['prueba'],
    					$p['vacunatorio'],
    					$p['ile'],
    					$p['infectologia'],
    					$p['ssr'],
    					$p['es_rapido'],
    					$p['id'],
    					$p['que_busca'],
    					$p['edad'],
    					$p['genero'],
    					$p['voto'],
    					"\"" . $p['comentario']  . "\"" ,
    					$p['aprobado'],
    					$p['created_at'],
    					$p['service'],
    					$p['name'],
    					$p['email'],
    					$p['tel']

    				]);
    			}
    		}
    		$csv->output($copyCSV);
    	}

    	public function exportarPanelEvalFormed($pid,$cid,$bid){

    		$placesController = new PlacesRESTController;
    		$places = $placesController->showApproved($pid,$cid,$bid);

    		$copyCSV = "evaluaciones_".$places[0]->nombre_partido."_".$places[0]->nombre_provincia."_".$places[0]->nombre_pais.".csv";

    		$csv = Writer::createFromFileObject(new SplTempFileObject());

		//header
    		$csv->insertOne('id-establecimiento,nombre-establecimiento,direccion,barrio_localidad,partido,provincia,pais,condones,prueba,vacunatorio,ile,infectologia,ssr,es_rapido, Id Evaluaci??n,??Que busc???,??Se lo dieron?,Informaci??n clara,Privacidad,es_gratuito,comodo,Informaci??n_vacunas_edad,Edad,G??nero,Puntuaci??n,Comentario,??Aprobado?,Fecha');

		//body
    		foreach ($places as $key => $value) {

    			$evaluations = DB::table('evaluation')
    			->join('places','evaluation.idPlace','=','places.placeId')
    			->join('pais','pais.id','=','places.idPais')
    			->join('provincia','provincia.id','=','places.idProvincia')
    			->join('partido','partido.id','=','places.idPartido')
    			->where('evaluation.idPlace',$value->placeId)
    			->select('places.placeId','places.establecimiento','places.calle','places.altura','places.barrio_localidad','places.condones','places.prueba','places.vacunatorio','places.ile','places.ssr','places.infectologia','places.es_rapido','evaluation.id','evaluation.que_busca','evaluation.le_dieron','evaluation.info_ok','evaluation.privacidad_ok','evaluation.es_gratuito','evaluation.comodo','evaluation.informaci??n_vacunas','evaluation.edad','evaluation.genero','evaluation.voto','evaluation.comentario','evaluation.aprobado','pais.nombre_pais','provincia.nombre_provincia','partido.nombre_partido','evaluation.created_at')
    			->get();

    			foreach ($evaluations as $p) {
    				$p = (array)$p;
    				$p['condones']= $this->parseToExport($p['condones']);
    				$p['prueba']= $this->parseToExport($p['prueba']);
    				$p['vacunatorio']= $this->parseToExport($p['vacunatorio']);
    				$p['ile']= $this->parseToExport($p['ile']);
    				$p['ssr']= $this->parseToExport($p['ssr']);
    				$p['infectologia']= $this->parseToExport($p['infectologia']);
    				$p['es_rapido']= $this->parseToExport($p['es_rapido']);
    				$p['info_ok']= $this->parseToExport($p['info_ok']);
    				$p['privacidad_ok']= $this->parseToExport($p['privacidad_ok']);
    				$p['aprobado']= $this->parseToExport($p['aprobado']);
    				$p['direccion']= $p['calle']." ".$p['altura'];

    				$csv->insertOne([
    					$p['placeId'],
    					$p['direccion'],
    					$p['establecimiento'],
    					$p['barrio_localidad'],
    					$p['nombre_partido'],
    					$p['nombre_provincia'],
    					$p['nombre_pais'],
    					$p['condones'],
    					$p['prueba'],
    					$p['vacunatorio'],
    					$p['ile'],
    					$p['infectologia'],
    					$p['ssr'],
    					$p['es_rapido'],
    					$p['id'],
    					$p['que_busca'],
    					$p['le_dieron'],
    					$p['info_ok'],
    					$p['privacidad_ok'],
    					$p['es_gratuito'],
    					$p['comodo'],
    					$p['informacion_vacunas'],
    					$p['edad'],
    					$p['genero'],
    					$p['voto'],
    					"\"" . $p['comentario']  . "\"" ,
    					$p['aprobado'],
    					$p['created_at']
    				]);
    			}
    		}
    	//descarga
    		$csv->output($copyCSV);
    	}

    	public function exportarPanelFormed($pid=null,$cid=null,$bid=null){
    		$placesController = new PlacesRESTController;
    		$places = $placesController->panelShowApprovedActive($pid,$cid,$bid);

    		$copyCSV = "establecimientos_".$places[0]->nombre_partido."_".$places[0]->nombre_provincia."_".$places[0]->nombre_pais.".csv";
    		$csv = $this->insertArraObejectsDataIntoCsv_places($places);
		//descarga
    		$csv->output($copyCSV);
    	}

    	public function exportarPanelFormedCity($pid=null,$bid=null,$did=null,$cid=null){

    		$placesController = new PlacesRESTController;

    		$places = $placesController->showApprovedSearchActive($pid,$bid,$did,$cid);

    		$copyCSV = "establecimientos_".$places[0]->nombre_ciudad."_".$places[0]->nombre_partido."_".$places[0]->nombre_provincia."_".$places[0]->nombre_pais.".csv";
    		$csv = $this->insertArraObejectsDataIntoCsv_places($places);
		//descarga
    		$csv->output($copyCSV);
    	}


    	function download_csv_results($results, $name = NULL)
    	{
    		if( ! $name)
    		{
    			$name = md5(uniqid() . microtime(TRUE) . mt_rand()). '.csv';
    		}

    		header('Content-Type: text/csv');
    		header('Content-Disposition: attachment; filename='. $name);
    		header('Pragma: no-cache');
    		header("Expires: 0");
    		header("Content-Transfer-Encoding: UTF-8");

    		$outstream = fopen("php://output", "w");

    		foreach($results as $result)
    		{
    			fputcsv($outstream, $result);
    		}

    		fclose($outstream);
    	}

    	function joinFiles(array $files, $result) {
    		if(!is_array($files)) {
    			throw new Exception('`$files` must be an array');
    		}

    		$wH = fopen($result, "w+");

    		foreach($files as $file) {
    			$fh = fopen($file, "r");
    			while(!feof($fh)) {
    				fwrite($wH, fgets($fh));
    			}
    			fclose($fh);
    			unset($fh);
			fwrite($wH,""); //usually last line doesn't have a newline
		}
		fclose($wH);
		unset($wH);
	}


	/**
	* Export sample csv template with correct structures
	* @return .csv
	*/
	public function exportarMuestra(){
		$csv = Writer::createFromFileObject(new SplTempFileObject());
		//header
		$csv->insertOne($this->csvColumns);

		$csv->output('Template.csv');

	}

	public function exportar(){

		// contenedor de nombres
		$names = array();
		array_push($names,storage_path("encabezado.csv"));


		//genero primero el header del csv
		$encabezado = $this->csvColumns_arrayFormat;


		$file1 = fopen(storage_path("encabezado.csv"),"w");
		fputcsv($file1,$encabezado);
		fclose($file1);

		//armo el techo de grupos
		$n = DB::table('places')
		->join('pais','pais.id','=','places.idPais')
		->join('provincia','provincia.id','=','places.idProvincia')
		->join('partido','partido.id','=','places.idPartido')
		->join('ciudad','ciudad.id','=','places.idCiudad')
		->count();

		$n = $n / 1000;
		$n = ceil($n);

		//agrupo los files segun la cantidad de grupos que tenga.
		for ($i=0; $i < $n; $i++) {
			array_push($names, storage_path("file".$i.".csv") );
			$placeColumns = array('placeId','establecimiento','tipo','calle','altura','piso_dpto','cruce','barrio_localidad','ciudad.nombre_ciudad','partido.nombre_partido','provincia.nombre_provincia','pais.nombre_pais','aprobado','observacion','formattedAddress','latitude','longitude','places.habilitado','confidence','condones','prueba','vacunatorio','ile','infectologia','ssr','es_rapido','tel_distrib','mail_distrib','horario_distrib','responsable_distrib','web_distrib','ubicacion_distrib','comentarios_distrib','tel_testeo','mail_testeo','horario_testeo','responsable_testeo','web_testeo','ubicacion_testeo','observaciones_testeo','tel_vac','mail_vac','horario_vac','responsable_vac','web_vac','ubicacion_vac','comentarios_vac','tel_ile','mail_ile','horario_ile','responsable_ile','web_ile','ubicacion_ile','comentarios_ile','tel_infectologia','mail_infectologia','horario_infectologia','responsable_infectologia','web_infectologia','ubicacion_infectologia','comentarios_infectologia','tel_ssr','mail_ssr','horario_ssr','responsable_ssr','web_ssr','ubicacion_ssr','comentarios_ssr','servicetype_condones','servicetype_prueba','servicetype_mac','servicetype_ile','servicetype_dc','servicetype_ssr','friendly_condones','friendly_prueba','friendly_mac','friendly_ile','friendly_dc','friendly_ssr','uploader_name','uploader_email','uploader_tel');
			$places = DB::table('places')
			->join('pais','pais.id','=','places.idPais')
			->join('provincia','provincia.id','=','places.idProvincia')
			->join('partido','partido.id','=','places.idPartido')
			->join('ciudad','ciudad.id','=','places.idCiudad')
			->skip($i*1000)
			->take(1000)
			->select($placeColumns)
			->get();

			$file = fopen(storage_path("file".$i.".csv"),"w");

			foreach ($places as $line){
				$line->condones = $this->parseToExport($line->condones);
				$line->prueba = $this->parseToExport($line->prueba);
				$line->vacunatorio = $this->parseToExport($line->vacunatorio);
				$line->ile = $this->parseToExport($line->ile);
				$line->ssr = $this->parseToExport($line->ssr);
				$line->infectologia = $this->parseToExport($line->infectologia);
				$line->es_rapido = $this->parseToExport($line->es_rapido);
				$line->friendly_ile = $this->parseToExport($line->friendly_ile);
				$line->friendly_mac = $this->parseToExport($line->friendly_mac);
				$line->friendly_prueba = $this->parseToExport($line->friendly_prueba);
				$line->friendly_condones = $this->parseToExport($line->friendly_condones);
				$line->friendly_ssr = $this->parseToExport($line->friendly_ssr);
				$line->friendly_dc = $this->parseToExport($line->friendly_dc);

				$line = (array)$line;
				fputcsv($file,$line);
			}
			fclose($file);
		}
		    //cuando termina esto, ya tengo los files

			//uno los ficheros recien creados (ya estan en names)
		$this->joinFiles($names, storage_path('DONDE.csv'));

		$fName = storage_path("DONDE.csv");
		if (file_exists($fName)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.basename($fName).'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: private');
			header('Content-Length: ' . filesize($fName));
			readfile($fName);
			exit;
		}
	}
//==============================================================================================================
//==============================================================================================================
//==============================================================================================================
	public function get_numeric_score($data) {
		switch($data){
			case "ROOFTOP":
			return 0.9;
			break;
			case "RANGE_INTERPOLATED":
			return 0.7;
			break;
			case "GEOMETRIC_CENTER":
			return 0.5;
			break;
			case "APPROXIMATE":
			return 0.25;
			break;
			default:
			return 0;
		}
	}
	public function elimina_acentos($text) {
		$text = htmlentities($text, ENT_QUOTES, 'UTF-8');
		$text = strtolower($text);
		$patron = array (
	            // Espacios, puntos y comas por guion
	            //'/[\., ]+/' => ' ',
	            // Vocales
			'/\+/' => '',
			'/&agrave;/' => 'a',
			'/&egrave;/' => 'e',
			'/&igrave;/' => 'i',
			'/&ograve;/' => 'o',
			'/&ugrave;/' => 'u',
			'/&aacute;/' => 'a',
			'/&eacute;/' => 'e',
			'/&iacute;/' => 'i',
			'/&oacute;/' => 'o',
			'/&uacute;/' => 'u',
			'/&acirc;/' => 'a',
			'/&ecirc;/' => 'e',
			'/&icirc;/' => 'i',
			'/&ocirc;/' => 'o',
			'/&ucirc;/' => 'u',
			'/&atilde;/' => 'a',
			'/&etilde;/' => 'e',
			'/&itilde;/' => 'i',
			'/&otilde;/' => 'o',
			'/&utilde;/' => 'u',
			'/&auml;/' => 'a',
			'/&euml;/' => 'e',
			'/&iuml;/' => 'i',
			'/&ouml;/' => 'o',
			'/&uuml;/' => 'u',
			'/&auml;/' => 'a',
			'/&euml;/' => 'e',
			'/&iuml;/' => 'i',
			'/&ouml;/' => 'o',
			'/&uuml;/' => 'u',
	            // Otras letras y caracteres especiales
			'/&aring;/' => 'a',
			'/&ntilde;/' => 'n',
	            // Agregar aqui mas caracteres si es necesario
		);
		$text = preg_replace(array_keys($patron),array_values($patron),$text);
		return $text;
	}
	//==============================================================================================================
	// function to geocode address, it will return false if unable to geocode address
	public function geocode($book){

		//ya tiene lat&long
		if ( ($book['latitude']) != null  && ($book['longitude']) != null) {

			$address = $book['latitude'].','.$book['longitude'];

			try {
				$url = "https://maps.google.com.ar/maps/api/geocode/json?latlng={$address}&key=AIzaSyBoXKGMHwhiMfdCqGsa6BPBuX43L-2Fwqs";

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				$response = curl_exec($ch);
				curl_close($ch);

				$resp = json_decode($response,true);
				$location = json_decode($response);

			}catch(Exception $e){
				throw new ImporterException($e->getMessage());
			}

		    // // response status will be 'OK', if able to geocode given address
			if($resp['status']=='OK'){

				$geoResults = [];

				foreach($location->results as $result){

					$geoResult = [];

					if ($location->status == "OK"){

						foreach ($location->results[0]->address_components as $address) {

							if ($address->types[0] == 'country') {

								$geoResult['country'] = $address->long_name;

							}

							if ($address->types[0] == 'administrative_area_level_1') {

								$geoResult['state'] = $address->long_name;

							}

							if ($address->types[0] == 'administrative_area_level_1') {

								$geoResult['esCABA'] = $address->short_name;

							}

							if ($address->types[0] == 'administrative_area_level_2') {

							            $geoResult['partido'] = $address->long_name; //partido
							        }

							        if ($address->types[0] == 'locality') {  		//barrio_localidad (CABA), ciudad (Entre rios)

							        	$geoResult['city'] = $address->long_name;

							        }

							        if ($address->types[0] == 'political') { //solo en caba y reemplazaria a locality(city)

							            $geoResult['county'] = $address->long_name;  //barrio_localidad

							        }

							        if ($address->types[0] == 'route') {

							        	$geoResult['route'] = $address->short_name;

							        }

							        if ($address->types[0] == 'street_number') {

							        	$geoResult['street_number'] = $address->long_name;

							        }

							        $geoResult['lati'] = $result->geometry->location->lat;
							        $geoResult['longi'] = $result->geometry->location->lng;
							        $geoResult['formatted_address'] = $resp['results'][0]['formatted_address'];
							        $geoResult['accurracy'] = $this->get_numeric_score($result->geometry->location_type);

							    }

							}

							if (isset($geoResult['route']))
								if ($geoResult['route'] == "Unnamed Road") $geoResult['route'] = "Calle sin nombre";


						    // excepci??n CABA
							if(isset($geoResult['esCABA'])){
								if($geoResult['esCABA'] == 'CABA'){
									$geoResult['city'] = 'CABA';
								}
							}

							$geoResults = $geoResult;
						}

						$faltaAlgo = false;
						if (!isset($geoResults['state'])) $faltaAlgo = true;
						if (!isset($geoResults['city']) ) $faltaAlgo = true;

						if ($faltaAlgo)
							return false;
						else
							return $geoResults;

					}//End of login status OK

					else{
						return false;
					}

		}//sin lat y long

		else{ //sin geolocalizar

			$address = $book['calle'];
			if (is_numeric($book['altura']))
				$address = $address.' '.$book['altura'];
			if (($book['ciudad'] != $book['barrio_localidad']) && isset($book['barrio_localidad']) )
				$address = $address.' '.$book['barrio_localidad'];
			if (($book['ciudad'] != $book['partido_comuna']) && isset($book['ciudad']) )
				$address = $address.' '.$book['ciudad'];
			$address = $address.' '.$book['partido_comuna'];
			$address = $address.' '.$book['provincia_region'];
			$address = $address.' '.$book['pais'];
			$basicString = $this->elimina_acentos($address);
			$address = urlencode($basicString);

			try {
				$url = "https://maps.google.com.ar/maps/api/geocode/json?address={$address}&key=AIzaSyBoXKGMHwhiMfdCqGsa6BPBuX43L-2Fwqs";

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				$response = curl_exec($ch);
				curl_close($ch);

				$resp = json_decode($response,true);
				$location = json_decode($response);

			}catch(Exception $e){
				throw new ImporterException($e->getMessage());
			}

		    // // response status will be 'OK', if able to geocode given address
			if($resp['status']=='OK'){
				$geoResults = [];
				foreach($location->results as $result){
					$geoResult = [];
					if ($location->status == "OK"){
						foreach ($result->address_components as $address) {
							if ($address->types[0] == 'country') {
								$geoResult['country'] = $address->long_name;
							}
							if ($address->types[0] == 'administrative_area_level_1') {
								$geoResult['state'] = $address->long_name;
							}
							if ($address->types[0] == 'administrative_area_level_1') {
								$geoResult['esCABA'] = $address->short_name;
							}
							if ($address->types[0] == 'administrative_area_level_2') {
					            $geoResult['partido'] = $address->long_name; //partido
					        }
					        if ($address->types[0] == 'locality') {  		//barrio_localidad (CABA), ciudad (Entre rios)
					        	$geoResult['city'] = $address->long_name;
					        }
					        if ($address->types[0] == 'political') { //solo en caba
					            $geoResult['county'] = $address->long_name;  //barrio_localidad
					        }
					        if ($address->types[0] == 'route') {
					        	$geoResult['route'] = $address->short_name;
					        }
					        if ($address->types[0] == 'street_number') {
					        	$geoResult['street_number'] = $address->long_name;
					        }
					        $geoResult['lati'] = $result->geometry->location->lat;
					        $geoResult['longi'] = $result->geometry->location->lng;
					        $geoResult['formatted_address'] = $resp['results'][0]['formatted_address'];
					        $geoResult['accurracy'] = $this->get_numeric_score($result->geometry->location_type);
					    }

					}
					$geoResults = $geoResult;
					} // foreach location result


				//new aunque tendria que fallar aca.
					if (!isset($geoResults['state']))
						if (isset($geoResults['city']))
							$geoResults['state']=$geoResults['city'];

					if (isset($geoResults['esCABA']) && ($geoResult['esCABA'] == "CABA") ){ //solamente a caba le mando barrio|barrio|provincia|pais
						if (isset($geoResults['county']))
							$geoResults['partido'] = $geoResults['county'];

						if (isset($geoResults['county']))
							$geoResults['city'] = $geoResults['county'];
					}

					if (!$geoResults){

						return $this->geocodeExtra($book);
					}
					else {
						$faltaAlgo = false;
						if (!isset($geoResults['partido'])) $faltaAlgo = true;
						if (!isset($geoResults['state'])) $faltaAlgo = true;
						if (!isset($geoResults['country'])) $faltaAlgo = true;

						if ($faltaAlgo)
							return false;
						else{
							if (isset($geoResults['route']))
								$geoResults['route'] = $this->matchValues($book['calle'],$geoResults['route']);
							if (!isset($geoResults['route']) || $geoResults['route'] != $book['calle'])
								$geoResults['accurracy'] = 0;

							if (isset($geoResults['country']))
								$geoResults['country'] = $this->matchValues($book['pais'],$geoResults['country']);
							if ($geoResults['country'] != $book['pais'])
								$geoResults['accurracy'] = 0;

							if (isset($geoResults['state']))
								$geoResults['state'] = $this->matchValues($book['provincia_region'],$geoResults['state']);
							if ($geoResults['state'] != $book['provincia_region'])
								$geoResults['accurracy'] = 0;

						return $geoResults; //desp de la primera geoLoc, salgo con los datos obtenidos. "xq algo tengo"
					}
				}

			} //if resp[0] == OK
			else{ // si no puedo geolocalizar xq la calle es random
				$resu = $this->geocodeExtra($book);

				if ($resu){
					if (isset($resu['country']))
						$resu['country'] = $this->matchValues($book['pais'],$resu['country']);
					if ($resu['country'] != $book['pais'])
						$resu['accurracy'] = 0;

					if (isset($resu['state']))
						$resu['state'] = $this->matchValues($book['provincia_region'],$resu['state']);
					if ($resu['state'] != $book['provincia_region'])
						$resu['accurracy'] = 0;
					return $resu;
				}
				else
					return false;
			}
		}

	}

	public function matchValues($bookData, $googleData){
		// 0-0
		$result = $googleData;
		$pureBookData   = $this->elimina_acentos($bookData);
		$pureGoogleData = $this->elimina_acentos($googleData);

		// 1) 1-0
		if (is_null($googleData))
			$result = $bookData;

		// 2) 1-1
		if ($pureBookData != $pureGoogleData)
			$result = $bookData;

		// 3) 0-1
		if (is_null($bookData))
			$result = $googleData;

		return $result;
	}


	function curl_get_contents($url)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	public function geocodeExtra($book){
		$address = "";
		if (!is_null($book['barrio_localidad']))
			$address = $book['barrio_localidad'];

		if ( (!is_null($book['partido_comuna'])) )
			$address = $address.' '.$book['partido_comuna'];

		if (!is_null($book['provincia_region']))
			$address = $address.' '.$book['provincia_region'];

		if (!is_null($book['pais']))
			$address = $address.' '.$book['pais'];

		$basicString = $this->elimina_acentos($address);

		$address = urlencode($basicString);

		try {
			$url = "https://maps.google.com.ar/maps/api/geocode/json?key=AIzaSyBoXKGMHwhiMfdCqGsa6BPBuX43L-2Fwqs&address={$address}";

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			$response = curl_exec($ch);
			curl_close($ch);

			$resp = json_decode($response,true);
			$location = json_decode($response);

		}catch(Exception $e){
			throw new ImporterException($e->getMessage());
		}


		if($resp['status']=='OK'){
			$geoResults = [];
			foreach($location->results as $result){
				$geoResult = [];
				if ($location->status == "OK"){
					foreach ($result->address_components as $address) {
						if ($address->types[0] == 'country') {
							$geoResult['country'] = $address->long_name;
						}
						if ($address->types[0] == 'administrative_area_level_1') {
							$geoResult['state'] = $address->long_name;
						}
						if ($address->types[0] == 'administrative_area_level_1') {
							$geoResult['esCABA'] = $address->short_name;
						}
						if ($address->types[0] == 'administrative_area_level_2') {
						            $geoResult['partido'] = $address->long_name; //partido
						        }
						        if ($address->types[0] == 'locality') {  		//barrio_localidad (CABA), ciudad (Entre rios)
						        	$geoResult['city'] = $address->long_name;
						        }
						        if ($address->types[0] == 'political') { //solo en caba
						            $geoResult['county'] = $address->long_name;  //barrio_localidad
						        }
						        if ($address->types[0] == 'route') {
						        	$geoResult['route'] = $address->short_name;
						        }
						        if ($address->types[0] == 'street_number') {
						        	$geoResult['street_number'] = $address->long_name;
						        }
						        $geoResult['lati'] = $result->geometry->location->lat;
						        $geoResult['longi'] = $result->geometry->location->lng;
						        $geoResult['formatted_address'] = $resp['results'][0]['formatted_address'];
						        $geoResult['accurracy'] = $this->get_numeric_score($result->geometry->location_type);
						    }
						}
						$geoResults = $geoResult;

					}


					if (!isset($geoResults['city']))
						if (isset($geoResults['partido']))
							$geoResults['city'] = $geoResults['partido'];

					if (isset($geoResults['esCABA'])){ //solamente a caba le mando barrio|barrio|provincia|pais
						if (isset($geoResults['county']))
							$geoResults['partido'] = $geoResults['county'];
						if (isset($geoResults['county']))
							$geoResults['city'] = $geoResults['county'];
					}

					$faltaAlgo = false;
					if (!isset($geoResults['country'])) $faltaAlgo = true;
					if (!isset($geoResults['partido'])) $faltaAlgo = true;
					if (!isset($geoResults['city'])) $faltaAlgo = true;


					if ($faltaAlgo)
						return false;
					else{
					//si google normaliza distinto dev los datos del csv
						return $geoResults;
					}
		} // del resp satatus OK
		else { //esto es xq yha no tiene datos de ese lugar
			return false;
		}
	}

//==============================================================================================================
//==============================================================================================================
//==============================================================================================================

	//services are already parsed to bool's
	public function hasServices($book){
		$result = false;

		$mainServices = $this->placeMainServices;
		foreach ($mainServices as $key => $service) {
			if($book[$service] == 1){
				$result = true;
				break;
			}
		}

		return $result;
	}

	public function hasLatFormat($value){
		$result = false;

		if (is_numeric($value)){
			$result = preg_match('/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?)$/', $value);
		}
		return $result;
	}

	public function hasLongFormat($value){
		$result = false;

		if (is_numeric($value)){
			$result = preg_match('/^[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/', $value);
		}
		return $result;
	}

	public function isValidPlaceAprobado($aprobado){
		$result = false;
		if(is_numeric($aprobado) && ($aprobado == 0 || $aprobado == 1 || $aprobado == -1))
			$result = true;
		return $result;
	}

	public function isInvalidAttr($attr){
		$result = is_null($attr) || empty($attr);
		return $result;
	}

	public function esIncompleto($book, $withGeo = false){
		$result = false;

		if(($this->isInvalidAttr($book['establecimiento']))	||
			($this->isInvalidAttr($book['calle'])) 				||
			($this->isInvalidAttr($book['pais'])) 				||
			($this->isInvalidAttr($book['provincia_region']))	||
			($this->isInvalidAttr($book['partido_comuna'])) 	||
			($this->isInvalidAttr($book['ciudad'])) 			||
			(!$this->hasServices($book))						||
			(!$this->isValidPlaceAprobado($book['aprobado'])))
			$result = true;
		if($withGeo){
			if(	(!$this->isInvalidAttr($book['latitude']) && !$this->hasLatFormat($book['latitude'])) 	|| 
				(!$this->isInvalidAttr($book['longitude']) && !$this->hasLatFormat($book['longitude'])) )
				$result = true;
		}
		elseif(	(!$this->hasLatFormat($book['latitude'])) 		|| 
			(!$this->hasLongFormat($book['longitude']))		)
			$result = true;

		return $result;
	}

	public function esUpdateIncompleto($book){
		$result = false;

		$existePlace = Places::where('placeId',$book['id'])->first();
		if (!$existePlace || $this->esIncompleto($book)){
			$result = true;
		}

		return $result;
	}

	public function unsetLocationValidations($validations){
		unset($validations[array_search('ciudad', $validations)]);
		unset($validations[array_search('partido_comuna', $validations)]);
		unset($validations[array_search('provincia_region', $validations)]);
		unset($validations[array_search('pais', $validations)]);

		return $validations;
	}

	public function repetidoValidations(){
		$validations = $this->csvColumns_arrayFormat;					//all columns of csv entry
		array_shift($validations);										//pop the 'id' column
		unset($validations[array_search('confidence', $validations)]);	//Este da problemas en la base por ser tipo float plano
		$validations = $this->unsetLocationValidations($validations);	//pop location validations

		return $validations;
	}

	public function unificableValidations(){
		$validations = array('establecimiento', 'calle', 'altura');
		return $validations;
	}

	public function addLocationFilters($filters, $book, int $level){
		if($level < 0 || $level > 4) return $filters;

		if($level >= 1)	//pais
		array_push($filters,['column' => 'pais.nombre_pais', 'op' => '=', 'value' => $book['pais']]);
		if($level >= 2)	//provincia
		array_push($filters,['column' => 'provincia.nombre_provincia', 'op' => '=', 'value' => $book['provincia_region']]);
		if($level >= 3)	//partido
		array_push($filters,['column' => 'partido.nombre_partido', 'op' => '=', 'value' => $book['partido_comuna']]);
		if($level == 4)	//ciudad
		array_push($filters,['column' => 'ciudad.nombre_ciudad', 'op' => '=', 'value' => $book['ciudad']]);
		return $filters;
	}

	public function createFiltersWithValidations($table,$validations,$book,$level){
		$filters = array();
		foreach ($validations as $key => $validation) {
			$filter = ['column' => $table.'.'.$validation, 'op' => '=', 'value' => $book[$validation]];
			array_push($filters,$filter);
		}
		$filters = $this->addLocationFilters($filters,$book,$level);
		return $filters;
	}

	public function createJoins($table,int $level){
		$joins = array();
		if($level < 0 || $level > 4) return $joins;

		if($level >= 1)	//pais
		array_push($joins,['fkTable' => 'pais','id' => 'pais.id', 'op' => '=', 'fkID' => $table.'.idPais']);
		if($level >= 2)	//provincia
		array_push($joins,['fkTable' => 'provincia','id' => 'provincia.id', 'op' => '=', 'fkID' => $table.'.idProvincia']);
		if($level >= 3)	//partido
		array_push($joins,['fkTable' => 'partido','id' => 'partido.id', 'op' => '=', 'fkID' => $table.'.idPartido']);
		if($level == 4)	//ciudad
		array_push($joins,['fkTable' => 'ciudad','id' => 'ciudad.id', 'op' => '=', 'fkID' => $table.'.idCiudad']);

		return $joins;
	}

	// CustomJoining y CustomFiltering se definen en el Model Places (Eloquent Scopes)
	public function findPlaceByFilters($filters,$joins){
		$place = Places::customJoining(collect($joins))
		->customFiltering(collect($filters))
		->first();
		return $place;
	}

	// Si es un repetido, devuelve el id del establecimiento existente
	public function esRepetido($book){
		$result = false;
		
		$validations = $this->repetidoValidations();
		$filters = $this->createFiltersWithValidations('places',$validations,$book,4);
		$joins = $this->createJoins('places',4);

		$existePlace = $this->findPlaceByFilters($filters, $joins);
		
		if ($existePlace){
			$result = $existePlace->placeId;
		}

		return $result;
	}

	// Si es unificable, devuelve el id del establecimiento a unificar
	public function esUnificable($book){
		$result = false;
		
		$validations = $this->unificableValidations();
		$filters = $this->createFiltersWithValidations('places',$validations,$book,4);
		$joins = $this->createJoins('places',4);

		$existePlace = $this->findPlaceByFilters($filters, $joins);
		
		if ($existePlace){
			$result = $existePlace->placeId;
		}

		return $result;
	}

	public function esBajaConfianza($book){
		$result = false;

		// echo ' Confianza: '.$book['establecimiento'].' '.$book['confidence'];
		if ($book['confidence'] <= 0.25)
			$result = true;

		return $result;
	}

	// Aplicar los resultados de la geolocalizaci??n al establecimiento ($book)
	public function applyGeoResults($book,$latLng){
		$book['pais'] = isset($latLng['country'])?$latLng['country']:$book['pais'];
		$book['provincia_region'] = isset($latLng['state'])?$latLng['state']:$book['provincia_region'];
		$book['partido_comuna'] = isset($latLng['partido'])?$latLng['partido']:$book['partido_comuna'];
		$book['ciudad'] = isset($latLng['city'])?$latLng['city']:$book['ciudad'];
		$book['barrio_localidad'] = isset($latLng['city'])?$latLng['city']:$book['barrio_localidad'];
		$book['formatted_address'] = isset($latLng['formatted_address'])?$latLng['formatted_address']:$book['formatted_address'];
		$book['calle'] = isset($latLng['route'])?$latLng['route']:$book['calle'];
		$book['latitude'] = isset($latLng['lati'])?$latLng['lati']:$book['latitude'];
		$book['longitude'] = isset($latLng['longi'])?$latLng['longi']:$book['longitude'];
		$book['confidence'] = isset($latLng['accurracy'])?$latLng['accurracy']:$book['confidence'];
		$book['altura'] = isset($latLng['street_number'])?$latLng['street_number']:$book['altura'];

		    // if (!isset($latLng['state'])) $faltaAlgo = true;
      //       if (!isset($latLng['route'])) $latLng['route'] = $book->calle;
      //       if (!isset($latLng['city'])) $latLng['city'] = $book['partido_comuna'];

      //       if (!isset($latLng['county'])) {
      //       	if (isset($latLng['city']))
      //       		$latLng['county'] = $latLng['city'];
      //       	else
      //       		$faltaAlgo = true;
      //       }

      //       if (!isset($latLng['partido'])) {
      //       	if (isset($latLng['county']))
      //       		$latLng['partido'] = $latLng['county'];
      //       	else
      //       		$faltaAlgo = true;
      //       }

		return $book;
	}

	// Buscamos nuevos paises, provincias, partidos o ciudades (locations) para el establecimiento $book dado
	public function findNewLocations($book){
		$filters = array();
		$filters = $this->addLocationFilters($filters,$book,1);
		$existePais = Pais::customFiltering(collect($filters))
		->select('pais.id as pais')
		->first();

		$filters = array();
		$filters = $this->addLocationFilters($filters,$book,2);
		$joins = $this->createJoins('provincia',1);
		$existeProvincia = Provincia::customJoining(collect($joins))
		->customFiltering(collect($filters))
		->select('provincia.id as provincia','pais.id as pais')
		->first();

		$filters = array();
		$filters = $this->addLocationFilters($filters,$book,3);
		$joins = $this->createJoins('partido',2);
		$existePartido = Partido::customJoining(collect($joins))
		->customFiltering(collect($filters))
		->select('partido.id as partido','provincia.id as provincia','pais.id as pais')
		->first();

		$filters = array();
		$filters = $this->addLocationFilters($filters,$book,4);
		$joins = $this->createJoins('ciudad',3);
		$existeCiudad = Ciudad::customJoining(collect($joins))
		->customFiltering(collect($filters))
		->select('ciudad.id as ciudad', 'partido.id as partido','provincia.id as provincia','pais.id as pais')
		->first();

		return array($existePais,$existeProvincia,$existePartido,$existeCiudad);
	}

	// Filtrado de locations repetidos en el mismo archivo de importaci??n
	// Los resultados se guardan en $_SESSION
	public function filterNewLocations($newLocations,$book){
		if(count($newLocations) !== 4) return;
		$existePais = $newLocations[0];
		$existeProvincia = $newLocations[1];
		$existePartido = $newLocations[2];
		$existeCiudad = $newLocations[3];

		$level = 1;
		if (!$existePais) {
			$this->addNuevos('NuevosPaises',$book,$level);
		}

		$level = 2;
		if (!$existeProvincia) {
			$this->addNuevos('NuevosProvincia',$book,$level);
		}

		$level = 3;
		if (!$existePartido) {
			$this->addNuevos('NuevosPartido',$book,$level);
		}

		$level = 4;
		if (!$existeCiudad) {
			$this->addNuevos('NuevosCiudades',$book,$level);
		}
	}

	// Agregar la nueva localidad encontrada a la $_SESSION correspondiente
	public function addNuevos(string $sessionKey,$book,int $level){
		$new = $this->getLocationByLevel($book,$level);
		$found = false;
		foreach ($_SESSION[$sessionKey] as $key => $value) {
			if ($this->isSameLocation($value,$new,$level)){
				$found = true;
				break;
			}
		}
		if(!$found)
			array_push($_SESSION[$sessionKey],$new);
	}

	// Obtener una localidad con el nivel de detalle solicitado: 1.Pais > 2.Provincia > 3.Partido > 4.Ciudad
	public function getLocationByLevel($book,int $level): array {
		$arr = [];
		if($level < 0 || $level > 4) return $arr;
		
		if($level >= 1) $arr['Pais'] = $book['pais'];
		if($level >= 2) $arr['Provincia'] = $book['provincia_region'];
		if($level >= 3) $arr['Partido'] = $book['partido_comuna'];
		if($level == 4) $arr['Ciudad'] = $book['ciudad'];
		return $arr;
	}

	// Valida si la localidad nueva $new es id??ntica a una ya existente $value, seg??n el nivel de comparaci??n.
	public function isSameLocation(array $value, array $new,int $level): bool {
		if($level > 4 || $level <= 0) return true;

		$result = false;
		if($level == 1 && strcmp($value['Pais'],$new['Pais']) == 0)
			$result = true;
		if($level == 2 && strcmp($value['Pais'],$new['Pais']) == 0 
			&& strcmp($value['Provincia'],$new['Provincia']) == 0)
			$result = true;
		if($level == 3 && strcmp($value['Pais'],$new['Pais']) == 0 
			&& strcmp($value['Provincia'],$new['Provincia']) == 0
			&& strcmp($value['Partido'],$new['Partido']) == 0)
			$result = true;
		if($level == 4 && strcmp($value['Pais'],$new['Pais']) == 0 
			&& strcmp($value['Provincia'],$new['Provincia']) == 0
			&& strcmp($value['Partido'],$new['Partido']) == 0
			&& strcmp($value['Ciudad'],$new['Ciudad']) == 0)
			$result = true;
		
		return $result;
	}

	// Transforma coloquial a binario
	public function parseToImport($string){
		$string = strtolower(trim($string));
		if (strcasecmp($string, "si") == 0){
			$string = 1;
		}
		else{
			$string = 0;
		}
		return $this->convertfromISOCharset($string);
	}

	//AutoCorrector: Si un servicio secundario o adicional es seleccionado, seleccionar tambi??n el principal
	public function autocorrectOptServices($book){
		$optServices = $this->placeOptServices;
		foreach ($optServices as $optService => $mainService) {
			if($book[$optService] == 1 && $book[$mainService] == 0){
				$book[$mainService] = 1;
			}
		}
		return $book;
	}

	// Transforma servicios para importar
	public function parseServicesToImport($book){
		$services = array_merge($this->placeMainServices, array_keys($this->placeOptServices), $this->placeFriendlys);
		$serviceTypes = $this->placeServicetypes;

		foreach ($services as $key => $service) {
			$book[$service.'Ori'] = $book[$service];
			$book[$service] = $this->parseToImport($book[$service]);
		}

		foreach ($serviceTypes as $key => $serviceType) {
			$book[$serviceType] = strtolower($book[$serviceType]);
		}

		$book = $this->autocorrectOptServices($book);

		return $book;
	}

	// Transformaciones del archivo para importar
	public function parseDataToImport($book){
		$book = $this->parseServicesToImport($book);

		return $book;
	}

	// Valida si en el archivo de importaci??n existen datos repetidos/unificables
	public function isNotUniqueEntry($books, $index, $book){
		$result = false;
		$uniqueColumns = ['id','establecimiento','calle','altura','ciudad','partido_comuna','provincia_region','pais'];

		foreach ($books as $key => $value) {
			if($key <= $index) continue;
			foreach ($uniqueColumns as $k => $column) {
				if(strcmp($value[$column],$book[$column]) == 0)
					$result = true;
				else{
					$result = false;
					break;
				}
			}
			if($result){
				$result = $key;
				break;
			}
			else
				$result = false;
		}

		return $result;
	}

//==========FUNCION que valida si el CSV ingresado es valido =================//
	public function validarCsv (Request $request){
		$request_params = $request->all();
		if ($request->hasFile('file'))
			$ext = $request->file('file')->getClientOriginalExtension();
		if (isset($ext))
			$request_params['tmp'] = ($ext == "csv") ? 1234 : 1234567;

		$rules = array(
			'tmp' => 'required|max:4'
		);
		$messages = array(
			'required'    => 'Se debe ingresar un archivo antes de continuar!',
			'max'    => 'La extension del archivo tiene que ser .csv y estar separado por punto y coma (";") ');
		$validator = Validator::make($request_params,$rules,$messages);
		if ($validator->fails()) {
			return redirect('panel/importer/picker')
			->withErrors($validator->messages())
			->withInput();
		}
	}

	public function csvPrimeraFila(Request $request) {
		$tmpFile = Input::file('file')->getClientOriginalName();
		Storage::disk('local')->put($tmpFile, \File::get($request->file('file') ) );
		Excel::load(storage_path().'/app/'.$tmpFile, function($reader) {
			$_SESSION['primeraFila'] = $reader->get()[0];
		},'UTF-8');
		$primeraFila = $_SESSION['primeraFila'];
		session()->forget('primeraFila');
		return $primeraFila;
	}

	public function checkAllColumns($rowColumns){
		// tambien se puede hacer con $correctCvs == $rowColumns.
		$correctCvs = $this->csvColumns_arrayFormat;

		$status = true;
		$failColumns = array();
		$columns = array();
		$failColumns['sizeProblem'] = "";

		if (count($correctCvs) != count($rowColumns)){
			$status = false;
			$failColumns['sizeProblem'] = "Revise la cantidad de columnas ingresadas";
		}
		else {
			for ($i=0; $i < count($rowColumns) ; $i++) {
				if (strcmp($correctCvs[$i],$rowColumns[$i]) != 0){
					$status = false;
					array_push($columns, $correctCvs[$i] );
					continue;
				}
			}
		}
		$failColumns['columns'] = $columns;
		$failColumns['status'] = $status;
		return $failColumns;
	}

	public function setSessionData(string $key, $value){
		$_SESSION[$key] = $value;
		session([$key => $value]);
		return $value;
	}

	public function importCsv(Request $request){
		$request_params = $request->all();
		
		if ($request->hasFile('file')){

			$ext = $request->file('file')->getClientOriginalExtension();
			$rows = Excel::load($request->file('file')->getRealPath(), function($reader) {
				
			},'UTF-8')->get()->toArray();
			$rowCount = count($rows);
			$rowColumns =  array_keys($rows[0]);
			$validateResult = $this->checkAllColumns($rowColumns);

			try {
				if ($rowCount > 1000)
					abort(310, "El maximo de centros soportados es 1000. Revisalo y volv?? a intentar.");
				else
					if (!$validateResult['status'])
						abort(311, "Parece que la estructura del CSV que estas subiendo no es v??lida. Revisalo contra un formato ejemplo y volv?? a intentar.");
				}
				catch(Exception $e){
					if ($e->getMessage() == "Parece que la estructura del CSV que estas subiendo no es v??lida. Revisalo contra un formato ejemplo y volv?? a intentar.")
						throw new CustomException($validateResult, $e->getMessage(),$e->getCode());
					else
						throw new CsvException($e->getMessage());
				}

				if (isset($ext))
					$request_params['tmp'] = ($ext == "csv") ? 1234 : 1234567;

				$rules = array(
					'tmp' => 'required|max:4'
				);

				$messages = array(
					'required'    => 'Se debe ingresar un archivo antes de continuar!',
					'max'    => 'La extension del archivo tiene que ser .csv y estar separado por comas (",") ');

				$validator = Validator::make($request_params,$rules,$messages);

				if ($validator->fails()) {
					return redirect('panel/importer/picker')
					->withErrors($validator->messages())
					->withInput();
				}
				$params = $request_params;

				$book = $this->csvPrimeraFila($request);

				session_start();

				$tmpFile = Input::file('file')->getClientOriginalName();
				$this->setSessionData('csvname', $tmpFile);
				Storage::disk('local')->put($tmpFile, \File::get($request->file('file')));

			//Resivar el modo del importador (caminos):
			//1) Si es importador de datos nuevos sin servicio de geolocalizaci??n
			//2) Si es importador de datos nuevos con servicio de geolocalizaci??n
			//3) Si es actualizador de datos existentes

				$this->setSessionData('withGeo', false);

				if(!is_null($book['id'])){
				//Si 'id' de la primer fila no est?? vac??o, entonces camino 3)
					$this->setSessionData('importerMode', 'updater');
				}
				else {
				//Si 'id' de la primer fila est?? vac??o, entonces camino 1) o 2)
					$this->setSessionData('importerMode', 'importer');

					if( (is_null($book['latitude']))  && (is_null($book['longitude'])) ) {
					//Si la primer fila no tiene datos en Lat y Long, entonces camino 2)
						$this->setSessionData('withGeo', true);
					}
				}

				return $this->preAdd($request);
			}
			else{
				abort(311, "No ha seleccionado ning??n dataset");
			}
		}

//=================================================================================================================
//	RUTA PREVIEW, VISUALIZO LAS NUEVAS LOCALIDADES INGRESADAS
//=================================================================================================================

		public function preAdd(Request $request) {
			$_SESSION['NuevosPaises']= array();
			$_SESSION['NuevosProvincia']= array();
			$_SESSION['NuevosPartido']= array();
			$_SESSION['NuevosCiudades']= array();

			$tmpFile = Input::file('file')->getClientOriginalName();
			$this->setSessionData('nombreFile',$tmpFile);
			$this->setSessionData('csvname',$tmpFile);

			Storage::disk('local')->put($tmpFile, \File::get($request->file('file') ) );

			Excel::load(storage_path().'/app/'.$tmpFile, function($reader){
				$books = array();
				$withGeo = $_SESSION['withGeo'];
				foreach ($reader->get() as $book) {
					$book = $book->toArray();
					$book = $this->parseDataToImport($book);
					if(!$this->esIncompleto($book,$withGeo)){
					//Si se eligieron los servicios de geo, hay que pasar antes por ah??
						if($withGeo){
							$latLng = $this->geocode($book);
							if ($latLng){
								$book = $this->applyGeoResults($book,$latLng);
							}
						}
						$this->filterNewLocations($this->findNewLocations($book),$book);
					}
					array_push($books,$book);
				}
				$this->setSessionData('books',$books);
			},'UTF-8');

		//Armo los datos para mostrar
			$nuevosPaises = $_SESSION['NuevosPaises'];
			$nuevosProvincias = $_SESSION['NuevosProvincia'];
			$nuevosPartidos = $_SESSION['NuevosPartido'];
			$nuevosCiudades = $_SESSION['NuevosCiudades'];
			$nombreFile = $_SESSION['nombreFile'];

			return view('panel.importer.preview',compact('nuevosPaises','nuevosProvincias','nuevosPartidos','nuevosCiudades','nombreFile'));
		}

//=================================================================================================================
//	RUTA CONFIRM, VISUALIZO LOS NUEVOS ESTABLECIMIENTOS INGRESADOS
//=================================================================================================================

		public function confirmAdd(Request $request){
			session_start();

			$datosActualizar = array();
			$datosNuevos = array();
			$datosRepetidos = array();
			$datosIncompletos = array();
			$datosUnificar = array();
			$datosDescartados = array();
			$errores = array();
			$errores['general_repetidos'] = false;

			$books = $_SESSION['books'];
			$withGeo = $_SESSION['withGeo'];
			$importerMode = $_SESSION['importerMode'];

		// Primera pasada, el campo de errores en false
			for ($index = 0; $index < count($books); $index++) {
				$books[$index]['error_repetidos'] = false;
			}

			for ($index = 0; $index < count($books); $index++) {
				$book = $books[$index];

				if($resultKey = $this->isNotUniqueEntry($books,$index,$book)){
					$errores['general_repetidos'] = true;
					$book['error_repetidos'] = true;
					$books[$resultKey]['error_repetidos'] = true;
				}

				if(strcmp($importerMode,'updater') == 0){
					if($this->esUpdateIncompleto($book)){
						array_push($datosIncompletos,$this->agregarIncompleto($book));
					}
					else{
						array_push($datosActualizar,$this->agregarActualizar($book));
					}
				}
				else if(strcmp($importerMode,'importer') == 0){
					$id = [];
					if ($this->esIncompleto($book,$withGeo)){
						array_push($datosIncompletos,$this->agregarIncompleto($book));
					}
					elseif($withGeo && $this->esBajaConfianza($book)){
						array_push($datosDescartados,$this->agregarBajaConfianza($book));
					}
					elseif ($id = $this->esRepetido($book)){
						array_push($datosRepetidos,$this->agregarRepetido($book,$id));
					}
					elseif ($id = $this->esUnificable($book)){
						array_push($datosUnificar,$this->agregarUnificable($book,$id));
					}
					else{
						array_push($datosNuevos,$this->agregarNuevo($book));
					}
				}
			}

			$this->setSessionData('datosActualizar',$datosActualizar);
			$this->setSessionData('datosNuevos',$datosNuevos);
			$this->setSessionData('datosRepetidos',$datosRepetidos);
			$this->setSessionData('datosIncompletos',$datosIncompletos);
			$this->setSessionData('datosUnificar',$datosUnificar);
			$this->setSessionData('datosDescartados',$datosDescartados);

			return view('panel.importer.confirmFast',
				compact('datosActualizar','datosNuevos','datosRepetidos','datosIncompletos','datosUnificar','datosDescartados','errores'));
		}

//=================================================================================================================
//	RUTA RESULTS, VISUALIZO LOS ESTABLECIMIENTOS IMPORTADOS Y NO IMPORTADOS
//=================================================================================================================

		public function posAdd(Request $request){
			session_start();

			$datosActualizar = $request->session()->get('datosActualizar');
			$datosNuevos = $request->session()->get('datosNuevos');
			$datosRepetidos = $request->session()->get('datosRepetidos');
			$datosDescartados = $request->session()->get('datosDescartados');
			$datosUnificar = $request->session()->get('datosUnificar');
			$datosIncompletos = $request->session()->get('datosIncompletos');

			if (session()->get('datosNuevos') != null){

				$placeLog = $this->createPlaceLog("import");
				$contador = 0;

				foreach ($datosNuevos as $book) {
					$book = $this->getOrCreateLocations($book);
					$newPlace = $this->createNewPlace($book, $placeLog);

					$book['placeId'] = $newPlace->placeId;
					$datosNuevos[$contador]['placeId'] = $newPlace->placeId;
					$contador++;
				}
				$this->setSessionData('datosNuevos',$datosNuevos);
			}

			if (session()->get('datosUnificar') != null){

				$placeLog = $this->createPlaceLog("unified_import");

				foreach ($datosUnificar as $book) {
					$this->unifyExistingPlace($book, $placeLog);
				}
			}

			if (session()->get('datosActualizar') != null){

				$placeLog = $this->createPlaceLog("update_import");

				foreach ($datosActualizar as $book) {
					$book = $this->getOrCreateLocations($book);
					$this->updateExistingPlace($book, $placeLog);
				}
			}

		// Si hacemos forget, no se pueden bajar los datos en el ??cono "download" cuando termina el proceso.
		// session()->forget('datosNuevos');
		// session()->forget('datosActualizar');
		// session()->forget('datosUnificar');
		// session()->forget('datosIncompletos');
		// session()->forget('datosDescartados');
		// session()->forget('datosRepetidos');
		// session()->forget('csvname');

			return view('panel.importer.results',compact('datosActualizar','datosNuevos','datosRepetidos','datosDescartados','datosIncompletos','datosUnificar'));
		}

//=================================================================================================================
//=================================================================================================================
//	STORE
//=================================================================================================================
//=================================================================================================================

	// Given a place ($book), assign the locations id's or create them if they are new locations
		public function getOrCreateLocations($book){
			$newLocations = $this->findNewLocations($book);
			$existePais = $newLocations[0];
			$existeProvincia = $newLocations[1];
			$existePartido = $newLocations[2];
			$existeCiudad = $newLocations[3];

			if (!$existePais) {
				$pais = new Pais;
				$pais->nombre_pais = $book['pais'];
				$pais->habilitado = 1;
				$pais->save();
				$book['idPais'] = $pais->id;
			}
			else{
				$book['idPais'] = $existePais->pais;
				$existePais->habilitado = 1;
				$existePais->save();
			}

			if (!$existeProvincia) {
				$provincia = new Provincia;
				$provincia->nombre_provincia = $book['provincia_region'];
				$provincia->idPais = $book['idPais'];
				$provincia->habilitado = 1;
				$provincia->save();
				$book['idProvincia'] = $provincia->id;
			}
			else{
				$book['idPais'] = $existeProvincia->pais;
				$book['idProvincia'] = $existeProvincia->provincia;
				$existeProvincia->habilitado = 1;
				$existeProvincia->save();
			}

			if (!$existePartido) {
				$partido = new Partido;
				$partido->nombre_partido = $book['partido_comuna'];
				$partido->idPais = $book['idPais'];
				$partido->idProvincia = $book['idProvincia'];
				$partido->habilitado = 1;
				$partido->save();
				$book['idPartido'] = $partido->id;
			}
			else{
				$book['idPais'] = $existePartido->pais;
				$book['idProvincia'] = $existePartido->provincia;
				$book['idPartido'] = $existePartido->partido;
				$existePartido->habilitado = 1;
				$existePartido->save();
			}

			if (!$existeCiudad) {
				$ciudad = new Ciudad;
				$ciudad->nombre_ciudad = $book['ciudad'];
				$ciudad->idPais  = $book['idPais'];
				$ciudad->idProvincia = $book['idProvincia'];
				$ciudad->idPartido = $book['idPartido'];
				$ciudad->habilitado = 1;
				$ciudad->save();
				$book['idCiudad'] = $ciudad->id;
			}
			else{
				$book['idPais'] = $existeCiudad->pais;
				$book['idProvincia'] = $existeCiudad->provincia;
				$book['idPartido'] = $existeCiudad->partido;
				$book['idCiudad'] = $existeCiudad->ciudad;
				$existeCiudad->habilitado = 1;
				$existeCiudad->save();
			}

			return $book;
		}

	// Create new import tag
		public function createPlaceLog($entry_type){
			$placeTag = new PlaceLog();
			$placeTag->modification_date = date("Y/m/d");
			$placeTag->entry_type = $entry_type;
			$placeTag->user_id = Auth::user()->id;
			$placeTag->csvname = session('csvname');
			$placeTag->save();

			return $placeTag;
		}

	// datosNuevos
		public function createNewPlace($book, PlaceLog $placeLog){
		$columns = $this->csvColumns_arrayFormat;								//all csv columns
		array_shift($columns); 													//pop the 'id' column
		$columns = $this->unsetLocationValidations($columns);					//pop locations names
		array_push($columns,'idPais','idProvincia','idPartido','idCiudad');		//push locations ids

		$newPlace = new Places;
		foreach ($columns as $key => $column) {
			$newPlace[$column] = $book[$column];
		}
		$newPlace->logId = $placeLog->id;
		$newPlace->save();

		return $newPlace;
	}

	// datosUnificar
	public function unifyExistingPlace($book, PlaceLog $placeLog){
		$columns = $this->csvColumns_arrayFormat;				//all csv columns
		array_shift($columns); 									//pop the 'id' column
		$columns = $this->unsetLocationValidations($columns);	//pop locations names
		$diff = $this->unificableValidations();					//no deber??a actualizar lo que utiliza para validar, a??n as?? sean iguales
		$columns = array_diff($columns, $diff);					//pop validations
		
		$place = Places::find($book['placeId']);
		foreach ($columns as $key => $column) {
			$place[$column] = $book[$column];
		}
		$place->logId = $placeLog->id;
		$place->save();

		return $place;
	}

	// datosActualizar
	public function updateExistingPlace($book, PlaceLog $placeLog){
		$columns = $this->csvColumns_arrayFormat;								//all csv columns
		array_shift($columns); 													//pop the 'id' column
		$columns = $this->unsetLocationValidations($columns);					//pop locations names
		array_push($columns,'idPais','idProvincia','idPartido','idCiudad');		//push locations ids

		$place = Places::find($book['placeId']);
		foreach ($columns as $key => $column) {
			$place[$column] = $book[$column];
		}
		$place->logId = $placeLog->id;
		$place->save();

		return $place;
	}


	public function agregarBadActualizar($book){
		return $this->preparePlaceToImport($book,'ADD_BAU');
	}

	public function agregarActualizar($book){
		return $this->preparePlaceToImport($book,'ADD_ACT');
	}

	public function agregarIncompleto($book){
		return $this->preparePlaceToImport($book,'ADD_INC');
	}

	public function agregarBajaConfianza($book){
		return $this->preparePlaceToImport($book,'ADD_BAC');
	}

	public function agregarRepetido($book,$id){
		$book['id'] = $id;
		$book = $this->preparePlaceToImport($book,'ADD_REPITED');
		return $book;
	}

	public function agregarUnificable($book,$id){
		$book['id'] = $id;
		$book = $this->preparePlaceToImport($book,'ADD_UNI');
		return $book;
	}
	public function agregarNuevo($book){
		$book = $this->preparePlaceToImport($book,'ADD_NEW');
		return $book;
	}

	// Elimina datos de la tabla paises, provincias, partidos, ciudades, evaluaciones y places
	public function cleardb(Request $request){
		$result = $this->getServerMode($request);
		$mode = $result['mode'];
		if (($mode !== null) && ($mode !== 'production'))  {
			DB::statement('SET FOREIGN_KEY_CHECKS=0');
			DB::table('places')->truncate();
			DB::table('ciudad')->truncate();
			DB::table('partido')->truncate();
			DB::table('provincia')->truncate();
			DB::table('pais')->truncate();
			DB::table('evaluation')->truncate();
			DB::statement('SET FOREIGN_KEY_CHECKS=1');
		}
		else{
			$result =  "Proceso NO permitido para servidor en PRODUCCION";
		}

		return $result;
	}

	public function getServerMode(Request $request){
		if(getenv("APP_ENV") == false)
			$mode = 'production';
		else
			$mode = getenv("APP_ENV");
		return(['mode' => $mode]);
	}

}