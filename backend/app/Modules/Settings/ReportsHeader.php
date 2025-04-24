<?php

namespace App\Modules\Settings;

use App\Modules\Company\CompanyQueries;
use App\Queries\QueryTable;
use App\Queries\UpdateTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportsHeader
{
    private string $table  = 'reports_header';

    /**
     * @throws \Exception
     */
    public function getData(Request $request): JsonResponse
    {
        $company    = CompanyQueries::getCompany();
        $report     = DB::table($this->table)
            ->where('company_id', $company->id)
            ->first();
        if(!$report) {
            $data   = [
                'company_id'    => $company->id
            ];
            DB::table($this->table)->insert($data);
            DB::table($this->table)
                ->where('company_id', $company->id)
                ->first();
        }

        $whereSend  = [
            'company_id'    => $company->id
        ];
        $request->where = $whereSend;
        return QueryTable::table($this->table, $whereSend);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $records        = json_decode($request->input('records'));
        $records->id    = $id;
        $company_id     = CompanyQueries::getCompany()->id;
        if(isset($records->imgdata)){
            $occurs    = strpos($records->imgdata, ",");
            //get the base-64 from data
            $base64_str = substr($records->imgdata, strpos($records->imgdata, ",") + 1);

            if(!empty($base64_str) &&  $occurs > 0){
                //decode base64 string
                $image              = base64_decode($base64_str);
                $imgName            = $records->imgname;
                $records->image     = $this->putFile($company_id, $image, $imgName);
            }
        }
        return UpdateTable::update($request, $records, $this->table);
    }


    private function putFile($company_id, $data, $imgName): string
    {
        $extension      = pathinfo($imgName, PATHINFO_EXTENSION);
        $imageName      = Str::uuid() . '.' . $extension;
        $path  = "companies/{$company_id}/reports/logo/".$imageName;
        Storage::disk('public')->put($path, $data);
        return Storage::url($path);
    }
}
