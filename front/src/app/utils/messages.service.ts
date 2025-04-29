import { Injectable } from '@angular/core';
import { ToastrService } from 'ngx-toastr';

import Swal from 'sweetalert2';
import {TranslateService} from "@ngx-translate/core";
import {GlobalSettingsService} from "../services/global-settings.service";

@Injectable({
  providedIn: 'root'
})

export class MessagesService {
  constructor(
      private toastr: ToastrService,
      public _translateService: TranslateService,
      public settings: GlobalSettingsService
  ) {
  }
  confirm(title: string, message: string) {
    return Swal.fire({
      title: title,
      html: message,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: this._translateService.instant('buttons.yes'),
      cancelButtonText: this._translateService.instant('buttons.not'),
    });
  }
  toastMessage(title: string, msg: string, type: number = 0){
    switch (type) {
      case 2:
        this.toastr.info(msg, title, {positionClass: 'toast-bottom-right'});
        break;
      case 3:
        this.toastr.warning(msg, title, {positionClass: 'toast-bottom-right'});
        break;
      case 4:
        this.toastr.error(msg, title, {positionClass: 'toast-bottom-right'});
        break;
      default:
        this.toastr.success(msg, title, {positionClass: 'toast-bottom-right'});
        break;
    }
  }

  onMessage(title: string, msg: string) {
    Swal.fire((title.length > 1) ? title :  "CERTIFICATE MANAGER", msg, "info");
  }
  errorMessage(title: string, msg: string) {
    Swal.fire((title.length > 1) ? title :  "Error CERTIFICATE MANAGER", msg, "error");
  }

}
