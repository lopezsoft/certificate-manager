import { Component, OnInit, OnDestroy, inject } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { NotificationsService, AppNotification } from 'app/layout/components/navbar/navbar-notification/notifications.service';
import { AuthenticationService } from 'app/auth/service';
import TokenService from 'app/utils/token.service';

@Component({
  selector: 'app-navbar-notification',
  templateUrl: './navbar-notification.component.html',
  standalone: false
})
export class NavbarNotificationComponent implements OnInit, OnDestroy {
  public notifications: AppNotification[] = [];
  private _unsubscribeAll: Subject<any> = new Subject();

  protected tokenService: TokenService = inject(TokenService);

  constructor(private _notificationsService: NotificationsService) { }

  get unreadCount(): number {
    return this.notifications.filter(n => !n.read_at).length;
  }

  ngOnInit(): void {
    // Subscribe to real-time updates from service
    this._notificationsService.onApiDataChange
      .pipe(takeUntil(this._unsubscribeAll))
      .subscribe(res => {
        this.notifications = res || [];
      });
    if (this.tokenService.isAuthenticated()) {
      // Fetch initial data
      this._notificationsService.getNotifications().subscribe();
    }
  }

  ngOnDestroy(): void {
    this._unsubscribeAll.next(null);
    this._unsubscribeAll.complete();
  }

  onRead(notification: AppNotification): void {
    if (notification.read_at) return; // Already read
    this._notificationsService.markAsRead(notification.id).subscribe();

    // Optionally navigate to notification.data.url here if provided
  }

  onReadAll(): void {
    if (this.unreadCount === 0) return;
    this._notificationsService.markAllAsRead().subscribe();
  }
}
