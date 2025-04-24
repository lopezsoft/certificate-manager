import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CompanyComponent } from './company/company.component';
import { ProfileContainerComponent } from './profile-container.component';
import {ProfileComponent} from "./users";

const routes: Routes = [
  {
    path: '',
    component: ProfileContainerComponent,
  },
  {
    path: 'company',
    component: CompanyComponent,
  },
  {
    path: 'user',
    component: ProfileComponent,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ProfileRoutingModule { }
