import {Component} from '@angular/core';
import {FormatsService} from "../../services/formats.service";
import {HttpResponsesService} from "../../utils";
import {CustomerService} from "../../services/companies/customers.service";
import {Company} from "../../models/companies-model";
import {animate, style, transition, trigger} from "@angular/animations";

@Component({
  selector: 'app-customer-view',
  templateUrl: './customer-view.component.html',
  styleUrl: './customer-view.component.scss',
  animations: [
    trigger('fadeInOut', [
      transition(':enter', [
        style({ opacity: 0 }),
        animate('300ms', style({ opacity: 1 })),
      ]),
      transition(':leave', [
        animate('300ms', style({ opacity: 0 })),
      ])
    ])
  ]
})
export class CustomerViewComponent {
  constructor(
    public format: FormatsService,
    protected http: HttpResponsesService,
    public customer: CustomerService,
  ) {
  }


  public get currentCustomer(): Company {
    return this.customer.currentCustomer;
  }

}
