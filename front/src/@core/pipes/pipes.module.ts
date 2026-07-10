import { NgModule } from '@angular/core';

import { FilterPipe } from '@core/pipes/filter.pipe';

import { InitialsPipe } from '@core/pipes/initials.pipe';

import { SafePipe } from '@core/pipes/safe.pipe';
import { StripHtmlPipe } from '@core/pipes/stripHtml.pipe';
import { WebhookHealthPipe } from '@core/pipes/webhook-health.pipe';
import { WebhookEventPipe } from '@core/pipes/webhook-event.pipe';
import { FallbackImagePipe } from '@core/pipes/fallback-image.pipe';

@NgModule({
  declarations: [InitialsPipe, FilterPipe, StripHtmlPipe, SafePipe, WebhookHealthPipe, WebhookEventPipe, FallbackImagePipe],
  imports: [],
  exports: [InitialsPipe, FilterPipe, StripHtmlPipe, SafePipe, WebhookHealthPipe, WebhookEventPipe, FallbackImagePipe]
})
export class CorePipesModule { }
