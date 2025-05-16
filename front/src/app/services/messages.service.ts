import { Injectable } from '@angular/core';
import { ToastrService } from 'ngx-toastr';

import Swal, {SweetAlertIcon} from 'sweetalert2';

@Injectable({
  providedIn: 'root'
})
export class MessagesService {
  constructor(
    private toastr: ToastrService,
  ) {

  }
  toastMessage(title: string, msg: string, type: number = 0) {
    switch (type) {
      case 2:
        this.toastr.info(msg, title, {positionClass: 'toast-bottom-full-width'});
        break;
      case 3:
        this.toastr.warning(msg, title, {positionClass: 'toast-bottom-full-width'});
        break;
      case 4:
        this.toastr.error(msg, title, {positionClass: 'toast-bottom-full-width'});
        break;
      default:
        this.toastr.success(msg, title, {positionClass: 'toast-bottom-full-width'});
        break;
    }
  }

  onMessage(title: string, msg: string, iconMsg: SweetAlertIcon = 'info') {
    const titleMsg = (title.length > 1) ? title :  'CERTIFICATE MANAGER'
    Swal.fire({
        title: '<strong>' + titleMsg + '</strong>',
        icon: iconMsg,
        html: msg,
    }).then();
  }
  errorMessage(title: string, msg: string) {
    Swal.fire((title.length > 1) ? title :  'Error CERTIFICATE MANAGER', msg, 'error');
  }

  confirm(title: string, message: string) {
    return Swal.fire({
      title: title,
      html: message,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Si',
      cancelButtonText: 'No',
    });
  }

	error(err: any) {
    if (err.error) {
      if (err.error.message) {
        this.errorMessage('', err.error.message);
      } else {
        this.errorMessage('', err.error);
      }
    } else {
      this.errorMessage('', err);
    }
	}
}
