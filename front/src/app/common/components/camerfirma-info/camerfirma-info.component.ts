import {Component, Input} from '@angular/core';

@Component({
  selector: 'app-camerfirma-info',
  templateUrl: './camerfirma-info.component.html',
  styleUrl: './camerfirma-info.component.scss'
})
export class CamerfirmaInfoComponent {
  @Input() public pin: string;
}
