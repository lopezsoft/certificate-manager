import {Component, EventEmitter, Input, Output} from '@angular/core';

@Component({
  selector: 'app-document-view-data',
  templateUrl: './document-view-data.component.html',
  styleUrls: ['./document-view-data.component.scss']
})
export class DocumentViewDataComponent {
  @Input() dataDian: any = {};
  @Output() closeCard = new EventEmitter();
  onCloseCard() {
    this.closeCard.emit();
  }
}
