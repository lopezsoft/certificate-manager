import { Injectable } from '@angular/core';
import {HttpResponsesService} from "../utils";
import {map} from "rxjs/operators";
import {Observable} from "rxjs";
import {DataRecords} from "../interfaces";
import {Shipping} from "../interfaces/shipping-intetface";

@Injectable({
  providedIn: 'root'
})
export class ShippingService {
  public currentShipping: Shipping;
  public shippingData: Shipping[] = [];
  public shippingDataRecords: DataRecords;
  
  constructor(
    private http: HttpResponsesService
  ) { }
  
  getShipping(params: any = {}): Observable<Shipping[]> {
    return this.http.get('/documents', params)
      .pipe(map((res) => {
        this.shippingData = res.dataRecords.data;
        this.shippingDataRecords = res.dataRecords;
        return res.dataRecords.data;
      }));
  }

  deleteDocument(id: number) {
    return this.http.delete(`/documents/${id}`);
  }
}
