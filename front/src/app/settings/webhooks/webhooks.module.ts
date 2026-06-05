import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { CoreModule } from '../../@core/core.module';
import { CoreCommonModule } from '../../../@core/common.module';
import { CommonComponentsModule } from '../../common/common-components.module';

import { HighlightModule, HIGHLIGHT_OPTIONS } from 'ngx-highlightjs';

import { WebhooksRoutingModule } from './webhooks-routing.module';
import { WebhooksListComponent } from './webhooks-list/webhooks-list.component';
import { WebhookFormComponent } from './webhook-form/webhook-form.component';
import { WebhookSecretModalComponent } from './webhook-secret-modal/webhook-secret-modal.component';
import { WebhookDeliveriesComponent } from './webhook-deliveries/webhook-deliveries.component';
import { JsonViewerComponent } from './json-viewer/json-viewer.component';

@NgModule({
  declarations: [
    WebhooksListComponent,
    WebhookFormComponent,
    WebhookSecretModalComponent,
    WebhookDeliveriesComponent,
    JsonViewerComponent,
  ],
  imports: [
    CommonModule,
    CoreModule,
    CoreCommonModule,
    CommonComponentsModule,
    HighlightModule,
    WebhooksRoutingModule,
  ],
  providers: [
    {
      provide: HIGHLIGHT_OPTIONS,
      useValue: {
        coreLibraryLoader: () => import('highlight.js/lib/core'),
        languages: {
          json: () => import('highlight.js/lib/languages/json')
        }
      }
    }
  ]
})
export class WebhooksModule { }
