import { Component } from '@angular/core';

@Component({
  selector: 'app-support-button',
  templateUrl: './support-button.component.html',
  styleUrl: './support-button.component.scss'
})
export class SupportButtonComponent {
  isMenuOpen = false;

  toggleSupportMenu() {
    this.isMenuOpen = !this.isMenuOpen;
    const button = document.querySelector('.support-btn');
    if (button) {
      button.classList.toggle('active'); // Añadir la clase "active" para la animación
    }
  }

  openChat() {
    open('https://lopezsoftsas.zohodesk.com/portal', '_blank');
  }

  openSupport() {
    open('https://chatgpt.com/g/g-677c83026f708191a518848e4574ebfe-lewis-bot-asistente-virtual', '_blank');
  }

  openWhatsApp() {
    open('https://wa.me/573108435431', '_blank');
  }

  openYoutube() {
    open('https://www.youtube.com/@matiasapp', '_blank');
  }
}
