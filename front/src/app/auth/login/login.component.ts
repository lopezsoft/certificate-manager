import {AfterViewInit, Component, ElementRef, OnInit, ViewChild, ViewEncapsulation} from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import { ErrorResponse } from '../../interfaces';
import { HttpResponsesService, MessagesService } from '../../utils';
import {CoreConfigService} from "../../../@core/services/config.service";
import {AuthMasterComponent} from "../auth-master/auth-master.component";
import {TranslateService} from "@ngx-translate/core";
import {GlobalSettingsService} from "../../services/global-settings.service";
import TokenService from "../../utils/token.service";

import {environment} from '../../../environments/environment';
@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss'],
  encapsulation: ViewEncapsulation.None
})
export class LoginComponent extends AuthMasterComponent implements OnInit, AfterViewInit {
  @ViewChild('email') email !: ElementRef;
  public passwordTextType: boolean;
  public year = new Date().getFullYear();
   environment = environment;
  constructor(private fb: FormBuilder,
              private _http: HttpResponsesService,
              private _msg: MessagesService,
              public translate: TranslateService,
              public _coreConfigService: CoreConfigService,
              public _globalSettings: GlobalSettingsService,
              public authService: TokenService,
              public _router: Router)
  {
    super(_coreConfigService, translate, _router, authService);
  }

  ngOnInit(): void {
    this.onCreateForm();
    super.ngOnInit();
  }

  ngAfterViewInit(): void {
    const ts    = this;
    setTimeout(() => {
      ts.email.nativeElement.focus();
    }, 100);
  }

  get f() {
    return this.customForm.controls;
  }
  // convenience getter for easy access to form fields

  /**
   * Toggle password
   */
  togglePasswordTextType() {
    this.passwordTextType = !this.passwordTextType;
  }


  onCreateForm() : void {
    const ts  = this;
    ts.customForm = ts.fb.group({
      email     : ['', [
        Validators.email,
        Validators.required,
        Validators.minLength(3),
        Validators.pattern('^[a-z0-9._%+-ñ]+@[a-z0-9._%+-ñ]+\.[a-z]{2,4}$')]],
      password  : ['', [Validators.required, Validators.minLength(1)]]
    });
  }

  onLogin() : void {
    const ts    = this;
    const frm   = ts.customForm;
    ts.onValidateForm(frm);
    if(frm.invalid) {
      ts._msg.errorMessage('Error','Por favor llene la información de cada campo.');
      return;
    }
    let params          =  frm.getRawValue();
    params.remember_me  = 0;
    ts._globalSettings.showBlockUI();
    ts._http.post('/auth/login',params)
      .subscribe({
        next: (resp) => {
          ts._globalSettings.hideBlockUI();
          localStorage.setItem(`${ts._http.getApiJwt()}`, JSON.stringify(resp));
          ts._router.navigate(['/dashboard']);
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        },
        error: (err: ErrorResponse) => {
          ts._globalSettings.hideBlockUI();
          ts._msg.errorMessage('Inicio de sesión', err.error.message);
        }
      });
  }
}
