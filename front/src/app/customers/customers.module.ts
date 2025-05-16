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
import { CommonComponentsModule } from 'app/common/common-components.module';
import { CustomerViewComponent } from './customer-view/customer-view.component';


@NgModule({
  declarations: [
    CustomersComponent,
    CustomerFormComponent,
    CustomerCreateComponent,
    CustomerViewComponent
  ],
  imports: [
    CommonModule,
    CustomersRoutingModule,
    CoreModule,
    CommonComponentsModule,
    ExodolibsModule
  ]
})
export class CustomersModule { }
