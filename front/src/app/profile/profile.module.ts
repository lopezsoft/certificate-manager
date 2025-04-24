import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ProfileRoutingModule } from './profile-routing.module';
import { ProfileContainerComponent } from './profile-container.component';
import { CompanyComponent } from './company/company.component';
import { CoreModule } from '../@core/core.module';
import {UsersEditComponent, ProfileComponent} from "./users";


@NgModule({
  declarations: [
    ProfileComponent,
    UsersEditComponent,
    ProfileContainerComponent,
    CompanyComponent,
  ],
  imports: [
    CommonModule,
    CoreModule,
    ProfileRoutingModule
  ]
})
export class ProfileModule { }
