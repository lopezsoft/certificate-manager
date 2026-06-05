import { NgModule } from '@angular/core';

import { VerticalLayoutModule } from 'app/layout/vertical/vertical-layout.module';
import { HorizontalLayoutModule } from 'app/layout/horizontal/horizontal-layout.module';

@NgModule({
  imports: [VerticalLayoutModule, HorizontalLayoutModule],
  exports: [VerticalLayoutModule, HorizontalLayoutModule]
})
export class LayoutModule {}

