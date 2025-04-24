import { Component, OnInit, ViewEncapsulation } from '@angular/core';
import {FormBuilder, Validators} from '@angular/forms';

import { CoreConfigService } from '@core/services/config.service';
import {ActivatedRoute, Router} from "@angular/router";
import {HttpResponsesService, MessagesService} from "../../utils";
import {ErrorResponse} from "../../interfaces";
import {AuthMasterComponent} from "../auth-master/auth-master.component";
import {TranslateService} from "@ngx-translate/core";
import TokenService from "../../utils/token.service";
import {GlobalSettingsService} from "../../services/global-settings.service";

@Component({
  selector: 'app-reset-password',
  templateUrl: './reset-password.component.html',
  styleUrls: ['./reset-password.component.scss'],
  encapsulation: ViewEncapsulation.None
})
export class ResetPasswordComponent extends AuthMasterComponent implements OnInit {
  // Public
  public passwordTextType: boolean;
  public confPasswordTextType: boolean;
  public submitted = false;
  public token: string;
  public email: string;
  
  constructor(
      public api: HttpResponsesService,
      private route: ActivatedRoute,
      public fb: FormBuilder,
      public msg: MessagesService,
      public router: Router,
      public aRouter: ActivatedRoute,
      public _coreConfigService: CoreConfigService,
      public translate: TranslateService,
      public _token: TokenService,
      public _globalSettings: GlobalSettingsService,
  ) {
    super(_coreConfigService, translate, router, _token);
  }

  /**
   * Toggle password
   */
  togglePasswordTextType() {
    this.passwordTextType = !this.passwordTextType;
  }

  /**
   * Toggle confirm password
   */
  toggleConfPasswordTextType() {
    this.confPasswordTextType = !this.confPasswordTextType;
  }

  /**
   * On Submit
   */
  onSubmit() {
    const ts  = this;
    const lang= ts.translate;
    if(ts.customForm.invalid) {
      ts.msg.toastMessage(lang.instant('titleMessages.emptyFields'), lang.instant('bodyMessages.emptyFields'), 4);
      return;
    }
    let values = ts.customForm.getRawValue();
    values.token = ts.token;
    values.email = ts.email;
    ts._globalSettings.loading = true;
    ts.api.post('/reset-password', values).
    subscribe({
      next: (resp) => {
        ts._globalSettings.loading = false;
        if (!resp.success) {
          ts.msg.errorMessage('', resp.message);
          return;
        }
        ts.msg.onMessage('', resp.message);
      },
      error: (err: ErrorResponse) => {
        ts._globalSettings.loading = false;
        ts.msg.errorMessage('Error', err.error.message || err.message);
      }
    });
  }

  // Lifecycle Hooks
  // -----------------------------------------------------------------------------------------------------

  /**
   * On init
   */
  ngOnInit(): void {
    super.ngOnInit();
    this.customForm = this.fb.group({
      password: ['', [Validators.required]],
      password_confirmation: ['', [Validators.required]]
    });

    this.email = this.route.snapshot.queryParams['email'];
    this.token = this.route.snapshot.params['token'];
  }
}
