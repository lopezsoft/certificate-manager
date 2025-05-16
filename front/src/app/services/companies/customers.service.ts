import { Injectable } from '@angular/core';
import { Company } from '../../models/companies-model';
import { map } from 'rxjs/operators';
import { Observable } from 'rxjs';
import { CrudTableService } from '../crud-table.service';
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
    private _crud: CrudTableService
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
}
