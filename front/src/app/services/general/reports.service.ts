import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';


import { HttpResponsesService, MessagesService } from '../../utils/index';

import { Sales, SaleDetails} from './../../models/reports-model';

@Injectable({
  providedIn: 'root'
})
export class ReportsService {
  constructor(
    private api: HttpResponsesService,
    private msg: MessagesService,
  ) { }

  getAccountingDocumentsReport(pId: any) {
    const ts  = this;
    return ts.api.get(`/accounting/supports/report/${pId}`)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp;
      }));
  }

  getAccountingDocuments(params: any) {
    const ts  = this;
    return ts.api.get('/accounting/supports/read', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp;
      }));
  }

  getInventoryReport(params: any) {
    const ts  = this;
    return ts.api.get('/reports/inventory', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp;
      }));
  }

  getClosingSalesReport(params: any) {
    const ts  = this;
    return ts.api.get('/reports/sales/closingsales', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp;
      }));
  }

  getSalesReport(params: any) {
    const ts  = this;
    return ts.api.get('/reports/sales/report', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp;
      }));
  }

  getSaleDetails(id: number): Observable<SaleDetails[]> {
    const ts  = this;
    return ts.api.get(`/reports/sales/detail/${id}`)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }

  getNotes(params: any): Observable<Sales[]> {
    const ts  = this;
    return ts.api.get('/reports/notes', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }

  getSales(params: any): Observable<Sales[]> {
    const ts  = this;
    return ts.api.get('/reports/sales', params)
      .pipe( map ( (resp: JsonResponse ) => {
        return resp.records;
      }));
  }

  onNote(ele: Sales)  {
    const ts = this;
    return ts.api.post(`/reports/note`, {
        pdbId       : ele.id,
        invoiceNro  : ele.invoice_nro,
    }).pipe( map ( (resp: JsonResponse) => {
      if(resp.success){
        ts.msg.toastMessage('Generado correctamente', resp.message);
      }else{
        ts.msg.toastMessage('Error', resp.message, 4);
      }
      return resp;
    }));
  }

  onInvoice(ele: Sales)  {
    const ts = this;
    return ts.api.post(`/reports/invoice`, {
        pdbId       : ele.id,
        invoiceNro  : ele.invoice_nro,
    }).pipe( map ( (resp: JsonResponse) => {
      if(resp.success){
        ts.msg.toastMessage('Generado correctamente', resp.message);
      }else{
        ts.msg.toastMessage('Error', resp.message, 4);
      }
      return resp;
    }));
  }

  onSendMailNotes(ele: Sales) {
    const ts = this;
    return ts.api.post(`/notes/sendMail/${ ele.id }`)
    .pipe( map ( (resp: JsonResponse) => {
      if(resp.success){
        ts.msg.toastMessage('Enviado correctamente', resp.message);
      }else{
        ts.msg.toastMessage('Error', resp.message, 4);
      }
      return resp;
    }));
  }

  onSendMail(ele: Sales) {
    const ts = this;
    return ts.api.post(`/sendMail/${ ele.id }`)
    .pipe( map ( (resp: JsonResponse) => {
      if(resp.success){
        ts.msg.toastMessage('Enviado correctamente', resp.message);
      }else{
        ts.msg.toastMessage('Error', resp.message, 4);
      }
      return resp;
    }));
  }

}
