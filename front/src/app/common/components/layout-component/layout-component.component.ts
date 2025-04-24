import {Component, EventEmitter, HostListener, Input, OnDestroy, OnInit, Output} from '@angular/core';
import {CoreConfigService} from '../../../../@core/services/config.service';

@Component({
  selector: 'app-layout-component',
  templateUrl: './layout-component.component.html',
  styleUrls: ['./layout-component.component.scss']
})
export class LayoutComponentComponent implements OnInit, OnDestroy {
  protected hideNavbar = false;
  protected isScreenSmall: boolean;
  @Input() isClicked = false;
  @Output() onToggleNavbar = new EventEmitter<boolean>();
  constructor(
    public coreConfigService: CoreConfigService,
  ) {
    this.coreConfigService.config = {
      layout: {
        expandBoxed: true,
        navbar: {
          hidden: false
        },
        menu: {
          collapsed: false
        },
        footer: {
          hidden: true
        },
        customizer: false,
        enableLocalStorage: false
      }
    };
    this.isScreenSmall = window.innerWidth <= 900;
    this.isClicked = false;
  }
  ngOnInit(): void {
    this.checkScreenSize();
  }
  @HostListener('window:resize', ['$event'])
  onResize(event: Event): void {
    this.checkScreenSize();
  }
  toggleNavbar(): void {
    this.onToggleNavbar.emit(true);
  }
  private checkScreenSize(): void {
    this.isScreenSmall = window.innerWidth <= 900;
  }

  ngOnDestroy(): void {
    this.coreConfigService.config = {
      layout: {
        expandBoxed: false,
        navbar: {
          hidden: false
        },
        menu: {
          collapsed: false
        },
        footer: {
          hidden: false
        },
        customizer: false,
        enableLocalStorage: false
      }
    };
  }
  isShowSidebar() {
    return this.isScreenSmall ? !this.isClicked : true;
  }
  isShowContent() {
    return this.isScreenSmall ? this.isClicked : true;
  }
}
