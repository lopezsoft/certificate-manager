import {Component, EventEmitter, Output, Input, OnInit} from '@angular/core';

@Component({
  selector: 'app-html-editor',
  templateUrl: './html-editor.component.html',
  styleUrls: ['./html-editor.component.scss']
})
export class HtmlEditorComponent implements OnInit {
  @Output() valueChanged = new EventEmitter<string>();
  @Input() valueContent: string;
  constructor() { }
  ngOnInit(): void {
  }
  onChange(e) {
    this.valueChanged.emit(e.value);
  }

}
