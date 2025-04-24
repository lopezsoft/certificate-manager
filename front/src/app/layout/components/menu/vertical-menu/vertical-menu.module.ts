import { NgModule } from '@angular/core';
import { RouterModule } from '@angular/router';

import { PERFECT_SCROLLBAR_CONFIG } from 'ngx-perfect-scrollbar';
import { PerfectScrollbarConfigInterface } from 'ngx-perfect-scrollbar';

import { CoreMenuModule } from '@core/components';
import { CoreCommonModule } from '@core/common.module';

import { VerticalMenuComponent } from 'app/layout/components/menu/vertical-menu/vertical-menu.component';

const DEFAULT_PERFECT_SCROLLBAR_CONFIG: PerfectScrollbarConfigInterface = {
  suppressScrollX: true,
  wheelPropagation: false
};
@NgModule({
  declarations: [VerticalMenuComponent],
  imports: [CoreMenuModule, CoreCommonModule, RouterModule],
  providers: [
    {
      provide: PERFECT_SCROLLBAR_CONFIG,
      useValue: DEFAULT_PERFECT_SCROLLBAR_CONFIG
    }
  ],
  exports: [VerticalMenuComponent]
})
export class VerticalMenuModule {}
