<?php

namespace App\Core;

class MasterModel
{

     /**
     * PPP.
     * Código asignado por la DIAN al PT de tres (3) dígitos.
     * @var string
     */
    public $ppp = '000';

    public function uploadFileData($data,$path): bool|string
    {
        $fileSize    = file_put_contents($path, $data);
		if ($fileSize > 0) {
            $name   = basename($path);
            $format = pathinfo($path, PATHINFO_EXTENSION);
			$request = array(
                'success'       => TRUE,
                'name'          => $name,
                'format'        => $format,
				'size'			=> round((($fileSize/1024)/1024),3)
			);
        }else{
            $request = array(
                'success'       =>FALSE
			);
        }
        return json_encode($request);
    }

    public function putFile($file,$path): bool
    {
        $fileName = $file->getClientOriginalName();
		$fileTmp  = $file->getPathName();
        if (is_uploaded_file($fileTmp)){
            $afile	= $path.'/'.basename($fileName);
            return move_uploaded_file($fileTmp,$afile);
        }else{
            return false;
        }
    }

}
