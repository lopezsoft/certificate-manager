import { Component, EventEmitter, OnInit, Output } from '@angular/core';

import { Router } from '@angular/router';

import { ErrorResponse } from '../../../interfaces';

import Swal from 'sweetalert2';
import { TranslateService } from '@ngx-translate/core';
import TokenService from '../../../utils/token.service';

import { HttpResponsesService, MessagesService } from '../../../utils';
import { CompanyService } from '../../../services/companies/company.service';

@Component({
  selector: 'app-header',
  templateUrl: './header.component.html',
  styleUrls: ['./header.component.scss']
})
export class HeaderComponent implements OnInit {

  public companyData: any = null;
  public companyImg: string = '';

  @Output() onToggleChange = new EventEmitter();
  constructor(
    public translate: TranslateService,
    public router: Router,
    public _api: HttpResponsesService,
    public _token: TokenService,
    public msg: MessagesService,
    public company: CompanyService,
  ) {

   }

  ngOnInit(): void {
    this.company.getData({})
      .subscribe({
        next: (resp)  => {
          this.companyData  = resp[0];
          this.companyImg   = this.companyData.full_path_image ? this.companyData.full_path_image : '';
        }
      });
  }
  onToggleChangeMessage(): void {
		this.onToggleChange.emit();
	}

  /**
   * Logout method
   */
  logout() {
    const ts    = this;
    const lang  = ts.translate;
    Swal.fire({
      title : lang.instant('Cerrar sesión'),
      text  : lang.instant('¿Seguro que desea salir del sistema?'),
      icon  : 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Si',
      cancelButtonText: 'No'
    }).then((result) => {
      if (result.value) {
        ts._api.get('/auth/logout', {})
        .subscribe({
            next: () => {
            ts.router.navigate(['/auth/login']);
            localStorage.removeItem(ts._api.getApiJwt());
            ts._token.onClearCurrentUser();
            setTimeout(() => {
              window.location.reload();
            }, 500);
          },
          error: (err: ErrorResponse) => {
            ts.msg.toastMessage(lang.instant('general.error'), err.error.message, 4);
          }
        });
      }
    });
  }
}
