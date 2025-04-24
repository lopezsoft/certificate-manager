import {Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {FormBuilder, FormGroup, Validators} from '@angular/forms';

// Base component
import {TranslateService} from '@ngx-translate/core';
import {ActivatedRoute, Router} from '@angular/router';
import {AuthMasterComponent} from '../auth-master/auth-master.component';
import {HttpResponsesService, MessagesService} from '../../utils';

import {CoreConfigService} from '../../../@core/services/config.service';
import {GlobalSettingsService} from '../../services/global-settings.service';
import TokenService from "../../utils/token.service";

import {environment} from '../../../environments/environment';

@Component({
  selector: 'app-email-resend',
  templateUrl: './email-resend.component.html',
  styleUrls : ['./email-resend.scss']
})

export class EmailResendComponent extends AuthMasterComponent implements OnInit {
  @ViewChild('focusElement') focusElement: ElementRef;
  customForm: FormGroup;
  sendEmail = false;
  environment = environment;
  constructor(
    public fb: FormBuilder,
    public translate: TranslateService,
    public router: Router,
    public api: HttpResponsesService,
    public msg: MessagesService,
    public aRouter: ActivatedRoute,
    
    public coreConfigService: CoreConfigService,
    public _globalSettings: GlobalSettingsService,
    public authService: TokenService
  ) {
    super(coreConfigService, translate, router, authService);
    this.customForm = this.fb.group({
      email			        : ['', [Validators.required, Validators.pattern('^[a-z0-9._%+-ñ]+@[a-z0-9._%+-ñ]+\.[a-z]{2,4}$')]]
    });
  }
  ngOnInit() {
    super.ngOnInit();
  }

  get invalidEmail() {
    return this.isInvalid('email');
  }

  // placeholder

  get placeholderEmail(): string {
    return this.translate.instant('placeholder.email');
  }
  onSave(): void {
    const me    = this.customForm;
    const lang  = this.translate;
    this._globalSettings.showBlockUI(lang.instant('resend.button.resending'));
    if (me.invalid) {
      this.onValidateForm(me);
      this.msg.toastMessage(lang.instant('titleMessages.emptyFields'), lang.instant('bodyMessages.emptyFields'), 4);
      this.disableMsg();
      return;
    }
    this.loading = true;
    this.api.post(`/email/verification-notification`, {
      email: me.value
      })
      .subscribe({
        next: (resp) => {
            me.reset();
            this.disableMsg();
            this.sendEmail = true;
            this.msg.onMessage(lang.instant('register.messages.successfulRegistration'), resp.message);
        },
        error: (err: any) => {
            this.disableMsg();
            console.log(err);
            this.msg.errorMessage(lang.instant('general.error'), err.error.message || err.message || 'Error al enviar el correo electrónico');
        }
    });

  }
  disableMsg(): void {
    this._globalSettings.hideBlockUI();
    this.loading = false;
  }

}
