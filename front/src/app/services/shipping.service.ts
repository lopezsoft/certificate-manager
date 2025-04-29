import { Injectable } from '@angular/core';
import {HttpResponsesService} from "../utils";
import {map} from "rxjs/operators";
import {Observable} from "rxjs";
import {DataRecords} from "../interfaces";
import {Shipping} from "../interfaces/shipping-intetface";
import {CertificateRequest} from "../interfaces/file-manager.interface";

@Injectable({
  providedIn: 'root'
})
export class ShippingService {
  public currentShipping: CertificateRequest;
  public shippingData: CertificateRequest[] = [];
  public shippingDataRecords: DataRecords;

  public currentRequestAll: CertificateRequest;
  public requestDataAll: CertificateRequest[] = [];
  public requestDataRecordsAll: DataRecords;
  
  constructor(
    private http: HttpResponsesService
  ) { }
  
  getShipping(params: any = {}): Observable<CertificateRequest[]> {
    return this.http.get('/certificate-request', params)
      .pipe(map((res) => {
        this.shippingData = res.dataRecords.data;
        this.shippingDataRecords = res.dataRecords;
        return res.dataRecords.data;
      }));
  }

  getAll(params: any = {}): Observable<CertificateRequest[]> {
    return this.http.get('/certificate-request/all', params)
        .pipe(map((res) => {
          this.requestDataAll = res.dataRecords.data;
          this.requestDataRecordsAll = res.dataRecords;
          return res.dataRecords.data;
        }));
  }

  deleteDocument(id: number) {
    return this.http.delete(`/certificate-request/${id}`);
  }
}
