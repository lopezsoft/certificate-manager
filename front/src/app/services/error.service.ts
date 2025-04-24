import { Injectable } from '@angular/core';
import {ToastrService} from 'ngx-toastr';
@Injectable({
  providedIn: 'root'
})
export class ErrorService {

  constructor(
    public toastr: ToastrService,
  ) { }

  showError(message: string, status: number): void {
    this.toastr.error(message, 'MATIAS API', {positionClass: 'toast-bottom-full-width'});
  }
}
