import { Directive, ElementRef, HostListener, Input, Renderer2 } from '@angular/core';

@Directive({
  selector: '[appCustomTooltip]'
})
export class CustomTooltipDirective {

  @Input('appCustomTooltip') tooltipText: string;
  // Input para la dirección del tooltip, ahora opcional
  @Input('tooltipDirection') tooltipDirection?: 'left' | 'right';

  tooltipElement: HTMLElement;

  constructor(private el: ElementRef, private renderer: Renderer2) {}

  @HostListener('mouseenter') onMouseEnter() {
    if (!this.tooltipElement) {
      this.createTooltip();
    }
  }

  @HostListener('mouseleave') onMouseLeave() {
    this.destroyTooltip();
  }

  @HostListener('mousedown') onMouseDown() {
    this.destroyTooltip();
  }

  @HostListener('keypress') onMousePress() {
    this.destroyTooltip();
  }


  createTooltip() {
    this.tooltipElement = this.renderer.createElement('span');
    this.renderer.appendChild(
      this.tooltipElement,
      this.renderer.createText(this.tooltipText)
    );

    this.renderer.appendChild(document.body, this.tooltipElement);
    this.renderer.addClass(this.tooltipElement, 'custom-tooltip');

    const hostPos = this.el.nativeElement.getBoundingClientRect();
    const tooltipPos = this.tooltipElement.getBoundingClientRect();
    const top = hostPos.bottom;
    let left: number;

    // Calcula la posición horizontal basándose en la dirección del tooltip, si se especifica
    if (this.tooltipDirection === 'left') {
      left = hostPos.left;
    } else if (this.tooltipDirection === 'right') {
      left = hostPos.right - tooltipPos.width;
    } else {
      // Posición por defecto (centrado)
      left = hostPos.left + (hostPos.width - tooltipPos.width) / 2;
    }

    // Establece el estilo para posicionar el tooltip
    this.renderer.setStyle(this.tooltipElement, 'position', 'absolute');
    this.renderer.setStyle(this.tooltipElement, 'top', `${top}px`);
    this.renderer.setStyle(this.tooltipElement, 'left', `${left}px`);
  }

  destroyTooltip() {
    if (this.tooltipElement) {
      this.renderer.removeChild(document.body, this.tooltipElement);
      this.tooltipElement = null;
    }
  }
}
