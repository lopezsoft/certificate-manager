import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

import { ngxLoadingAnimationTypes, NgxLoadingModule } from 'ngx-loading';
import { NgSelectModule } from '@ng-select/ng-select';

import { AuthRoutingModule } from './auth-routing.module';
import {LoginComponent, RegisterComponent, RecoverComponent, ResetPasswordComponent} from './index';
import { AuthComponent } from './auth.component';

import { CoreModule } from '../@core/core.module';
import {AuthMasterComponent} from "./auth-master/auth-master.component";
import {EmailResendComponent} from "./email-resend/email-resend.component";
import {CoreCommonModule} from "../../@core/common.module";
import {NotAuthorizedComponent} from "./not-authorized/not-authorized.component";


@NgModule({
  declarations: [
    LoginComponent,
    AuthComponent,
    RegisterComponent,
    RecoverComponent,
    AuthMasterComponent,
    ResetPasswordComponent,
    EmailResendComponent,
    NotAuthorizedComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    NgSelectModule,
    CoreModule,
    NgxLoadingModule.forRoot({
      animationType: ngxLoadingAnimationTypes.circleSwish,
      backdropBackgroundColour: 'rgba(0,0,0,0.75)',
      backdropBorderRadius: '4px',
      primaryColour: '#ffffff',
      secondaryColour: '#ffffff',
      fullScreenBackdrop: true,
      tertiaryColour: '#ffffff'
    }),
    ReactiveFormsModule,
    AuthRoutingModule,
    CoreCommonModule
  ]
})
export class AuthModule { }
