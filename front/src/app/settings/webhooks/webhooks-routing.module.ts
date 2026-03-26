import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { WebhooksListComponent } from './webhooks-list/webhooks-list.component';
import { WebhookFormComponent } from './webhook-form/webhook-form.component';
import { WebhookDeliveriesComponent } from './webhook-deliveries/webhook-deliveries.component';

const routes: Routes = [
  { path: '', component: WebhooksListComponent },
  { path: 'new', component: WebhookFormComponent },
  { path: ':id/edit', component: WebhookFormComponent },
  { path: ':id/deliveries', component: WebhookDeliveriesComponent },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class WebhooksRoutingModule { }
