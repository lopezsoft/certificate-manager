import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {LoginComponent, RegisterComponent, RecoverComponent, ResetPasswordComponent} from './index';
import {EmailResendComponent} from "./email-resend/email-resend.component";
import {NotAuthorizedComponent} from "./not-authorized/not-authorized.component";

const routes: Routes = [
  {
    path: '',
    children: [
      {
        path: 'login',
        component: LoginComponent,
        data: { animation: 'auth' }
      },
      {
        path: 'register',
        component: RegisterComponent,
        data: { animation: 'auth' }
      },
      {
        path: 'forgot-password',
        component: RecoverComponent,
        data: { animation: 'auth' }
      },
      {
        path: 'email-resend',
        component: EmailResendComponent,
        data: { animation: 'auth' }
      },
      {
        path: 'password-reset/:token',
        component: ResetPasswordComponent,
        data: { animation: 'auth' }
      },
      {
        path: 'not-authorized',
        component: NotAuthorizedComponent,
        data: { animation: 'auth' }
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AuthRoutingModule { }
