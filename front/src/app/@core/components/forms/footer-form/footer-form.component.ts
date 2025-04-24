import { Component, EventEmitter, Output, Input } from '@angular/core';

@Component({
  selector: 'app-footer-form',
  templateUrl: './footer-form.component.html',
  styleUrls: ['./footer-form.component.scss']
})
export class FooterFormComponent {

  @Output() saveAndCreateEvent = new EventEmitter<string>();
  @Output() saveAndCloseEvent = new EventEmitter<string>();
  @Output() closeEvent = new EventEmitter<string>();
  @Input() loading            : boolean = false;
  @Input() saveAClose         : boolean = false;
  @Input() saveACreate        : boolean = false;
  @Input() showAaveACreate    : boolean = true;
  @Input() maskSpinner        : string  = '';

  constructor(){
    this.showAaveACreate  = true;
  }

  cancelMessage() {
    this.closeEvent.emit()
  }

  saveAndCreateMessage() {
    this.saveAndCreateEvent.emit()
  }

  saveAndCloseMessage() {
    this.saveAndCloseEvent.emit()
  }

}
