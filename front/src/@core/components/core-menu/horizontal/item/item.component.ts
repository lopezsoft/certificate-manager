import { Component, HostBinding, Input } from '@angular/core';

@Component({
    selector: '[core-menu-horizontal-item]',
    templateUrl: './item.component.html',
    standalone: false
})
export class CoreMenuHorizontalItemComponent {
  @Input()
  item: any;
}
