import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-documents',
  template: `
    <router-outlet></router-outlet>
  `
})
export class DocumentsComponent implements OnInit {

  constructor() { }

  ngOnInit(): void {
  }

}
