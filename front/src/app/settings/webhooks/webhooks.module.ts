import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { CoreModule } from '../../@core/core.module';
import { CoreCommonModule } from '../../../@core/common.module';
import { CommonComponentsModule } from '../../common/common-components.module';

import { WebhooksRoutingModule } from './webhooks-routing.module';
import { WebhooksListComponent } from './webhooks-list/webhooks-list.component';
import { WebhookFormComponent } from './webhook-form/webhook-form.component';
import { WebhookSecretModalComponent } from './webhook-secret-modal/webhook-secret-modal.component';
import { WebhookDeliveriesComponent } from './webhook-deliveries/webhook-deliveries.component';

@NgModule({
  declarations: [
    WebhooksListComponent,
    WebhookFormComponent,
    WebhookSecretModalComponent,
    WebhookDeliveriesComponent,
  ],
  imports: [
    CommonModule,
    CoreModule,
    CoreCommonModule,
    CommonComponentsModule,
    WebhooksRoutingModule,
  ],
})
export class WebhooksModule { }
