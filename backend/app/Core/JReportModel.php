<?php

namespace App\Core;

use Exception;
use Illuminate\Support\Facades\Storage;
use JasperPHP\JasperPHP;

/**
 * PHP Jasper Report Utlis
 * Clase para generar reportes de Jaspert Report desde php
 *
 */

class JReportModel {

	/**
	 * Driver jdbc de la conexion
	 * @var  String $driver
	*/

	private $driver	= "com.mysql.jdbc.Driver";
	/**
	* Nombre de la base de datos para la conexion jdbc
	* @var String $database_name
	*/
	private $database_name	= 'dbname';

	private $host			= 'localhost';


	/**
	 * usuario de la conexion  jdbc
	 *
	 * @var  String $user
	 */
	private $username_db	= 'root';

    private $port           = '3307';

	/**
	* Contraseña del usuario de la conexion jdbc
	* @var String $password_db
	*
	*/
	private $password_db	= NULL;

	/**
	* Propiedades para almacenar las rutas de los informes en sus respentivos formatos
	*/
	public $path_folder_pdf;
	public $path_folder_doc;
	public $path_folder_csv;
	public $path_folder_txt;
	public $path_folder_pptx;
	public $path_folder_html;
	public $path_folder_rtf;
	public $path_folder_xls;
	public $path_folder_xlsx;
	public $path_report;
    public $path_report_put;
    public $directory_path;

    var $path_delim = '/';

	//Variables del informe
	public	$p_title		= 'TITULO DEL REPORTE';
 	public	$p_nit			= '';
	public	$p_footer		= 'Leyenda del reporte';
	public	$p_dane			= '';
	public 	$p_resol		= '';
	public	$p_img_left		= '';
	public 	$p_img_right	= '';
	public  $p_escudo;
	public  $p_marketing;
    public	$subreport_dir	= '';

    public function __construct()
    {
        $this->path_folder_pdf	= 'pdf';
		$this->path_folder_doc	= 'doc';
		$this->path_folder_csv	= 'csv';
		$this->path_folder_txt	= 'txt';
		$this->path_folder_pptx	= 'pptx';
		$this->path_folder_html = 'html';
		$this->path_folder_rtf 	= 'rtf';
        $this->path_folder_xls 	= 'xls';
        $this->path_folder_xlsx = 'xlsx';
        $this->driver			= "mysql" /** mysql || postgres */;

        $delim				    = $this->path_delim;

        $this->port             = env('DB_PORT', '3306');
        $this->database_name    = env('DB_DATABASE', 'forge');
        $this->username_db		= env('DB_USERNAME', 'root');
		$this->password_db		= env('DB_PASSWORD', '');
		$this->host				= env('DB_HOST', '127.0.0.1');

        $this->path_report  = 'reportsjr';

        Storage::disk('reports')->makeDirectory($this->path_report);

        $dir    = $this->path_report.$delim;

        Storage::disk('reports')->makeDirectory($dir.$this->path_folder_pdf);
        Storage::disk('reports')->makeDirectory($dir.$this->path_folder_doc);
        Storage::disk('reports')->makeDirectory($dir.$this->path_folder_pptx);
        Storage::disk('reports')->makeDirectory($dir.$this->path_folder_xls);
        Storage::disk('reports')->makeDirectory($dir.$this->path_folder_html);
        Storage::disk('reports')->makeDirectory($dir.$this->path_folder_rtf);
        Storage::disk('reports')->makeDirectory($dir.$this->path_folder_csv);


        Storage::disk('reports')->makeDirectory('reportsjr'.$delim.'subreports');


        $this->path_folder_pdf	= $dir.'pdf';
		$this->path_folder_doc	= $dir.'doc';
		$this->path_folder_csv	= $dir.'csv';
		$this->path_folder_txt	= $dir.'txt';
		$this->path_folder_pptx	= $dir.'pptx';
		$this->path_folder_html = $dir.'html';
		$this->path_folder_rtf 	= $dir.'rtf';
        $this->path_folder_xls 	= $dir.'xls';

        $this->subreport_dir    = 'reportsjr'.$delim.'subreports';
    }

