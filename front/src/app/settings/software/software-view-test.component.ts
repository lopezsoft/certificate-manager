import {Component, EventEmitter, Input, Output} from '@angular/core';

@Component({
  selector: 'app-software-view-test',
  templateUrl: './software-view-test.component.html',
  styleUrls: ['./software-view-test.component.scss']
})
export class SoftwareViewTestComponent {
  @Input() dataDian: any = {};
  @Output() closeCard = new EventEmitter();
  onCloseCard() {
    this.closeCard.emit();
  }
}
