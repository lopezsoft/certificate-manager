import {Directive, ElementRef, HostListener, Renderer2} from '@angular/core';

@Directive({
  selector: '[appMoveElement]'
})
export class MoveElementDirective  {
  priceContainer: HTMLElement;
  priceList: HTMLElement;
  constructor(private el: ElementRef, private renderer: Renderer2) {
  }
  @HostListener('focus')
  onFocus(): void {
    if (!this.priceContainer) {
      this.createElement();
    }
    this.showElement();
  }
  @HostListener('mouseenter') onMouseEnter() {
    if (!this.priceContainer) {
      this.createElement();
    }
  }
  @HostListener('blur')
  onBlur() {
    this.hiddeElement();
  }
  @HostListener('mousedown') onMouseDown() {
    // this.hiddeElement();
  }
  createElement(): void {
    const priceContainer = document.querySelector('.price-container');
    if (priceContainer) {
      this.priceContainer = priceContainer as HTMLElement;
    }
  }
  showElement() {
    const hostPos = this.el.nativeElement.getBoundingClientRect();
    const top     = hostPos.bottom;
    let left      = (hostPos.left - (hostPos.width / 2 ));
    const priceList = this.priceContainer.querySelector('.price-list');
    if (priceList) {
      this.priceList = priceList as HTMLElement;
      left = hostPos.right - this.priceList.getBoundingClientRect().width;
    }
    this.renderer.addClass(this.priceContainer, 'active');
    this.renderer.setStyle(this.priceContainer, 'top', `${top}px`);
    this.renderer.setStyle(this.priceContainer, 'left', `${left}px`);
  }

  hiddeElement() {
    if (this.priceContainer) {
      this.renderer.removeClass(this.priceContainer, 'active');
    }
  }
}
