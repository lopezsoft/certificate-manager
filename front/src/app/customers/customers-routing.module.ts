import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {
  CustomersComponent,
  CustomerFormComponent,
  CustomerCreateComponent
} from './index';

const routes: Routes = [
  {
    path: '',
    component: CustomersComponent,
  },
  {
    path: 'edit/:id',
    component: CustomerFormComponent
  },
  {
    path: 'create',
    component: CustomerCreateComponent
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CustomersRoutingModule { }
