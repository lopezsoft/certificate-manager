import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import {NotificationCardComponent} from './components/notification-card/notification-card.component';
import {HelpInformationComponent} from "./components/help-information/help-information.component";
import {SupportButtonComponent} from "./components/support-button/support-button.component";
import {CoreModule} from "../@core/core.module";
import {ActionsToolbarComponent} from "./components/actions-toolbar/actions-toolbar.component";
import {LayoutComponentComponent} from "./components/layout-component/layout-component.component";
import {SearchDataComponent} from "./components/search-data/search-data.component";
@NgModule({
  declarations: [
    NotificationCardComponent,
    HelpInformationComponent,
    SupportButtonComponent,
    ActionsToolbarComponent,
    LayoutComponentComponent,
    SearchDataComponent
  ],
  imports: [
    CommonModule,
    CoreModule
  ],
  exports: [
    NotificationCardComponent,
    HelpInformationComponent,
    SupportButtonComponent,
    ActionsToolbarComponent,
    LayoutComponentComponent,
    SearchDataComponent
  ]
})
export class CommonComponentsModule { }
