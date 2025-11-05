import { Injectable, OnDestroy } from '@angular/core';
import { BehaviorSubject, Observable, Subject, interval } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

export interface RefreshConfig {
  enabled: boolean;
  intervalSeconds: number;
  lastRefresh?: Date;
  nextRefresh?: Date;
}

@Injectable({
  providedIn: 'root'
})
export class AutoRefreshService implements OnDestroy {

  private destroy$ = new Subject<void>();
  private refreshTrigger$ = new Subject<void>();
  private config$ = new BehaviorSubject<RefreshConfig>({
    enabled: false,
    intervalSeconds: 300 // 5 minutos por defecto
  });

  private intervalSubscription: any;
  private countdownValue = 0;

  constructor() { }

  start(intervalSeconds: number = 300): void {
    this.stop();

    this.config$.next({
      enabled: true,
      intervalSeconds,
      lastRefresh: new Date()
    });

    this.countdownValue = intervalSeconds;

    this.intervalSubscription = interval(1000)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.countdownValue--;

        if (this.countdownValue <= 0) {
          this.countdownValue = intervalSeconds;
          const now = new Date();
          this.config$.next({
            ...this.config$.value,
            lastRefresh: now,
            nextRefresh: new Date(now.getTime() + (intervalSeconds * 1000))
          });
          this.refreshTrigger$.next();
        }
      });
  }

  stop(): void {
    if (this.intervalSubscription) {
      this.intervalSubscription.unsubscribe();
      this.intervalSubscription = null;
    }

    this.config$.next({
      ...this.config$.value,
      enabled: false,
      lastRefresh: undefined,
      nextRefresh: undefined
    });
  }

  changeInterval(seconds: number): void {
    const wasEnabled = this.config$.value.enabled;
    if (wasEnabled) {
      this.stop();
      this.start(seconds);
    } else {
      this.config$.next({
        ...this.config$.value,
        intervalSeconds: seconds
      });
    }
  }

  toggle(): void {
    if (this.config$.value.enabled) {
      this.stop();
    } else {
      this.start(this.config$.value.intervalSeconds);
    }
  }

  isActive(): boolean {
    return this.config$.value.enabled;
  }

  getConfig$(): Observable<RefreshConfig> {
    return this.config$.asObservable();
  }

  getRefreshTrigger$(): Observable<void> {
    return this.refreshTrigger$.asObservable();
  }

  getCountdown(): number {
    return this.countdownValue;
  }

  formatCountdown(): string {
    const minutes = Math.floor(this.countdownValue / 60);
    const seconds = this.countdownValue % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.stop();
  }
}
