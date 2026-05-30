import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ExodoGridModule } from 'exodolibs';

import { OrdersRoutingModule } from './orders-routing.module';
import { OrderListComponent } from './order-list/order-list.component';
import { PurchaseComponent } from './purchase/purchase.component';
import { QuotaBannerComponent } from './quota-banner/quota-banner.component';
import { CommonComponentsModule } from '../common/common-components.module';
import { CoreModule } from '../@core/core.module';

@NgModule({
  declarations: [
    OrderListComponent,
    PurchaseComponent,
    QuotaBannerComponent,
  ],
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterModule,
    OrdersRoutingModule,
    ExodoGridModule,
    CommonComponentsModule,
    CoreModule,
  ],
  exports: [
    QuotaBannerComponent,
  ]
})
export class OrdersModule {}
