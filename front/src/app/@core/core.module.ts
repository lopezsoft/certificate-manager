import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

import { ngxLoadingAnimationTypes, NgxLoadingModule } from 'ngx-loading';

import{ jqxEditorModule } from 'jqwidgets-ng/jqxeditor';
import { NgSelectModule } from '@ng-select/ng-select';
/*
  * Translation
*/
import { TranslateModule } from '@ngx-translate/core';

import { SidebarComponent, FooterComponent, HeaderComponent, BodyComponent } from './layout';
import {
  FooterFormComponent,
 } from '.';
import { DataGridComponent } from './components/grid/data-grid.component';
import {CustomTooltipDirective} from "./directives/custom-tooltip.directive";
@NgModule({
  exports: [
      SidebarComponent,
      FooterComponent,
      HeaderComponent,
      BodyComponent,
      FooterFormComponent,
      RouterModule,
      CommonModule,
      jqxEditorModule,
      TranslateModule,
      NgSelectModule,
      NgxLoadingModule,
      ReactiveFormsModule,
      FormsModule,
      CustomTooltipDirective
	],
  declarations: [
    FooterFormComponent,
    SidebarComponent,
    FooterComponent,
    HeaderComponent,
    BodyComponent,
    DataGridComponent,
      CustomTooltipDirective
	],
  imports: [
    RouterModule,
    CommonModule,
    TranslateModule,
    NgSelectModule,
    ReactiveFormsModule,
    FormsModule,
    NgxLoadingModule.forRoot({
      animationType: ngxLoadingAnimationTypes.circleSwish,
      backdropBackgroundColour: 'rgba(0,0,0,0.75)',
      backdropBorderRadius: '4px',
      primaryColour: '#ffffff',
      secondaryColour: '#ffffff',
      fullScreenBackdrop: true,
      tertiaryColour: '#ffffff'
    }),
  ],
})
export class CoreModule { }

