import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ExodolibsModule } from 'exodolibs';
import { CustomersRoutingModule } from './customers-routing.module';
import { CoreModule } from '../@core/core.module';
import {
  CustomerFormComponent,
  CustomersComponent,
  CustomerCreateComponent
} from "./index";


@NgModule({
  declarations: [
    CustomersComponent,
    CustomerFormComponent,
    CustomerCreateComponent
  ],
  imports: [
    CommonModule,
    CustomersRoutingModule,
    CoreModule,
    ExodolibsModule
  ]
})
export class CustomersModule { }
