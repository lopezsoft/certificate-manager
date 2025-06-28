import { Directive, ElementRef, HostListener, Input, Renderer2, OnDestroy } from '@angular/core';

@Directive({
  selector: '[appCustomTooltip]',
})
export class CustomTooltipDirective implements OnDestroy {
  @Input('appCustomTooltip') tooltipText: string | undefined | null;

  /**
   * Legacy input for horizontal alignment when tooltip is BELOW the element.
   * Maps to: 'left' -> alignment='start', 'right' -> alignment='end'.
   * If 'placement' is used and is not 'bottom', this input is ignored.
   */
  @Input() tooltipDirection?: 'left' | 'right'; // Tu input original

  /**
   * Nueva propiedad para la posición principal del tooltip.
   * Por defecto es 'bottom' para mantener la compatibilidad.
   */
  @Input() placement: 'top' | 'bottom' | 'left' | 'right' = 'bottom';

  /**
   * Nueva propiedad para la alineación del tooltip dentro de su 'placement'.
   * Por defecto es 'center'.
   */
  @Input() alignment: 'start' | 'center' | 'end' = 'center';

  @Input() tooltipDisabled = false;

  private tooltipElement: HTMLElement | null = null;
  private readonly scrollOffset = 5;

  constructor(private el: ElementRef<HTMLElement>, private renderer: Renderer2) {}

  @HostListener('mouseenter')
  onMouseEnter(): void {
    if (this.tooltipDisabled || !this.tooltipText || this.tooltipElement) {
      return;
    }
    this.createTooltip();
  }

  @HostListener('mouseleave')
  onMouseLeave(): void {
    this.destroyTooltip();
  }

  @HostListener('mousedown')
  onMouseDown(): void {
    this.destroyTooltip();
  }

  @HostListener('document:keydown.escape', ['$event'])
  onEscapeKey(): void {
    this.destroyTooltip();
  }

  createTooltip(): void {
    if (!this.tooltipText) { return; }

    this.tooltipElement = this.renderer.createElement('span');
    this.renderer.appendChild(
      this.tooltipElement,
      this.renderer.createText(this.tooltipText)
    );
    this.renderer.appendChild(document.body, this.tooltipElement);
    this.renderer.addClass(this.tooltipElement, 'custom-tooltip');
    this.renderer.setStyle(this.tooltipElement, 'position', 'fixed');
    this.renderer.setStyle(this.tooltipElement, 'z-index', '10000');

    const hostRect = this.el.nativeElement.getBoundingClientRect();
    const tooltipRect = this.tooltipElement.getBoundingClientRect();

    this.renderer.setStyle(this.tooltipElement, 'position', 'absolute');

    let top: number;
    let left: number;

    // Determinar la alineación final a usar
    let finalAlignment = this.alignment;
    // Si estamos en el placement 'bottom' (por defecto o explícito)
    // y el usuario NO ha especificado un 'alignment' explícitamente (usa el default 'center')
    // PERO SÍ ha especificado el 'tooltipDirection' legacy:
    if (this.placement === 'bottom' && this.alignment === 'center' && this.tooltipDirection) {
      if (this.tooltipDirection === 'left') {
        finalAlignment = 'start'; // Tu 'left' original ahora es 'start'
      } else if (this.tooltipDirection === 'right') {
        finalAlignment = 'end'; // Tu 'right' original ahora es 'end'
      }
    }

    // --- Cálculo de Posición con Ajuste de Scroll y Viewport ---
    switch (this.placement) {
      case 'top':
        top = hostRect.top - tooltipRect.height - this.scrollOffset;
        break;
      case 'left':
      case 'right':
        top = hostRect.top + (hostRect.height - tooltipRect.height) / 2; // Centrado verticalmente
        break;
      case 'bottom':
      default:
        top = hostRect.bottom + this.scrollOffset;
        break;
    }

    switch (this.placement) {
      case 'left':
        left = hostRect.left - tooltipRect.width - this.scrollOffset;
        break;
      case 'right':
        left = hostRect.right + this.scrollOffset;
        break;
      case 'top':
      case 'bottom':
      default:
        if (finalAlignment === 'start') {
          left = hostRect.left;
        } else if (finalAlignment === 'end') {
          left = hostRect.right - tooltipRect.width;
        } else { // center
          left = hostRect.left + (hostRect.width - tooltipRect.width) / 2;
        }
        break;
    }

    top += window.scrollY;
    left += window.scrollX;

    // --- Ajuste Básico de Colisión con Viewport (simplificado para brevedad, la lógica completa está en la respuesta anterior) ---
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    // Vertical
    if (top + tooltipRect.height - window.scrollY > viewportHeight) {
      const newTopAttempt = hostRect.top - tooltipRect.height - this.scrollOffset + window.scrollY;
      if (newTopAttempt - window.scrollY > 0) { top = newTopAttempt; }
    } else if (top - window.scrollY < 0) {
      const newTopAttempt = hostRect.bottom + this.scrollOffset + window.scrollY;
      if (newTopAttempt + tooltipRect.height - window.scrollY < viewportHeight) { top = newTopAttempt; }
    }
    // Horizontal
    if (left + tooltipRect.width - window.scrollX > viewportWidth) {
      left = viewportWidth - tooltipRect.width - this.scrollOffset + window.scrollX;
    }
    if (left - window.scrollX < 0) {
      left = window.scrollX + this.scrollOffset;
    }
    // Re-ajustar si el cambio de lado lo saca del viewport (ej. si alinear a la derecha hace que se salga por la izquierda)
    if (left + tooltipRect.width - window.scrollX > viewportWidth) { left = Math.max(window.scrollX + this.scrollOffset, viewportWidth - tooltipRect.width - this.scrollOffset + window.scrollX); }
    if (left - window.scrollX < 0) { left = window.scrollX + this.scrollOffset; }


    this.renderer.setStyle(this.tooltipElement, 'top', `${top}px`);
    this.renderer.setStyle(this.tooltipElement, 'left', `${left}px`);

    const tooltipId = `tooltip-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
    this.renderer.setAttribute(this.tooltipElement, 'id', tooltipId);
    this.renderer.setAttribute(this.tooltipElement, 'role', 'tooltip');
    this.renderer.setAttribute(this.el.nativeElement, 'aria-describedby', tooltipId);
  }

  destroyTooltip(): void {
    if (this.tooltipElement) {
      this.renderer.removeChild(document.body, this.tooltipElement);
      this.renderer.removeAttribute(this.el.nativeElement, 'aria-describedby');
      this.tooltipElement = null;
    }
  }

  ngOnDestroy(): void {
    this.destroyTooltip();
  }
}
