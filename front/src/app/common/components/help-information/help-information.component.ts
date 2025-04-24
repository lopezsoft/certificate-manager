import {Component, Input, ViewEncapsulation} from '@angular/core';

@Component({
  selector: 'app-help-information',
  templateUrl: './help-information.component.html',
  styleUrls: ['./help-information.component.scss'],
  encapsulation: ViewEncapsulation.None,
})
export class HelpInformationComponent {
  @Input() imgWidth: string;
  @Input() imgHeight: string;
  constructor() {
    this.imgWidth = '128';
    this.imgHeight = '128';
  }
}
