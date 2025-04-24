import {Component, EventEmitter, OnInit, Output} from '@angular/core';
import {animate, style, transition, trigger} from '@angular/animations';

@Component({
  selector: 'app-notification-card',
  templateUrl: './notification-card.component.html',
  styleUrls: ['./notification-card.component.scss'],
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
export class NotificationCardComponent implements OnInit {
  @Output() onCloseCard = new EventEmitter();
  constructor(
  ) { }

  ngOnInit(): void {
  }

  protected closeCardMessage() {
    this.onCloseCard.emit();
  }
}
