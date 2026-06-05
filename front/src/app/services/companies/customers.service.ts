import { Injectable } from '@angular/core';
import { Company } from '../../models/companies-model';
import { CertificateRequestStats } from '../../models/certificate-stats.model';
import { map } from 'rxjs/operators';
import { Observable } from 'rxjs';
import { CrudTableService } from '../crud-table.service';
import { HttpResponsesService } from '../../utils';
import { DataRecords } from 'app/interfaces';

@Injectable({
  providedIn: 'root'
})
export class CustomerService {

  data: Company[] = [];
  currentCustomer: Company;
  dataRecords: DataRecords;
  protected _table = 'T001';
  constructor(
    private _crud: CrudTableService,
    private http: HttpResponsesService,
  ){}

  getData(params: any = {}): Observable<Company[]> {
    const ts  = this;
    params.tbPrefix = ts._table;
    params.order = JSON.stringify({
      'company_name' : 'asc'
    });
    return ts._crud.getData(params)
      .pipe( map ( (resp ) => {
        ts.dataRecords = resp;
        ts.data = resp.data;
        return resp.data;
      }));
  }

  /**
   * Obtiene estadísticas de solicitudes de certificados para una empresa.
   * GET /certificate-request/stats/{companyId}
   */
  getStats(companyId: number): Observable<CertificateRequestStats> {
    return this.http.get(`/certificate-request/stats/${companyId}`)
      .pipe(map((resp: any) => resp.dataRecords));
  }

  /**
   * Toggle active/inactive para una empresa.
   * PATCH /company/{id}/toggle-active
   */
  toggleActive(companyId: number): Observable<any> {
    return this.http.patch(`/company/${companyId}/toggle-active`, {});
  }
}
