import { Component, Input, OnInit } from '@angular/core';

@Component({
    selector: 'app-json-viewer',
    templateUrl: './json-viewer.component.html',
    styleUrls: ['./json-viewer.component.scss'],
    standalone: false
})
export class JsonViewerComponent implements OnInit {
  @Input() title: string = 'JSON Payload';
  @Input() payload: any;
  
  formattedJson: string = '';
  copied: boolean = false;

  ngOnInit(): void {
    this.parsePayload();
  }

  ngOnChanges(): void {
    this.parsePayload();
  }

  private parsePayload(): void {
    if (!this.payload) {
      this.formattedJson = '';
      return;
    }
    try {
      this.formattedJson = typeof this.payload === 'string' 
        ? JSON.stringify(JSON.parse(this.payload), null, 2) 
        : JSON.stringify(this.payload, null, 2);
    } catch {
      this.formattedJson = String(this.payload);
    }
  }

  copyToClipboard(event: Event): void {
    event.stopPropagation();
    
    const setCopiedState = () => {
      this.copied = true;
      setTimeout(() => {
        this.copied = false;
        // Trigger feather replace manually if needed, but d-none handles it
      }, 1800);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(this.formattedJson)
        .then(setCopiedState)
        .catch(err => console.error('Error copying text: ', err));
    } else {
      const textArea = document.createElement('textarea');
      textArea.value = this.formattedJson;
      textArea.style.top = '0';
      textArea.style.left = '0';
      textArea.style.position = 'fixed';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      try {
        if (document.execCommand('copy')) setCopiedState();
      } catch (err) {
        console.error('Fallback copy failed', err);
      }
      document.body.removeChild(textArea);
    }
  }
}