	/**
	*
	* @param string $reportName, Nonbre del reporte creado en JasReport
	* @param string $outputName, Nonbre de salida del reporte
	* @param string $outputFolder, Nonbre o ruta de la carpeta de salida del reporte
	* @param string $format, Formato o extensión del informe
	* @param string $query, sentencia SQL.
	* @param string $param, parametros del reporte SQL.
	*
	* @return jsonReponse
	*/
	public function getReportExport ($reportName, $outputName,  $fmt, $query, $outputFolder = '', $param = []) {

        $format             = strtolower($fmt);
        //Reporte a Procesar : Este nombre es del reporte creado en JasReport
        $path_root          =  public_path().$this->path_delim;
		$reportName			=  $path_root.$this->path_report.$this->path_delim.$reportName;

		//Parametro en caso de que el reporte no este parametrizado

        $paramreport	= array(
            'SQL_PARAM' 	=> $query,
            'HOME_DIR' 	    => $path_root,
            'R_MARKETING'   => 'Matias API',
            'SUBREPORT_DIR' => $path_root.$this->subreport_dir.$this->path_delim
        );


		if(count($param) > 0){
			foreach($param as $key => $value){
				$paramreport[$key] = $value;
			}
		}

		try {
			switch ($format) {
				case 'pdf':
					$output	= $this->path_folder_pdf.$this->path_delim;
					break;
				case 'pptx':
					$output	= $this->path_folder_pptx.$this->path_delim;
					break;
				case 'docx':
					$output	= $this->path_folder_doc.$this->path_delim;
					break;
				case 'csv':
					$output	= $this->path_folder_csv.$this->path_delim;
					break;
				case 'txt':
					$output	= $this->path_folder_txt.$this->path_delim;
					break;
				case 'html':
					$output	= $this->path_folder_html.$this->path_delim;
					break;
				case 'rtf':
					$output	= $this->path_folder_rtf.$this->path_delim;
					break;
				case 'xls':
					$output	= $this->path_folder_xls.$this->path_delim;
					break;
				case 'xlsx':
					$output	= $this->path_folder_xlsx.$this->path_delim;
					break;
				default:
					$output	= $this->path_folder_pdf.$this->path_delim;
					break;
			}

            $date	        = date('Ymdhis');
            $output_report  = $output.$date.$this->path_delim;
            if(strlen($outputFolder) > 0){
                $output_report  = $output.$outputFolder.$this->path_delim;
            }
            Storage::disk('reports')->makeDirectory($output_report);
			$jasper = new JasperPHP;
			// Compile a JRXML to Jasper
			$jasper->compile($reportName.'.jrxml')->execute();

            $output_report  = public_path('storage/').$output_report;
			$jasper->process(
				$reportName.'.jasper',
				$output_report.$outputName,
				array($format),
				$paramreport,
				array(
					'driver' 	=> $this->driver,
					'username' 	=> $this->username_db,
					'host' 		=> $this->host,
					'database' 	=> $this->database_name,
					'password' 	=> $this->password_db,
					'port' 		=> $this->port
				)
            )->execute();

            $output     = Storage::url(substr($output_report, strpos($output_report,'reportsjr')));

            return response()->json([
                'success'   => true,
                'pathFile'  => utf8_encode($output.$outputName.".".$format)
            ], 200);

		} catch (Exception $e) {
			// Depuración de errores
            exec($jasper->output().' 2>&1', $output_error);

            return response()->json([
                'success'   => false,
                'message'   => $e->getMessage(),
            ], 500);
		}
	}


	public function getReportExportJson ($reportName, $outputName, $fmt, $jsonPath, $outputFolder = '', $param = []) {

        $format             = strtolower($fmt);
        //Reporte a Procesar : Este nombre es del reporte creado en JasReport
        $path_root          =  public_path().$this->path_delim;
		$reportName			=  $path_root.$this->path_report.$this->path_delim.$reportName;

		//Parametro en caso de que el reporte no este parametrizado

        $paramreport	= array(
            'HOME_DIR' 	    => $path_root,
            'R_MARKETING'   => 'Matias API',
            'SUBREPORT_DIR' => $path_root.$this->subreport_dir.$this->path_delim
        );

		if(count($param) > 0){
			foreach($param as $key => $value){
				$paramreport[$key] = $value;
			}
		}

		try {
            $output_report  = $outputFolder.$this->path_delim.$format.$this->path_delim;
            Storage::disk('reports')->makeDirectory($output_report);
			$jasper = new JasperPHP;

            $date	        = date('Ymdhis');
            $report_url     = "{$output_report}{$outputName}_{$date}.{$format}";
            $output_report  = public_path('storage/').$output_report;
            $outputFile     = $output_report.$outputName."_{$date}";
			$jasper->process(
				$reportName.'.jrxml',
				$outputFile,
				array($format),
				$paramreport,
				array(
					'driver' 	    => "json",
					'json_query' 	=> "",
					"data_file"     =>  $jsonPath
				)
            )->execute();

            $output     = url(Storage::url($report_url));

            return [
                'url'       => utf8_encode($output),
                'data'      => base64_encode(file_get_contents("{$outputFile}.{$format}"))
            ];

		} catch (Exception $e) {
			// Depuración de errores
            exec($jasper->output().' 2>&1', $output_error);
            return response()->json([
                'success'   => false,
                'message'   => $e->getMessage(),
                'error'     => $output_error,
            ], 500);
		}
	}

}
